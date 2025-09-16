<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GaoWrite AI</title>
    @vite(['resources/css/app.css', 'resources/css/style.css', 'resources/js/app.js'])
</head>
<body>
    <div class="logo">
        <img src="{{ asset('images/LOGO-GAOTECH.svg') }}" alt="GaoTech Logo">
    </div>
        <form class="input-container">
            <textarea
                class="prompt-input"
                placeholder="Digite seu prompt aqui..."
                rows="1"
            ></textarea>
            <div class="input-options">
                <button type="button" class="option-btn" title="Anexar arquivo">
                    <i class="fas fa-paperclip"></i>
                </button>
                <button type="button" class="option-btn" title="Adicionar imagem">
                    <i class="fas fa-image"></i>
                </button>
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
</body>
</html>