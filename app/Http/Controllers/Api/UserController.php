<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller {

    // ADMIN: List all sellers
    public function index(Request $request) {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = User::where('role', 'seller')->with(['team', 'leadingTeam']);
        
        if ($request->has('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        return response()->json($query->get());
    }

    // ADMIN: Create a new Seller
    public function store(Request $request) {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'team_id' => 'nullable|exists:teams,id',
            'seller_code' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'seller',
            'team_id' => $validated['team_id'] ?? null,
            'seller_code' => $validated['seller_code'] ?? null,
        ]);

        return response()->json($user->load('team'), 201);
    }

    // ADMIN: Update Seller
    public function update(Request $request, $id) {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes', 'required', 'string', 'email', 'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'sometimes|nullable|string|min:8',
            'team_id' => 'sometimes|nullable|exists:teams,id',
            'role' => 'sometimes|required|in:admin,leader,seller',
            'seller_code' => 'sometimes|nullable|string|max:50'
        ]);

        if (isset($validated['password']) && $validated['password']) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user->load('team'));
    }

    // ADMIN: Delete User
    public function destroy($id) {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);
        
        // Prevent deleting self
        if ($user->id === Auth::id()) {
            return response()->json(['error' => 'Cannot delete your own account'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
