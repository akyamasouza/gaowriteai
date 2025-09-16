<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'model' => 'string|max:50'
        ]);

        $message = $request->input('message');
        $model = $request->input('model', 'gpt-oss:20b'); // modelo padrão

        try {
            // Fazer requisição para o Ollama
            $response = Http::timeout(60)->post('http://localhost:11434/api/generate', [
                'model' => $model,
                'prompt' => $message,
                'stream' => false
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'response' => $data['response'] ?? 'Resposta vazia'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro na comunicação com Ollama'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ollama não está disponível: ' . $e->getMessage()
            ], 500);
        }
    }
}