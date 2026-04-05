/* QS Chatbot — v1.0 */
(function () {
    'use strict';

    const cfg = window.QsChatbot || {};

    const form     = document.getElementById('qs-chatbot-form');
    const input    = document.getElementById('qs-chatbot-input');
    const messages = document.getElementById('qs-chatbot-messages');
    const typing   = document.getElementById('qs-chatbot-typing');

    if (!form || !input || !messages) return;

    // Session ID — persiste en sessionStorage para memoria por tab
    const sessionId = (function () {
        const key = 'qs_chat_session';
        let id = sessionStorage.getItem(key);
        if (!id) {
            id = 'anon_' + Math.random().toString(36).slice(2) + '_' + Date.now();
            sessionStorage.setItem(key, id);
        }
        return id;
    })();

    function appendMessage(text, role) {
        const div = document.createElement('div');
        div.className = 'qs-chatbot-msg qs-chatbot-msg--' + role;
        const p = document.createElement('p');
        p.textContent = text;
        div.appendChild(p);
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return div;
    }

    function setTyping(visible) {
        typing.style.display = visible ? 'flex' : 'none';
        if (visible) messages.scrollTop = messages.scrollHeight;
    }

    function setDisabled(disabled) {
        input.disabled = disabled;
        form.querySelector('.qs-chatbot-send').disabled = disabled;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const text = input.value.trim();
        if (!text) return;

        appendMessage(text, 'user');
        input.value = '';
        setDisabled(true);
        setTyping(true);

        try {
            const res = await fetch(cfg.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': cfg.nonce || '',
                },
                body: JSON.stringify({ message: text, session_id: sessionId }),
            });

            const data = await res.json();

            setTyping(false);

            if (!res.ok || !data.success) {
                const errText = data.message || cfg.errorMsg || 'Error al conectar.';
                appendMessage(errText, 'error');
            } else {
                appendMessage(data.response || '...', 'bot');
            }
        } catch (err) {
            setTyping(false);
            appendMessage(cfg.errorMsg || 'Error de conexión. Intenta de nuevo.', 'error');
        } finally {
            setDisabled(false);
            input.focus();
        }
    });

    // Enter = submit, Shift+Enter = nueva línea (solo por si se cambia a textarea)
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
    });
})();
