<?php

namespace App\Http\Controllers;

use App\Models\Notifications;
use App\NotificationType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class NotificationsController extends Controller
{
    //
    public function index()
    {
        return Notifications::where('receiver_id', auth()->id())
            ->latest()
            ->limit(20)
            ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'type' => ['required', new Enum(NotificationType::class)],
            'title' => 'required|string|max:100',
            'body' => 'nullable|string',
            'data' => 'nullable|array',
        ]);

        return Notifications::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $request->receiver_id,
            'type' => $request->type,
            'title' => $request->title,
            'body' => $request->body,
            'data' => $request->data,
        ]);
    }

    public function markAsRead(Notifications $notification)
    {
        if ($notification->receiver_id !== auth()->id()) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notificación marcada como leída'
        ]);
    }


    public function unreadCount()
    {
        $count = Notifications::where('receiver_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return response()->json([
            'count' => $count
        ]);
    }
}
