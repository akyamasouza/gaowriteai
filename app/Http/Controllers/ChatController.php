<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Table;

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
            if ( isset($data['document']) && $data['document'] == "true") {
                $documentContent = $output;
                
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
            
            $sectionStyle = [
                'marginTop' => 1800,
                'marginBottom' => 720,
                'marginLeft' => 720,
                'marginRight' => 720,
                'headerHeight' => 1000,
            ];
            
            $section = $phpWord->addSection($sectionStyle);
            
            // Adicionar cabeçalho
            $header = $section->addHeader();
            
            // Criar tabela simples sem configurações complexas
            $table = $header->addTable([
                'borderSize' => 0,
                'cellMargin' => 0
            ]);
            
            $table->addRow(800);
            
            // Coluna 1 - Logo esquerda
            $cell1 = $table->addCell(1500, ['valign' => 'center']);
            
            // Verificar se a imagem existe
            $logoPath = public_path('images/rondolandia.png');
            
            if (file_exists($logoPath)) {
                try {
                    $cell1->addImage($logoPath, [
                        'width' => 80,
                        'height' => 80,
                        'alignment' => Jc::CENTER
                    ]);
                } catch (\Exception $imgError) {
                    Log::warning('Erro ao adicionar imagem: ' . $imgError->getMessage());
                }
            } else {
                Log::warning('Logo não encontrada em: ' . $logoPath);
            }
            
            // Coluna 2 - Texto central
            $cell2 = $table->addCell(6000, ['valign' => 'center']);
            
            // Estilos de texto
            $titleStyle = [
                'bold' => true,
                'size' => 11,
                'color' => '000000',
                'name' => 'Arial'
            ];
            
            $subtitleStyle = [
                'bold' => false,
                'size' => 10,
                'color' => '000000',
                'name' => 'Arial'
            ];
            
            $centerAlignment = ['alignment' => Jc::CENTER];
            
            // Textos do cabeçalho
            $cell2->addText('ESTADO DE MATO GROSSO', $titleStyle, $centerAlignment);
            $cell2->addText('PREFEITURA MUNICIPAL DE RONDOLÂNDIA', $titleStyle, $centerAlignment);
            $cell2->addText('CONTROLADORIA GERAL', $subtitleStyle, $centerAlignment);
            $cell2->addText('GESTÃO 2025-2028', $subtitleStyle, $centerAlignment);
            
            // Coluna 3 - Logo direita
            $cell3 = $table->addCell(1500, ['valign' => 'center']);
            
            if (file_exists($logoPath)) {
                try {
                    $cell3->addImage($logoPath, [
                        'width' => 80,
                        'height' => 80,
                        'alignment' => Jc::CENTER
                    ]);
                } catch (\Exception $imgError) {
                    Log::warning('Erro ao adicionar segunda imagem: ' . $imgError->getMessage());
                }
            }
            
            // Linha separadora
            $header->addTextBreak(1);
            
            $lineStyle = [
                'size' => 8,
                'color' => '808080',
                'name' => 'Arial'
            ];
            $header->addText(str_repeat('- ', 50), $lineStyle, $centerAlignment);
            
            // Adicionar conteúdo principal
            if (strip_tags($content) !== $content) {
                // Conteúdo HTML
                try {
                    Html::addHtml($section, $content, false, false);
                } catch (\Exception $htmlError) {
                    Log::warning('Erro ao processar HTML, usando texto simples: ' . $htmlError->getMessage());
                    // Fallback para texto simples
                    $section->addText(strip_tags($content));
                }
            } else {
                // Conteúdo texto simples
                $paragraphs = explode("\n\n", $content);
                foreach ($paragraphs as $paragraph) {
                    if (trim($paragraph)) {
                        $section->addText(trim($paragraph));
                    }
                }
            }
            
            // Gerar arquivo
            $fileName = 'documento_' . time() . '.docx';
            $filePath = storage_path('app/public/documents/' . $fileName);
            
            // Criar diretório se não existir
            $directory = dirname($filePath);
            if (!file_exists($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new \Exception('Não foi possível criar o diretório: ' . $directory);
                }
            }
            
            // Verificar permissões de escrita
            if (!is_writable($directory)) {
                throw new \Exception('Diretório não tem permissão de escrita: ' . $directory);
            }
            
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($filePath);
            
            // Verificar se o arquivo foi criado
            if (!file_exists($filePath)) {
                throw new \Exception('Arquivo não foi criado: ' . $filePath);
            }
            
            return '/storage/documents/' . $fileName;
            
        } catch (\Exception $e) {
            Log::error('Erro ao gerar documento Word', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 200)
            ]);
            throw new \Exception('Erro ao gerar documento: ' . $e->getMessage());
        }
    }
}