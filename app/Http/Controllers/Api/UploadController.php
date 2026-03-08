<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Upload a file (design/mockup) to Backblaze B2.
     * Returns the public URL for use in fulfillment forms.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
            'folder' => 'nullable|string|in:designs,mockups,labels',
        ]);

        $file = $request->file('file');
        $folder = $request->input('folder', 'designs');

        // Sanitize filename: timestamp + original name
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $safeName = Str::slug($originalName) . '-' . now()->format('ymd-His') . '.' . $extension;
        $path = "{$folder}/{$safeName}";

        try {
            Storage::disk('b2')->put($path, file_get_contents($file), 'public');

            // Build public URL
            $endpoint = rtrim(config('filesystems.disks.b2.endpoint'), '/');
            $bucket = config('filesystems.disks.b2.bucket');
            $publicUrl = "{$endpoint}/{$bucket}/{$path}";

            return response()->json([
                'success' => true,
                'url' => $publicUrl,
                'path' => $path,
                'name' => $safeName,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
