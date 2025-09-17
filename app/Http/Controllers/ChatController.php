<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;

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
            'sessionId' => '3d27af56883d480885903e52dd35d1d5',
            'action' => 'sendMessage',
            'chatInput' => $message,
        ];

        try {
            Log::info('Enviando mensagem para n8n', [
                'url' => 'https://n8n.atoon.site/webhook/30e4ee4f-d50b-4646-b3ab-dc5c666cc532/chat',
                'payload' => $payload
            ]);

            $response = Http::timeout(60)->post(
                'https://n8n.atoon.site/webhook/30e4ee4f-d50b-4646-b3ab-dc5c666cc532/chat',
                $payload
            );

            Log::info('Resposta do n8n', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro na comunicação com n8n',
                    'details' => $response->body(),
                ], $response->status());
            }

            // Verifica se a resposta é JSON válida
            $responseBody = $response->body();
            $data = json_decode($responseBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Resposta não é JSON válido', [
                    'json_error' => json_last_error_msg(),
                    'response_body' => substr($responseBody, 0, 500)
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Resposta inválida do servidor',
                    'details' => 'Formato de resposta não reconhecido'
                ], 500);
            }

            $output = $data['output'] ?? 'Sem resposta do modelo';
            
            // Verifica se é uma resposta de documento
            if (preg_match('/^"document":\s*"true",?\s*(.*)$/s', $output, $matches)) {
                $documentContent = trim($matches[1], '"');
                
                try {
                    $documentPath = $this->generateWordDocument($documentContent);
                    
                    return response()->json([
                        'success' => true,
                        'type' => 'document',
                        'downloadUrl' => $documentPath,
                        'message' => 'Documento gerado com sucesso!'
                    ]);
                } catch (\Exception $e) {
                    Log::error('Erro ao gerar documento', ['error' => $e->getMessage()]);
                    
                    return response()->json([
                        'success' => false,
                        'error' => 'Erro ao gerar documento',
                        'details' => $e->getMessage()
                    ], 500);
                }
            } else {
                return response()->json([
                    'success' => true,
                    'type' => 'text',
                    'response' => $output
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Falha na comunicação com n8n', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Serviço indisponível',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function generateWordDocument(string $content): string
    {
        try {
            $phpWord = new PhpWord();
            $section = $phpWord->addSection();
            
            if (strip_tags($content) !== $content) {
                Html::addHtml($section, $content, false, false);
            } else {
                $paragraphs = explode("\n\n", $content);
                foreach ($paragraphs as $paragraph) {
                    if (trim($paragraph)) {
                        $section->addText(trim($paragraph));
                    }
                }
            }
            
            $fileName = 'documento_' . time() . '.docx';
            $filePath = storage_path('app/public/documents/' . $fileName);
            
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($filePath);
            
            return '/storage/documents/' . $fileName;
            
        } catch (\Exception $e) {
            Log::error('Erro ao gerar documento Word', [
                'exception' => $e->getMessage(),
                'content' => substr($content, 0, 200)
            ]);
            
            throw new \Exception('Erro ao gerar documento: ' . $e->getMessage());
        }
    }
}