<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Order;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index($orderId)
    {
        $order = Order::where('order_id', $orderId)->firstOrFail();
        $notes = Note::where('order_id', $order->id)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notes
        ]);
    }

    public function store(Request $request, $orderId)
    {
        $order = Order::where('order_id', $orderId)->firstOrFail();
        
        $request->validate([
            'content' => 'required|string'
        ]);

        $note = Note::create([
            'order_id' => $order->id,
            'user_id' => $request->user()?->id,
            'content' => $request->input('content')
        ]);

        return response()->json([
            'success' => true,
            'data' => $note->load('user:id,name')
        ], 201);
    }

    public function destroy($id)
    {
        $note = Note::findOrFail($id);
        
        // Check if user is owner or admin (basic check)
        if (auth()->user()->id !== $note->user_id && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $note->delete();
        return response()->json(['success' => true]);
    }
}
