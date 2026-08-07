<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    // Listar chats del usuario
   public function index()
{
    return Conversation::whereHas('users', function ($q) {
            $q->where('user_id', auth()->id());
        })
        ->with(['users:id,name,lastname', 'lastMessage'])
        ->withCount([
            'messages as unread_count' => function ($q) {
                $q->where('is_read', false)
                  ->where('sender_id', '!=', auth()->id());
            }
        ])
        ->orderByDesc(
            Message::select('created_at')
                ->whereColumn('conversation_id', 'conversations.id')
                ->latest()
                ->limit(1)
        )
        ->get();
}


    // Crear conversación (1 a 1)
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:user,id',
        ]);

        $authId = auth()->id();

        // Buscar si ya existe conversación entre ambos
        $conversation = Conversation::whereHas('users', function ($q) use ($authId) {
                $q->where('user_id', $authId);
            })
            ->whereHas('users', function ($q) use ($request) {
                $q->where('user_id', $request->user_id);
            })
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $conversation = Conversation::create();

        $conversation->users()->attach([
            $authId,
            $request->user_id,
        ]);

        return $conversation;
    }
}

