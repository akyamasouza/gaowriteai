import './bootstrap';
import '@fortawesome/fontawesome-free/js/all';

document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.querySelector('.prompt-input');

    if (textarea) {
        function autoResize() {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }

        textarea.addEventListener('input', autoResize);
        autoResize();
    }

    // File upload functionality
    const fileBtn = document.getElementById('fileBtn');
    const imageBtn = document.getElementById('imageBtn');
    const fileInput = document.getElementById('fileInput');
    const imageInput = document.getElementById('imageInput');
    const attachmentsContainer = document.getElementById('attachments');
    let attachedFiles = [];

    if (fileBtn && fileInput) {
        fileBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', handleFileSelect);
    }

    if (imageBtn && imageInput) {
        imageBtn.addEventListener('click', () => imageInput.click());
        imageInput.addEventListener('change', handleFileSelect);
    }

    function handleFileSelect(e) {
        const files = Array.from(e.target.files);
        files.forEach(addAttachment);
        e.target.value = '';
    }

    function addAttachment(file) {
        attachedFiles.push(file);

        const attachmentDiv = document.createElement('div');
        attachmentDiv.className = 'attachment';

        const isImage = file.type.startsWith('image/');

        if (isImage) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            attachmentDiv.appendChild(img);
        }

        const fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';

        const fileName = document.createElement('div');
        fileName.className = 'file-name';
        fileName.textContent = file.name;

        const fileSize = document.createElement('div');
        fileSize.className = 'file-size';
        fileSize.textContent = formatFileSize(file.size);

        fileInfo.appendChild(fileName);
        fileInfo.appendChild(fileSize);
        attachmentDiv.appendChild(fileInfo);

        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-btn';
        removeBtn.innerHTML = '×';
        removeBtn.onclick = () => removeAttachment(attachmentDiv, file);
        attachmentDiv.appendChild(removeBtn);

        attachmentsContainer.appendChild(attachmentDiv);
    }

    function removeAttachment(element, file) {
        attachedFiles = attachedFiles.filter(f => f !== file);
        element.remove();
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Chat functionality
    const chatForm = document.getElementById('chatForm');
    const messagesContainer = document.getElementById('messages');

    if (chatForm && messagesContainer) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const input = chatForm.querySelector('.prompt-input');
            const message = input.value.trim();

            if (message || attachedFiles.length > 0) {
                let userMessage = message;
                if (attachedFiles.length > 0) {
                    const fileNames = attachedFiles.map(f => f.name).join(', ');
                    userMessage += (message ? '\n' : '') + `📎 Anexos: ${fileNames}`;
                }
                addMessage(userMessage, 'user');

                input.value = '';
                input.style.height = 'auto';
                attachedFiles = [];
                attachmentsContainer.innerHTML = '';

                sendToOllama(message);
            }
        });

        async function sendToOllama(message) {
            let loadingDiv;
            
            try {
                loadingDiv = addMessage('Pensando...', 'assistant');

                const response = await fetch('/api/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        message: message,
                        model: 'gpt-oss:20b'
                    })
                });

                // Verifica se a resposta HTTP é válida
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status} - ${response.statusText}`);
                }

                // Verifica o Content-Type
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const textResponse = await response.text();
                    console.error('Resposta não é JSON:', textResponse);
                    throw new Error('Resposta do servidor não é JSON válida');
                }

                const data = await response.json();

                if (loadingDiv && loadingDiv.parentNode) {
                    loadingDiv.remove();
                }

                if (data.success) {
                    if (data.type === 'document') {
                        addMessage(data.message, 'assistant');
                        // Download automático
                        downloadDocument(data.downloadUrl);
                    } else {
                        addMessage(data.response, 'assistant');
                    }
                } else {
                    const errorMessage = data.error + (data.details ? ` - ${data.details}` : '');
                    addMessage('Erro: ' + errorMessage, 'assistant');
                }

            } catch (error) {
                console.error('Erro na requisição:', error);
                
                if (loadingDiv && loadingDiv.parentNode) {
                    loadingDiv.remove();
                }

                let errorMessage;
                if (error.name === 'SyntaxError') {
                    errorMessage = 'Erro de formato na resposta do servidor';
                } else if (error.message.includes('HTTP')) {
                    errorMessage = `Erro de servidor: ${error.message}`;
                } else if (error.message.includes('Failed to fetch')) {
                    errorMessage = 'Erro de conexão com o servidor';
                } else {
                    errorMessage = error.message;
                }

                addMessage('Erro: ' + errorMessage, 'assistant');
            }
        }

        function addMessage(text, sender) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;
            messageDiv.textContent = text;
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            return messageDiv;
        }

        // Torna as funções globalmente disponíveis
        window.downloadDocument = function(url) {
            const link = document.createElement('a');
            link.href = url;
            link.download = '';
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };

        window.previewDocument = function(url) {
            window.open(url, '_blank');
        };
    }
});