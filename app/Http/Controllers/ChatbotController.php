<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ChatHistory;
use Ramsey\Uuid\Uuid;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        // Kollar om användaren är inloggad
        $user = $request->user();
    
        // Om användaren är inloggad och har en session_id, använd den. Annars generera en ny session_id.
        $session_id = $request->session_id ?? ($user ? (string) Uuid::uuid4() : (string) Uuid::uuid4());
    
        // Hämtar tidigare chattmeddelanden om användaren är inloggad och har en session_id
        $previousMessages = [];
        if ($user && $session_id) {
            $previousMessages = ChatHistory::where('user_id', $user->id)
                ->where('session_id', $session_id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn($chat) => [
                    ['role' => 'user', 'content' => $chat->user_message],
                    ['role' => 'assistant', 'content' => $chat->bot_response],
                ])
                ->flatten(1)
                ->toArray();
        }
    
        // Lägger till det nya meddelandet
        $messages = array_merge($previousMessages, [
            ['role' => 'user', 'content' => $request->message]
        ]);
    
        // Skickar förfrågan till LLM
        $response = Http::post('http://localhost:11434/api/chat', [
            'model' => 'mistral',
            'messages' => $messages,
            'stream' => false
        ]);
    
        $botReply = $response->json()['message']["content"] ?? 'Jag kunde inte förstå din fråga.';
    
        // Sparar chatt-historik om användaren är inloggad
        if ($user) {
            ChatHistory::create([
                'user_id' => $user->id,
                'session_id' => $session_id,
                'user_message' => $request->message,
                'bot_response' => $botReply
            ]);
        }
    
        return response()->json([
            'session_id' => $session_id,
            'message' => $botReply
        ]);
    }    
}    

