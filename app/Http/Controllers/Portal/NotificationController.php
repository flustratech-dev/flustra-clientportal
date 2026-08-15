<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()->notifications()->latest()->paginate(30);

        return view('portal.notifications', compact('notifications'));
    }

    /** Dipanggil tiap 30 detik oleh lonceng di header. */
    public function poll(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'unread' => $user->notifications()->unread()->count(),
            'items'  => $user->notifications()->latest()->limit(8)->get()->map(fn (Notification $n) => [
                'id'      => $n->id,
                'title'   => $n->title,
                'body'    => $n->body,
                'type'    => $n->type,
                'url'     => $n->url,
                'is_read' => $n->read_at !== null,
                'time'    => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function read(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === Auth::id(), 404);

        $notification->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return $notification->url ? redirect($notification->url) : back();
    }

    public function readAll(Request $request)
    {
        Auth::user()->notifications()->unread()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Semua notifikasi ditandai terbaca.');
    }
}
