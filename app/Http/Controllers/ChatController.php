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
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'model' => 'string|max:50'
        ]);

        $message = $request->input('message');
        $files = $request->file('files');
        
        $filesData = [];
        
        $finalMessage = $message;
        
        if ($files && is_array($files)) {
            foreach ($files as $index => $file) {
                try {
                    if (!$file->isValid()) {
                        Log::warning("Arquivo inválido no índice {$index}");
                        continue;
                    }
                    
                    $fileName = $file->getClientOriginalName();
                    $fileSize = $file->getSize();
                    $mimeType = $file->getMimeType();
                    $extension = $file->getClientOriginalExtension();
                    
                    $allowedTypes = [
                        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                        'application/pdf',
                        'text/plain', 'text/csv',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    ];
                    
                    if (!in_array($mimeType, $allowedTypes)) {
                        Log::warning("Tipo de arquivo não permitido: {$mimeType} para arquivo {$fileName}");
                        continue;
                    }
                    
                    // Extrair conteúdo baseado no tipo de arquivo
                    $extractedContent = $this->extractFileContent($file, $mimeType, $extension);
                    
                    $fileData = [
                        'name' => $fileName,
                        'size' => $fileSize,
                        'type' => $mimeType,
                        'extension' => $extension,
                        'content' => $extractedContent,
                        'content_preview' => substr($extractedContent, 0, 100) . '...' // Para logs
                    ];
                    
                    $filesData[] = $fileData;
                    
                    Log::info("Arquivo processado com sucesso", [
                        'nome' => $fileName,
                        'tamanho' => $fileSize,
                        'tipo' => $mimeType,
                        'content_length' => strlen($extractedContent)
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error("Erro ao processar arquivo no índice {$index}", [
                        'erro' => $e->getMessage(),
                        'arquivo' => $file->getClientOriginalName() ?? 'desconhecido'
                    ]);
                    continue;
                }
            }
        }

        $sessionId = $request->input('chatSessionId') ?? Str::uuid()->toString();

        $payload = [
            'sessionId' => $sessionId,
            'action' => 'sendMessage',
            'chatInput' => $finalMessage,
            'files' => []
        ];

        if (!empty($filesData)) {
            $payload["files"] = $fileData['content'];
        }

        try {
            Log::info('Enviando mensagem para n8n', [
                'url' => 'https://webhookn8n.gaotech.com.br/webhook/30e4ee4f-d50b-4646-b3ab-dc5c666cc532/chat',
                'payload' => $payload
            ]);

            $response = Http::timeout(520)->post(
                'https://webhookn8n.gaotech.com.br/webhook/30e4ee4f-d50b-4646-b3ab-dc5c666cc532/chat',
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

    /**
     * Extrai o conteúdo de texto de diferentes tipos de arquivo
     */
    private function extractFileContent($file, string $mimeType, string $extension): string
    {
        try {
            switch ($mimeType) {
                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                    return $this->extractWordContent($file);
                
                case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                    return $this->extractExcelContent($file);
                
                case 'text/plain':
                case 'text/csv':
                    return file_get_contents($file->getRealPath());
                
                case 'application/pdf':
                    return $this->extractPdfContent($file);
                
                case 'image/jpeg':
                case 'image/png':
                case 'image/gif':
                case 'image/webp':
                    // Para imagens, retorna informações básicas ou Base64 se necessário
                    $fileContent = file_get_contents($file->getRealPath());
                    return base64_encode($fileContent);
                
                default:
                    Log::warning("Tipo de arquivo não suportado para extração de texto: {$mimeType}");
                    return "Conteúdo do arquivo não pode ser extraído como texto.";
            }
        } catch (\Exception $e) {
            Log::error("Erro ao extrair conteúdo do arquivo", [
                'mimeType' => $mimeType,
                'error' => $e->getMessage()
            ]);
            return "Erro ao extrair conteúdo do arquivo: " . $e->getMessage();
        }
    }

    /**
     * Extrai texto de documentos Word (.docx)
     */
    private function extractWordContent($file): string
    {
        try {
            $filePath = $file->getRealPath();
            $phpWord = IOFactory::load($filePath);
            
            $textContent = '';
            
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $textContent .= $element->getText();
                    } elseif (method_exists($element, 'getElements')) {
                        // Para elementos como tabelas, cabeçalhos, etc.
                        $textContent .= $this->extractElementText($element);
                    }
                }
            }
            
            return trim($textContent);
        } catch (\Exception $e) {
            Log::error("Erro ao extrair texto do Word", ['error' => $e->getMessage()]);
            throw new \Exception("Não foi possível extrair o texto do documento Word: " . $e->getMessage());
        }
    }

    /**
     * Extrai texto de elementos aninhados (como tabelas)
     */
    private function extractElementText($element): string
    {
        $text = '';
    
        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                if (method_exists($child, 'getText')) {
                    $text .= $child->getText();
                } elseif (method_exists($child, 'getElements')) {
                    $text .= $this->extractElementText($child);
                }
    
                // Adiciona quebra de linha entre parágrafos
                if ($child instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $text .= "\n";
                } elseif ($child instanceof \PhpOffice\PhpWord\Element\TextBreak) {
                    $text .= "\n";
                }
            }
        }
    
        // Quebra de linha ao final de cada elemento de nível superior
        $text .= "\n";
    
        return $text;
    }    

    /**
     * Extrai texto de planilhas Excel (.xlsx)
     */
    private function extractExcelContent($file): string
    {
        try {
            // Você precisará instalar PhpSpreadsheet para isso
            // composer require phpoffice/phpspreadsheet
            
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                return "PhpSpreadsheet não está instalado. Instale com: composer require phpoffice/phpspreadsheet";
            }
            
            $filePath = $file->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            
            $textContent = '';
            
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $textContent .= "=== Planilha: " . $sheet->getTitle() . " ===\n";
                
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                
                for ($row = 1; $row <= $highestRow; $row++) {
                    $rowData = [];
                    for ($col = 'A'; $col <= $highestColumn; $col++) {
                        $cellValue = $sheet->getCell($col . $row)->getCalculatedValue();
                        if (!empty($cellValue)) {
                            $rowData[] = $cellValue;
                        }
                    }
                    if (!empty($rowData)) {
                        $textContent .= implode("\t", $rowData) . "\n";
                    }
                }
                $textContent .= "\n";
            }
            
            return trim($textContent);
        } catch (\Exception $e) {
            Log::error("Erro ao extrair texto do Excel", ['error' => $e->getMessage()]);
            return "Erro ao extrair conteúdo do Excel: " . $e->getMessage();
        }
    }

    /**
     * Extrai texto de PDFs
     */
    private function extractPdfContent($file): string
    {
        try {
            // Para PDF, você pode usar smalot/pdfparser
            // composer require smalot/pdfparser
            
            if (!class_exists('\Smalot\PdfParser\Parser')) {
                return "PdfParser não está instalado. Instale com: composer require smalot/pdfparser";
            }
            
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($file->getRealPath());
            
            return $pdf->getText();
        } catch (\Exception $e) {
            Log::error("Erro ao extrair texto do PDF", ['error' => $e->getMessage()]);
            return "Erro ao extrair conteúdo do PDF: " . $e->getMessage();
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
            
            $header = $section->addHeader();
            
            $table = $header->addTable([
                'borderSize' => 0,
                'cellMargin' => 0
            ]);
            
            $table->addRow(800);
            
            $cell1 = $table->addCell(1500, ['valign' => 'center']);
            
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
            
            $cell2 = $table->addCell(6000, ['valign' => 'center']);
            
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
            
            $cell2->addText('ESTADO DE MATO GROSSO', $titleStyle, $centerAlignment);
            $cell2->addText('PREFEITURA MUNICIPAL DE RONDOLÂNDIA', $titleStyle, $centerAlignment);
            $cell2->addText('CONTROLADORIA GERAL', $subtitleStyle, $centerAlignment);
            $cell2->addText('GESTÃO 2025-2028', $subtitleStyle, $centerAlignment);
            
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
            
            $header->addTextBreak(1);
            
            $lineStyle = [
                'size' => 8,
                'color' => '808080',
                'name' => 'Arial'
            ];
            $header->addText(str_repeat('- ', 50), $lineStyle, $centerAlignment);
            
            if (strip_tags($content) !== $content) {
                try {
                    Html::addHtml($section, $content, false, false);
                } catch (\Exception $htmlError) {
                    Log::warning('Erro ao processar HTML, usando texto simples: ' . $htmlError->getMessage());
                    $section->addText(strip_tags($content));
                }
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

            $directory = dirname($filePath);
            if (!file_exists($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new \Exception('Não foi possível criar o diretório: ' . $directory);
                }
            }
            
            if (!is_writable($directory)) {
                throw new \Exception('Diretório não tem permissão de escrita: ' . $directory);
            }
            
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($filePath);
            
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