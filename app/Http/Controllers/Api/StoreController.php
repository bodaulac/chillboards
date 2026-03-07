<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index()
    {
        return response()->json(Store::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|unique:stores',
            'store_name' => 'required',
            'platform' => 'required',
            'credentials' => 'nullable|array',
            'settings' => 'nullable|array',
            'active' => 'boolean'
        ]);

        $store = Store::create($validated);
        return response()->json($store, 201);
    }

    public function show($id)
    {
        return response()->json(Store::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $store = Store::findOrFail($id);
        $store->update($request->all());
        return response()->json($store);
    }

    public function destroy($id)
    {
        Store::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
