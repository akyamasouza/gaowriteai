<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GaoWrite AI</title>
    @vite(['resources/css/app.css', 'resources/css/style.css', 'resources/js/app.js'])
</head>
<body>
    <div>
        <img class="w-48" src="{{ asset('images/rondolandia.png') }}" alt="GaoTech Logo">
    </div>
    <div class="chat-container">
        <div class="messages" id="messages"></div>
    </div>
    <form class="input-container" id="chatForm">
        <div class="attachments" id="attachments"></div>
        <textarea
            class="prompt-input"
            placeholder="Digite sua mensagem aqui..."
            rows="1"
        ></textarea>
        <div class="input-options">
            <input type="file" id="fileInput" accept="*/*" style="display: none;">
            <button type="button" class="option-btn" title="Anexar arquivo" id="fileBtn">
                <i class="fas fa-paperclip"></i>
            </button>
            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </form>

    <footer class="py-4">
        <div class="max-w-4xl mx-auto px-4">
            <div class="flex items-center justify-center gap-2 text-gray-600">
                <span class="text-sm text-white">Powered by</span>
                <img class="Logo" src="{{ asset('images/LOGO-GAOTECH.svg') }}" alt="GaoTech" class="h-6">
            </div>
        </div>
    </footer>
</body>
</html>