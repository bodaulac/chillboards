<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\Store;
use App\Services\ProductSheetSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TeamController extends Controller {

    // ADMIN: List all teams
    public function index() {
        if (!Auth::user()->isAdmin()) return response()->json(['error' => 'Unauthorized'], 403);
        return response()->json(Team::with(['leader', 'members', 'stores'])->get());
    }

    // ADMIN: Create Team
    public function store(Request $request) {
        if (!Auth::user()->isAdmin()) return response()->json(['error' => 'Unauthorized'], 403);
        
        $validated = $request->validate([
            'name' => 'required|string|unique:teams',
            'leader_email' => 'required|email|exists:users,email'
        ]);

        // Find the user by email (must exist)
        $leader = User::where('email', $validated['leader_email'])->first();
        
        if (!$leader) {
            return response()->json([
                'error' => 'User with this email does not exist. Please create a seller first.'
            ], 404);
        }

        // Check if user is already leading another team
        if ($leader->leadingTeam()->exists()) {
            return response()->json([
                'error' => 'This user is already leading another team.'
            ], 422);
        }

        // Create team with this seller as leader (no role change needed)
        $team = Team::create([
            'name' => $validated['name'],
            'leader_id' => $leader->id,
            'settings' => ['can_add_stores' => true],
            'product_sheet_url' => $request->input('product_sheet_url')
        ]);

        // Assign leader to this team
        $leader->update(['team_id' => $team->id]);

        return response()->json($team->load('leader'), 201);
    }

    // LEADER/ADMIN: Get my team details (members, stores)
    public function show(Request $request, $id) {
        $user = Auth::user();
        if ($id == 'my') { // Handy alias
            if (!$user->team_id) return response()->json(['error' => 'No team assigned'], 404);
            $id = $user->team_id;
        }

        $team = Team::findOrFail($id);

        if (!$user->isAdmin() && $user->team_id !== $team->id) {
             return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Load members and the stores assigned to this TEAM
        return response()->json($team->load(['members', 'stores']));
    }

    // LEADER/ADMIN: Update Team
    public function update(Request $request, $id) {
        $user = Auth::user();
        $team = Team::findOrFail($id);

        if (!$user->isAdmin() && $user->team_id !== $team->id) {
             return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|unique:teams,name,' . $team->id,
            'product_sheet_url' => 'nullable|url',
            'settings' => 'sometimes|array',
            'leader_email' => 'sometimes|email|exists:users,email'
        ]);

        // Handle leader change if leader_email is provided
        if (isset($validated['leader_email'])) {
            $newLeader = User::where('email', $validated['leader_email'])->first();
            
            if (!$newLeader) {
                return response()->json(['error' => 'User not found'], 404);
            }

            // Check if new leader is already leading another team
            if ($newLeader->leadingTeam()->where('id', '!=', $team->id)->exists()) {
                return response()->json([
                    'error' => 'This user is already leading another team.'
                ], 422);
            }

            // Unassign old leader from team
            if ($team->leader_id) {
                User::where('id', $team->leader_id)->update(['team_id' => null]);
            }

            // Assign new leader to team
            $newLeader->update(['team_id' => $team->id]);
            $team->leader_id = $newLeader->id;
            
            unset($validated['leader_email']); // Remove from validated array
        }

        $team->update($validated);

        return response()->json($team->load('leader'));
    }

    // ADMIN: Assign Store to Team
    public function assignStore(Request $request, $id) {
        if (!Auth::user()->isAdmin()) return response()->json(['error' => 'Unauthorized'], 403);
        
        $team = Team::findOrFail($id);
        $storeId = $request->input('store_id');
        
        $team->stores()->syncWithoutDetaching([$storeId]);
        return response()->json(['success' => true]);
    }

    // LEADER: Create/Add Member (Seller)
    public function addMember(Request $request, $id) {
        $user = Auth::user();
        $team = Team::findOrFail($id);
        
        if (!$user->isAdmin() && ($user->team_id !== $team->id || !$user->isLeader())) {
             return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string',
            'password' => 'required|min:6'
        ]);

        $member = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'seller',
            'team_id' => $team->id
        ]);

        return response()->json($member, 201);
    }

    // LEADER: Delegate Store to Member
    public function delegateStore(Request $request, $id) {
        $user = Auth::user();
        $team = Team::findOrFail($id);
        
        if (!$user->isAdmin() && ($user->team_id !== $team->id || !$user->isLeader())) {
             return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'store_id' => 'required|exists:stores,id',
            'permission' => 'in:view,edit'
        ]);

        // Verify the store belongs to this team
        if (!$team->stores()->where('stores.id', $validated['store_id'])->exists()) {
             return response()->json(['error' => 'Store does not belong to this team'], 400);
        }

        // Verify user belongs to this team
        if (User::where('id', $validated['user_id'])->where('team_id', $team->id)->doesntExist()) {
             return response()->json(['error' => 'User does not belong to this team'], 400);
        }

        // Attach
        $targetUser = User::find($validated['user_id']);
        $targetUser->assignedStores()->syncWithoutDetaching([
            $validated['store_id'] => ['permission_level' => $validated['permission']]
        ]);

        return response()->json(['success' => true]);
    }

    // LEADER/ADMIN: Sync Team Products from Google Sheet
    public function syncProducts(Request $request, $id) {
        $user = Auth::user();
        $team = Team::findOrFail($id);
        
        if (!$user->isAdmin() && $user->team_id !== $team->id) {
             return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $service = app(ProductSheetSyncService::class);
            $result = $service->syncTeamProducts($team);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ADMIN: Delete Team
    public function destroy($id) {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $team = Team::findOrFail($id);
        
        // Unassign all members from this team (including leader)
        User::where('team_id', $team->id)->update(['team_id' => null]);
        
        // Delete the team (cascade will handle pivot tables)
        $team->delete();

        // Note: We do NOT delete the leader anymore - they're just a seller
        // They can be reused as a leader for another team or continue as a regular seller

        return response()->json(['message' => 'Team deleted successfully']);
    }
}
