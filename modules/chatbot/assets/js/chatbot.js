document.addEventListener('DOMContentLoaded', function() {
    const chatbot = document.getElementById('aiutoma-chatbot');
    if (!chatbot) return;

    const toggleBtn = document.getElementById('aiutoma-chatbot-toggle');
    const header = document.getElementById('aiutoma-chatbot-header');
    const sendBtn = document.getElementById('aiutoma-chatbot-send');
    const promptInput = document.getElementById('aiutoma-chatbot-prompt');
    const messagesArea = document.getElementById('aiutoma-chatbot-messages');
    
    const isLiveMode = chatbot.getAttribute('data-live-mode') === '1';
    
    let sessionId = localStorage.getItem('aiutoma_chatbot_session_id');
    if (!sessionId) {
        sessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('aiutoma_chatbot_session_id', sessionId);
    }

    let chatHistory = [];
    try {
        const storedHistory = localStorage.getItem('aiutoma_chatbot_history');
        if (storedHistory) {
            chatHistory = JSON.parse(storedHistory);
        }
    } catch(e) {}

    function updateOperatorMode(isManual) {
        const disclaimer = document.getElementById('aiutoma-chatbot-disclaimer') || document.querySelector('.aiutoma-chatbot-disclaimer');
        const aiBadge = document.querySelector('.aiutoma-chatbot-ai-badge');
        
        if (isManual) {
            if (disclaimer) disclaimer.style.display = 'none';
            if (aiBadge) aiBadge.style.display = 'none';
            chatbot.setAttribute('data-operator-mode', '1');
        } else {
            if (disclaimer) disclaimer.style.display = '';
            if (aiBadge) aiBadge.style.display = '';
            chatbot.removeAttribute('data-operator-mode');
        }
    }

    function renderHistory() {
        if (chatHistory.length > 0) {
            const greeting = messagesArea.firstElementChild;
            messagesArea.innerHTML = '';
            if (greeting) {
                messagesArea.appendChild(greeting);
            }
            
            const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/;
            let hasEmail = false;

            chatHistory.forEach(msg => {
                const div = document.createElement('div');
                div.className = 'aiutoma-chatbot-msg aiutoma-chatbot-' + msg.role;
                
                const authorDiv = document.createElement('div');
                authorDiv.className = 'aiutoma-chatbot-author';
                let authorName = '';
                if (msg.role === 'ai') {
                    authorName = msg.author || aiutomaChatbotData.chatbotName || 'AI Bot';
                } else if (msg.role === 'sys') {
                    authorName = 'System';
                } else {
                    let localUserName = localStorage.getItem('aiutoma_chatbot_user_name');
                    if (msg.author && msg.author !== 'You' && msg.author !== 'Visitor') {
                        authorName = msg.author;
                    } else {
                        authorName = localUserName || aiutomaChatbotData.userName || 'You';
                    }
                }
                authorDiv.innerText = authorName;
                div.appendChild(authorDiv);

                const contentDiv = document.createElement('div');
                contentDiv.className = 'aiutoma-msg-content';
                
                if (msg.role === 'ai' || msg.role === 'sys') {
                    contentDiv.innerHTML = msg.text;
                } else {
                    contentDiv.innerText = msg.text;
                    if (emailRegex.test(msg.text)) hasEmail = true;
                }
                div.appendChild(contentDiv);
                
                if (msg.role === 'ai' && 'speechSynthesis' in window) {
                    const speakBtn = document.createElement('button');
                    speakBtn.type = 'button';
                    speakBtn.className = 'aiutoma-chatbot-speak-btn';
                    speakBtn.innerHTML = '🔊';
                    speakBtn.title = 'Speak message';
                    speakBtn.addEventListener('click', function() {
                        window.speechSynthesis.cancel();
                        speakText(msg.text);
                    });
                    div.appendChild(speakBtn);
                }
                
                messagesArea.appendChild(div);
            });
            setTimeout(() => {
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }, 100);

            const hint = document.getElementById('aiutoma-chatbot-email-hint');
            if (hint) {
                const hasAiReply = chatHistory.some(m => m.role === 'ai');
                if (hasEmail) {
                    hint.style.display = 'none';
                } else if (hasAiReply) {
                    hint.style.display = 'block';
                }
            }
            
            const gdprNotice = document.getElementById('aiutoma-chatbot-gdpr-notice');
            if (gdprNotice) {
                gdprNotice.style.display = 'none';
            }

            let isManual = localStorage.getItem('aiutoma_chatbot_manual_mode') === '1';
            for (let i = chatHistory.length - 1; i >= 0; i--) {
                if (chatHistory[i].role === 'sys') {
                    const txt = (chatHistory[i].text || '').toLowerCase();
                    if (txt.includes('operator') && (txt.includes('joined') || txt.includes('human'))) {
                        isManual = true;
                        break;
                    } else if (txt.includes('left') || txt.includes('active again')) {
                        isManual = false;
                        break;
                    }
                }
            }
            updateOperatorMode(isManual);
        } else {
            updateOperatorMode(false);
        }
    }
    renderHistory();

    function toggleChat() {
        if (chatbot.classList.contains('aiutoma-chatbot-closed')) {
            chatbot.classList.remove('aiutoma-chatbot-closed');
            toggleBtn.innerHTML = '<span class="dashicons dashicons-arrow-down-alt2"></span>';
            if (typeof pollServer === 'function') {
                pollServer();
            }
            setTimeout(() => {
                promptInput.focus();
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }, 100);
        } else {
            chatbot.classList.add('aiutoma-chatbot-closed');
            toggleBtn.innerHTML = '<span class="dashicons dashicons-arrow-up-alt2"></span>';
        }
    }

    let hasDragged = false;

    if (typeof jQuery !== 'undefined' && jQuery.ui) {
        jQuery('#aiutoma-chatbot').draggable({
            handle: '#aiutoma-chatbot-header',
            start: function() {
                hasDragged = true;
                jQuery(this).css({
                    bottom: 'auto',
                    right: 'auto',
                    transition: 'none'
                });
            },
            stop: function(event, ui) {
                setTimeout(() => hasDragged = false, 100);
                jQuery(this).css('transition', 'all 0.3s ease');
                
                let rect = chatbot.getBoundingClientRect();
                let ww = window.innerWidth;
                let wh = window.innerHeight;
                
                let savePos = {};
                if (rect.left > ww / 2) {
                    savePos.right = (ww - rect.right) + 'px';
                    savePos.left = 'auto';
                } else {
                    savePos.left = rect.left + 'px';
                    savePos.right = 'auto';
                }
                
                if (rect.top > wh / 2) {
                    savePos.bottom = (wh - rect.bottom) + 'px';
                    savePos.top = 'auto';
                } else {
                    savePos.top = rect.top + 'px';
                    savePos.bottom = 'auto';
                }
                
                chatbot.style.left = savePos.left;
                chatbot.style.right = savePos.right;
                chatbot.style.top = savePos.top;
                chatbot.style.bottom = savePos.bottom;
                
                localStorage.setItem('aiutoma_chatbot_pos', JSON.stringify(savePos));
            }
        }).resizable({
            minHeight: 300,
            minWidth: 250,
            handles: 'n, e, s, w, ne, se, sw, nw',
            start: function() {
                jQuery(this).css('transition', 'none');
            },
            stop: function(event, ui) {
                jQuery(this).css('transition', 'all 0.3s ease');
                localStorage.setItem('aiutoma_chatbot_size', JSON.stringify({
                    width: ui.size.width + 'px',
                    height: ui.size.height + 'px'
                }));
            }
        });
    }

    const savedPos = localStorage.getItem('aiutoma_chatbot_pos');
    if (savedPos) {
        try {
            const pos = JSON.parse(savedPos);
            if (pos.left && pos.left !== 'auto') chatbot.style.left = pos.left;
            if (pos.right && pos.right !== 'auto') chatbot.style.right = pos.right;
            if (pos.top && pos.top !== 'auto') chatbot.style.top = pos.top;
            if (pos.bottom && pos.bottom !== 'auto') chatbot.style.bottom = pos.bottom;
        } catch(e) {}
    }

    const savedSize = localStorage.getItem('aiutoma_chatbot_size');
    if (savedSize) {
        try {
            const size = JSON.parse(savedSize);
            if (size.width) chatbot.style.width = size.width;
            if (size.height) chatbot.style.height = size.height;
        } catch(e) {}
    }

    header.addEventListener('click', function(e) {
        if (hasDragged) return;
        if (chatbot.classList.contains('aiutoma-chatbot-closed')) {
            toggleChat();
        } else if (e.target.closest('#aiutoma-chatbot-toggle')) {
            toggleChat();
        }
    });

    const emailSubmitBtn = document.getElementById('aiutoma-chatbot-email-submit');
    const nameInput = document.getElementById('aiutoma-chatbot-name-input');
    const emailInput = document.getElementById('aiutoma-chatbot-email-input');
    if (emailSubmitBtn && emailInput) {
        emailSubmitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const consentBox = document.getElementById('aiutoma-chatbot-gdpr-consent');
            const noticeBox = document.getElementById('aiutoma-chatbot-gdpr-notice');
            const isNoticeVisible = noticeBox && noticeBox.style.display !== 'none';
            
            if (isNoticeVisible && consentBox && !consentBox.checked) {
                if (consentBox.parentElement) consentBox.parentElement.classList.add('aiutoma-gdpr-error');
                consentBox.focus();
                return;
            } else if (consentBox) {
                if (consentBox.parentElement) consentBox.parentElement.classList.remove('aiutoma-gdpr-error');
            }

            const emailVal = emailInput.value.trim();
            const nameVal = nameInput ? nameInput.value.trim() : '';
            if (emailVal && /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/.test(emailVal)) {
                if (nameVal) {
                    localStorage.setItem('aiutoma_chatbot_user_name', nameVal);
                    promptInput.value = "My name is " + nameVal + " and my email is " + emailVal;
                } else {
                    promptInput.value = "My email is " + emailVal;
                }
                sendMessage();
            }
        });
    }

    const resetBtn = document.getElementById('aiutoma-chatbot-reset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (confirm(aiutomaChatbotData.resetConfirm || 'Are you sure you want to start a new chat?')) {
                localStorage.removeItem('aiutoma_chatbot_session_id');
                localStorage.removeItem('aiutoma_chatbot_history');
                sessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem('aiutoma_chatbot_session_id', sessionId);
                chatHistory = [];
                const greeting = messagesArea.firstElementChild;
                messagesArea.innerHTML = '';
                if (greeting) {
                    messagesArea.appendChild(greeting);
                }
                const noticeBox = document.getElementById('aiutoma-chatbot-gdpr-notice');
                if (noticeBox) {
                    noticeBox.style.display = 'block';
                }
                const consentBox = document.getElementById('aiutoma-chatbot-gdpr-consent');
                if (consentBox) {
                    consentBox.checked = false;
                    if (consentBox.parentElement) {
                        consentBox.parentElement.classList.remove('aiutoma-gdpr-error');
                    }
                }
            }
        });
    }

    function addMessage(text, role, save = true, author = null) {
        const div = document.createElement('div');
        div.className = 'aiutoma-chatbot-msg aiutoma-chatbot-' + role;
        
        const authorDiv = document.createElement('div');
        authorDiv.className = 'aiutoma-chatbot-author';
        let authorName = '';
        if (role === 'ai') {
            authorName = author || aiutomaChatbotData.chatbotName || 'AI Bot';
        } else if (role === 'sys') {
            authorName = 'System';
        } else {
            let localUserName = localStorage.getItem('aiutoma_chatbot_user_name');
            if (author && author !== 'You' && author !== 'Visitor') {
                authorName = author;
            } else {
                authorName = localUserName || aiutomaChatbotData.userName || 'You';
            }
        }
        authorDiv.innerText = authorName;
        div.appendChild(authorDiv);

        const contentDiv = document.createElement('div');
        contentDiv.className = 'aiutoma-msg-content';
        
        if (role === 'ai' || role === 'sys') {
            contentDiv.innerHTML = text;
        } else {
            contentDiv.innerText = text;
        }
        div.appendChild(contentDiv);

        if (role === 'sys') {
            const lower = (text || '').toLowerCase();
            if (lower.includes('operator') && (lower.includes('joined') || lower.includes('human'))) {
                updateOperatorMode(true);
                localStorage.setItem('aiutoma_chatbot_manual_mode', '1');
            } else if (lower.includes('left') || lower.includes('active again')) {
                updateOperatorMode(false);
                localStorage.setItem('aiutoma_chatbot_manual_mode', '0');
            }
        }
        
        if (role === 'ai' && 'speechSynthesis' in window) {
            const speakBtn = document.createElement('button');
            speakBtn.type = 'button';
            speakBtn.className = 'aiutoma-chatbot-speak-btn';
            speakBtn.innerHTML = '🔊';
            speakBtn.title = 'Speak message';
            speakBtn.addEventListener('click', function() {
                window.speechSynthesis.cancel();
                speakText(text);
            });
            div.appendChild(speakBtn);
        }
        messagesArea.appendChild(div);
        messagesArea.scrollTop = messagesArea.scrollHeight;
        
        if (save) {
            try {
                const stored = localStorage.getItem('aiutoma_chatbot_history');
                if (stored) {
                    chatHistory = JSON.parse(stored);
                }
            } catch(e) {}
            chatHistory.push({ text: text, role: role, author: authorName });
            localStorage.setItem('aiutoma_chatbot_history', JSON.stringify(chatHistory));
        }
        
        return div;
    }

    const micBtn = document.getElementById('aiutoma-chatbot-mic');
    
    let recognition = null;
    let isListening = false;
    
    if (isLiveMode && ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        
        recognition.onstart = function() {
            isListening = true;
            if (micBtn) {
                micBtn.style.color = '#d63638';
                micBtn.style.opacity = '0.5';
            }
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
        };
        
        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            promptInput.value = transcript;
            setTimeout(() => {
                sendMessage();
            }, 100);
        };
        
        recognition.onend = function() {
            isListening = false;
            if (micBtn) {
                micBtn.style.color = '';
                micBtn.style.opacity = '1';
            }
        };
        
        if (micBtn) {
            micBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (isListening) {
                    recognition.stop();
                } else {
                    recognition.start();
                }
            });
        }
    } else if (micBtn) {
        micBtn.style.display = 'none';
    }
    
    function speakText(text) {
        if (isLiveMode && 'speechSynthesis' in window) {
            setTimeout(() => {
                let cleanText = text.replace(/<[^>]+>/g, ' ').replace(/[#*_~\[\]()]/g, '');
                const utterance = new SpeechSynthesisUtterance(cleanText);
                window.speechSynthesis.speak(utterance);
            }, 100);
        }
    }

    const consentBox = document.getElementById('aiutoma-chatbot-gdpr-consent');
    if (consentBox) {
        consentBox.addEventListener('change', function() {
            if (consentBox.checked && consentBox.parentElement) {
                consentBox.parentElement.classList.remove('aiutoma-gdpr-error');
                consentBox.parentElement.style.color = '';
            }
        });
    }

    let messageQueue = [];
    let isProcessing = false;

    function sendMessage() {
        const text = promptInput.value.trim();
        if (!text) return;
        
        const consentBox = document.getElementById('aiutoma-chatbot-gdpr-consent');
        const noticeBox = document.getElementById('aiutoma-chatbot-gdpr-notice');
        const isNoticeVisible = noticeBox && noticeBox.style.display !== 'none';
        
        if (isNoticeVisible && consentBox && !consentBox.checked) {
            if (consentBox.parentElement) consentBox.parentElement.classList.add('aiutoma-gdpr-error');
            consentBox.focus();
            return;
        } else if (consentBox) {
            if (consentBox.parentElement) {
                consentBox.parentElement.classList.remove('aiutoma-gdpr-error');
                consentBox.parentElement.style.color = '';
            }
            if (noticeBox) noticeBox.style.display = 'none';
        }
        
        const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/;
        if (emailRegex.test(text)) {
            const hint = document.getElementById('aiutoma-chatbot-email-hint');
            if (hint) hint.style.display = 'none';
        }

        promptInput.value = '';

        addMessage(text, 'user');
        messageQueue.push(text);
        
        processQueue();
    }

    async function processQueue() {
        if (isProcessing || messageQueue.length === 0) return;
        
        isProcessing = true;
        const text = messageQueue.shift();
        
        const loadingDiv = addMessage('...', 'ai', false);

        try {
            let mainContentEl = document.querySelector('main, article, #content, .content, #main');
            let current_content = '';
            if (mainContentEl) {
                current_content = mainContentEl.innerHTML.substring(0, 150000);
            } else {
                current_content = document.body.innerHTML.substring(0, 150000);
            }

            let formsContext = '';
            document.querySelectorAll('form').forEach((f, idx) => {
                if (f.id === 'aiutoma-chatbot-form') return;
                formsContext += `[Frontend Form ${idx + 1} (${f.id || f.className || 'unnamed'})]\n`;
                f.querySelectorAll('input, select, textarea').forEach(input => {
                    if (input.type === 'hidden' || input.type === 'submit' || input.type === 'button') return;
                    let name = input.name || input.id;
                    if (!name) return;
                    let type = input.tagName.toLowerCase() === 'select' ? 'select' : input.type;
                    let options = '';
                    if (type === 'select') {
                        options = ' Options: ' + Array.from(input.options).map(o => o.value).join(', ');
                    }
                    formsContext += `- ${name} (type: ${type})${options}\n`;
                });
                formsContext += '\n';
            });

            const hp = document.getElementById('aiutoma-chatbot-hp');
            const reqData = {
                prompt: text,
                session_id: sessionId,
                current_url: window.location.href,
                current_title: document.title,
                current_content: current_content,
                current_forms: formsContext,
                current_post_id: aiutomaChatbotData.post_id,
                aiutoma_hp: hp ? hp.value : ''
            };
            if (aiutomaChatbotData.debugMode) {
                console.debug('Aiutoma Chatbot: Sending request', { url: aiutomaChatbotData.rest_url, data: reqData });
            }

            const response = await fetch(aiutomaChatbotData.rest_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiutomaChatbotData.nonce
                },
                body: JSON.stringify(reqData)
            });

            const data = await response.json();
            if (aiutomaChatbotData.debugMode) {
                console.debug('Aiutoma Chatbot: Received response', data);
            }
            
            messagesArea.removeChild(loadingDiv);
            
            if (data.date_gmt) {
                lastPollTime = data.date_gmt;
            }
            
            if (typeof data.manual_mode !== 'undefined') {
                updateOperatorMode(data.manual_mode);
                localStorage.setItem('aiutoma_chatbot_manual_mode', data.manual_mode ? '1' : '0');
            }

            if (data.success && (data.reply || data.manual_mode)) {
                if (data.reply) {
                    addMessage(data.reply, 'ai');
                }
                
                const hint = document.getElementById('aiutoma-chatbot-email-hint');
                if (hint) {
                    const hasEmail = chatHistory.some(m => m.role === 'user' && /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/.test(m.text));
                    if (!hasEmail) {
                        hint.style.display = 'block';
                    }
                }
                
                if (data.frontend_actions && data.frontend_actions.length > 0) {
                    data.frontend_actions.forEach(action => {
                        if (action.type === 'fill_form') {
                            try {
                                const field = document.querySelector(`[name="${action.fieldName}"]`) || document.getElementById(action.fieldName);
                                if (field) {
                                    field.value = action.fieldValue;
                                    field.dispatchEvent(new Event('change', { bubbles: true }));
                                    field.dispatchEvent(new Event('input', { bubbles: true }));
                                    field.style.boxShadow = '0 0 10px #4ade80';
                                    setTimeout(() => field.style.boxShadow = '', 2000);
                                }
                            } catch(err) {
                                console.error('Form fill error:', err);
                            }
                        } else if (action.type === 'show_email_form') {
                            const emailHint = document.getElementById('aiutoma-chatbot-email-hint');
                            const userHasEmail = chatHistory.some(m => m.role === 'user' && /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/.test(m.text));
                            if (emailHint && !userHasEmail) {
                                emailHint.style.display = 'block';
                                setTimeout(() => {
                                    messagesArea.scrollTop = messagesArea.scrollHeight;
                                }, 100);
                            }
                        } else if (action.type === 'elementor_insert' && window.elementor) {
                            try {
                                let section = {
                                    id: Math.random().toString(36).substr(2, 7),
                                    elType: 'section',
                                    settings: {},
                                    elements: [{
                                        id: Math.random().toString(36).substr(2, 7),
                                        elType: 'column',
                                        settings: { _column_size: 100 },
                                        elements: [{
                                            id: Math.random().toString(36).substr(2, 7),
                                            elType: 'widget',
                                            widgetType: action.widgetType,
                                            settings: action.widgetData,
                                            elements: []
                                        }]
                                    }]
                                };
                                if (window.$e && window.$e.run) {
                                    window.$e.run('document/elements/create', {
                                        model: section,
                                        container: window.elementor.getPreviewContainer()
                                    });
                                } else {
                                    window.elementor.getPreviewView().addChildModel(section);
                                }
                            } catch(err) {
                                console.error('Elementor insert error:', err);
                            }
                        } else if (action.type === 'gutenberg_insert' && window.wp && wp.data && wp.data.dispatch('core/block-editor')) {
                            try {
                                const blocks = wp.blocks.parse(action.blockHTML);
                                wp.data.dispatch('core/block-editor').insertBlocks(blocks);
                            } catch(err) {
                                console.error('Gutenberg insert error:', err);
                            }
                        }
                    });
                }
            } else {
                addMessage(data.message || 'Error occurred.', 'sys');
            }
        } catch (e) {
            messagesArea.removeChild(loadingDiv);
            addMessage('Network error occurred.', 'sys');
        }

        isProcessing = false;
        if (messageQueue.length > 0) {
            setTimeout(processQueue, 1000);
        } else {
            promptInput.focus();
        }
    }

    sendBtn.addEventListener('click', sendMessage);
    promptInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    let lastPollTime = new Date().toISOString().replace('T', ' ').substring(0, 19);

    async function pollServer() {
        if (!sessionId) return;
        const pollUrl = aiutomaChatbotData.rest_url + '/poll';
        try {
            const response = await fetch(pollUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiutomaChatbotData.nonce
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    last_time: lastPollTime
                })
            });
            const data = await response.json();
            if (data.success) {
                if (typeof data.manual_mode !== 'undefined') {
                    updateOperatorMode(data.manual_mode);
                    localStorage.setItem('aiutoma_chatbot_manual_mode', data.manual_mode ? '1' : '0');
                }
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        try {
                            const stored = localStorage.getItem('aiutoma_chatbot_history');
                            if (stored) {
                                chatHistory = JSON.parse(stored);
                            }
                        } catch(e) {}

                        const isDuplicate = chatHistory.some(m => m.role === msg.role && m.text === msg.text);
                                                
                        if (!isDuplicate) {
                            addMessage(msg.text, msg.role, true, msg.author);
                            if (msg.role === 'ai') {
                                const hint = document.getElementById('aiutoma-chatbot-email-hint');
                                if (hint) {
                                    const hasEmail = chatHistory.some(m => m.role === 'user' && /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/.test(m.text));
                                    if (!hasEmail) {
                                        hint.style.display = 'block';
                                    }
                                }
                            }
                        }
                        lastPollTime = msg.date_gmt;
                    });
                }
            }
        } catch(e) {}
    }

    setInterval(pollServer, 5000);

});
