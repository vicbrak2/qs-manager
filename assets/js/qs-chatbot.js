/* QS Chatbot — v1.1 */
(function () {
    'use strict';

    const cfg = window.QsChatbot || {};
    const roots = document.querySelectorAll('[data-qs-chatbot]');
    let sharedSessionId = null;

    if (!roots.length) return;

    roots.forEach(initChatbot);

    function initChatbot(root) {
        const form = root.querySelector('[data-qs-chatbot-form]');
        const input = root.querySelector('[data-qs-chatbot-input]');
        const messages = root.querySelector('[data-qs-chatbot-messages]');
        const typing = root.querySelector('[data-qs-chatbot-typing]');
        const sendButton = form ? form.querySelector('.qs-chatbot-send') : null;
        const sessionId = getOrCreateSessionId();

        if (!form || !input || !messages || !sendButton) return;

        function appendMessage(content, role, meta) {
            const div = document.createElement('div');
            div.className = 'qs-chatbot-msg qs-chatbot-msg--' + role;

            const bubble = document.createElement('div');
            bubble.className = 'qs-chatbot-bubble';

            if (role === 'bot' && meta && Array.isArray(meta.blocks) && meta.blocks.length > 0) {
                renderStructuredMessage(bubble, meta.blocks);
            } else {
                bubble.appendChild(buildParagraphBlock(typeof content === 'string' ? content : String(content || '')));
            }

            div.appendChild(bubble);

            if (role === 'bot' && meta && Number.isInteger(meta.turnIndex) && meta.turnIndex > 0) {
                div.appendChild(buildFeedback(meta.turnIndex, sessionId));
            }

            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;

            return div;
        }

        function setTyping(visible) {
            if (!typing) return;

            typing.style.display = visible ? 'flex' : 'none';

            if (visible) {
                messages.scrollTop = messages.scrollHeight;
            }
        }

        function setDisabled(disabled) {
            input.disabled = disabled;
            sendButton.disabled = disabled;
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
                    body: JSON.stringify({
                        message: text,
                        session_id: sessionId,
                    }),
                });

                const data = await res.json();

                setTyping(false);

                if (!res.ok || !data.success) {
                    appendMessage(data.message || cfg.errorMsg || 'Error al conectar.', 'error');
                    return;
                }

                appendMessage(data.response || '...', 'bot', {
                    blocks: Array.isArray(data.response_blocks) ? data.response_blocks : [],
                    turnIndex: Number.parseInt(data.turn_index, 10),
                });
            } catch (err) {
                setTyping(false);
                appendMessage(cfg.errorMsg || 'Error de conexión. Intenta de nuevo.', 'error');
            } finally {
                setDisabled(false);
                input.focus();
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    return;
                }

                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }
        });
    }

    function renderStructuredMessage(container, blocks) {
        blocks.forEach(function (block) {
            if (!block || typeof block !== 'object') return;

            if (block.type === 'list' && Array.isArray(block.items) && block.items.length > 0) {
                container.appendChild(buildListBlock(block.items));
                return;
            }

            if ((block.type === 'paragraph' || block.type === 'question') && typeof block.text === 'string' && block.text.trim() !== '') {
                container.appendChild(buildParagraphBlock(block.text, block.type));
            }
        });
    }

    function buildParagraphBlock(text, type) {
        const p = document.createElement('p');
        p.className = 'qs-chatbot-block qs-chatbot-block--' + (type || 'paragraph');
        appendRichText(p, text);
        return p;
    }

    function buildListBlock(items) {
        const ul = document.createElement('ul');
        ul.className = 'qs-chatbot-block qs-chatbot-block--list';

        items.forEach(function (item) {
            if (typeof item !== 'string' || item.trim() === '') return;

            const li = document.createElement('li');
            appendRichText(li, item);
            ul.appendChild(li);
        });

        return ul;
    }

    function appendRichText(element, text) {
        const parts = String(text || '').split(/(https?:\/\/[^\s]+)/g);

        parts.forEach(function (part) {
            if (!part) return;

            if (/^https?:\/\/[^\s]+$/i.test(part)) {
                const link = document.createElement('a');
                link.href = part;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = part;
                element.appendChild(link);
                return;
            }

            element.appendChild(document.createTextNode(part));
        });
    }

    function buildFeedback(turnIndex, sessionId) {
        const wrap = document.createElement('div');
        wrap.className = 'qs-chatbot-feedback';
        wrap.dataset.turnIndex = String(turnIndex);

        const label = document.createElement('span');
        label.className = 'qs-chatbot-feedback__label';
        label.textContent = '¿Te sirvio esta respuesta?';
        wrap.appendChild(label);

        const buttons = document.createElement('div');
        buttons.className = 'qs-chatbot-feedback__buttons';

        const goodBtn = feedbackButton('good', '👍');
        const badBtn = feedbackButton('bad', '👎');

        goodBtn.addEventListener('click', function () {
            submitFeedback(wrap, turnIndex, 'good', goodBtn, badBtn, sessionId);
        });

        badBtn.addEventListener('click', function () {
            submitFeedback(wrap, turnIndex, 'bad', goodBtn, badBtn, sessionId);
        });

        buttons.appendChild(goodBtn);
        buttons.appendChild(badBtn);
        wrap.appendChild(buttons);

        const status = document.createElement('span');
        status.className = 'qs-chatbot-feedback__status';
        wrap.appendChild(status);

        return wrap;
    }

    function feedbackButton(rating, text) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'qs-chatbot-feedback__btn';
        button.dataset.rating = rating;
        button.textContent = text;
        button.setAttribute('aria-label', rating === 'good' ? 'Respuesta util' : 'Respuesta no util');
        return button;
    }

    async function submitFeedback(wrap, turnIndex, rating, goodBtn, badBtn, sessionId) {
        if (!cfg.feedbackApiUrl || wrap.dataset.submitted === 'true') return;

        const status = wrap.querySelector('.qs-chatbot-feedback__status');
        const buttons = [goodBtn, badBtn];

        buttons.forEach(function (button) {
            button.disabled = true;
        });

        if (status) {
            status.textContent = 'Guardando...';
        }

        try {
            const res = await fetch(cfg.feedbackApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': cfg.nonce || '',
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    turn_index: turnIndex,
                    rating: rating,
                }),
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Feedback request failed.');
            }

            wrap.dataset.submitted = 'true';

            buttons.forEach(function (button) {
                button.classList.toggle('is-active', button.dataset.rating === rating);
            });

            if (status) {
                status.textContent = cfg.feedbackThanks || 'Gracias por tu feedback.';
            }
        } catch (err) {
            buttons.forEach(function (button) {
                button.disabled = false;
            });

            if (status) {
                status.textContent = cfg.feedbackError || 'No se pudo guardar tu feedback.';
            }
        }
    }

    function getOrCreateSessionId() {
        if (sharedSessionId) {
            return sharedSessionId;
        }

        const key = 'qs_chat_session';

        try {
            let id = window.sessionStorage.getItem(key);

            if (!id) {
                id = createSessionId();
                window.sessionStorage.setItem(key, id);
            }

            sharedSessionId = id;
            return sharedSessionId;
        } catch (err) {
            sharedSessionId = createSessionId();
            return sharedSessionId;
        }
    }

    function createSessionId() {
        return 'anon_' + Math.random().toString(36).slice(2) + '_' + Date.now();
    }
})();
