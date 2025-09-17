<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'model' => 'string|max:50'
        ]);

        $message = $request->input('message');

        $payload = [
            'sessionId' => '3d27af56883d480885903e52dd35d1d5', // fixo ou pode vir do request
            'action' => 'sendMessage',
            'chatInput' => $message,
        ];

        try {
            // Log da requisição
            Log::info('Enviando mensagem para n8n', [
                'url' => 'https://n8n.atoon.site/webhook/30e4ee4f-d50b-4646-b3ab-dc5c666cc532/chat',
                'payload' => $payload
            ]);

            $response = Http::timeout(60)->post(
                'https://n8n.atoon.site/webhook/30e4ee4f-d50b-4646-b3ab-dc5c666cc532/chat',
                $payload
            );

            // Log da resposta (mesmo se for erro)
            Log::info('Resposta do n8n', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'response' => $data['output'] ?? 'Sem resposta do modelo'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro na comunicação com Ollama',
                    'details' => $response->body(),
                ], 500);
            }

        } catch (\Exception $e) {
            // Log do erro
            Log::error('Falha na comunicação com n8n', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ollama não está disponível: ' . $e->getMessage()
            ], 500);
        }
    }
}
