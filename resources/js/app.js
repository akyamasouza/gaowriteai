import './bootstrap';
import '@fortawesome/fontawesome-free/js/all';

// Auto-resize textarea
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.querySelector('.prompt-input');

    if (textarea) {
        // Auto-resize function
        function autoResize() {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }

        // Listen for input events
        textarea.addEventListener('input', autoResize);

        // Initial resize
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
        e.target.value = ''; // Reset input
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
                // Add user message with attachments info
                let userMessage = message;
                if (attachedFiles.length > 0) {
                    const fileNames = attachedFiles.map(f => f.name).join(', ');
                    userMessage += (message ? '\n' : '') + `📎 Anexos: ${fileNames}`;
                }
                addMessage(userMessage, 'user');

                // Clear input and attachments
                input.value = '';
                input.style.height = 'auto';
                attachedFiles = [];
                attachmentsContainer.innerHTML = '';

                // Send to Ollama API
                sendToOllama(message);
            }
        });

        async function sendToOllama(message) {
            try {
                // Add loading message
                const loadingDiv = addMessage('Pensando...', 'assistant');

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

                const data = await response.json();

                // Remove loading message
                loadingDiv.remove();

                if (data.success) {
                    addMessage(data.response, 'assistant');
                } else {
                    addMessage('Erro: ' + data.error, 'assistant');
                }
            } catch (error) {
                // Remove loading message if exists
                const loadingMessages = messagesContainer.querySelectorAll('.message.assistant');
                const lastMessage = loadingMessages[loadingMessages.length - 1];
                if (lastMessage && lastMessage.textContent === 'Pensando...') {
                    lastMessage.remove();
                }

                addMessage('Erro de conexão: ' + error.message, 'assistant');
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
    }
});
