<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trend;
use App\Models\News;
use Illuminate\Http\Request;

class TrendController extends Controller
{
    /**
     * Get Trends
     */
    public function index(Request $request)
    {
        $query = Trend::query();

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }
        
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        return response()->json(
            $query->orderBy('trending_score', 'desc')->paginate(50)
        );
    }

    /**
     * Get News (Notifications)
     */
    public function getNews(Request $request)
    {
        $query = News::query();

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }
        
        // Unread only?
        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }

        return response()->json(
            $query->orderBy('event_timestamp', 'desc')->paginate(20)
        );
    }

    /**
     * Mark news as read
     */
    public function markNewsRead($id)
    {
        News::where('id', $id)->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }
}
