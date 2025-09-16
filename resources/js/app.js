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
});
