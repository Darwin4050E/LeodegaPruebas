<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    // Mensajes de una conversación
    public function index(Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        return $conversation
            ->messages()
            ->with('sender:id,name')
            ->orderBy('created_at', 'asc')
            ->paginate(20);
    }

    // Enviar mensaje
    public function store(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $request->validate([
            'body' => 'required|string',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => auth()->id(),
            'body' => $request->body,
        ]);

        $conversation->users
            ->where('id', '!=', auth()->id())
            ->each(function ($user) use ($conversation, $message) {
            NotificationService::send(
                auth()->id(),          // quien envía
                $user->id,             // quien recibe
                NotificationType::MESSAGE,
                'Nuevo mensaje',
                Str::limit($message->body, 50),
                [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                ]
            );
        });

        return $message;
        
    }

    // Marcar mensajes como leídos
    public function markRead(Conversation $conversation)
    {
        // seguridad
        if (!$conversation->users->contains(auth()->id())) {
            abort(403);
        }

        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }


    private function authorizeConversation(Conversation $conversation)
    {
        if (!$conversation->users()->where('user_id', auth()->id())->exists()) {
            abort(403);
        }
    }
}
