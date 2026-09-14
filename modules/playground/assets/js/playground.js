function initPlaygroundSpeech() {
    // Speech to Text for AI Playground
    const speechBtn = document.getElementById('aiutoma-speech-to-text');
    const promptEl = document.getElementById('aiutoma-playground-prompt');
    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition || window.mozSpeechRecognition || window.msSpeechRecognition;

    if (speechBtn && promptEl && SpeechRec) {
        speechBtn.style.display = 'block';
        const recognition = new SpeechRec();
        recognition.continuous = false;
        recognition.interimResults = false;

        let isRecording = false;

        recognition.onstart = function () {
            isRecording = true;
            speechBtn.innerHTML = '<span class="dashicons dashicons-microphone" style="color: #d63638;"></span>';
        };

        recognition.onresult = function (event) {
            const transcript = event.results[0][0].transcript;
            if (promptEl.value) {
                promptEl.value += ' ' + transcript;
            } else {
                promptEl.value = transcript;
            }
        };

        recognition.onerror = function (event) {
            console.error('Speech recognition error', event.error);
            isRecording = false;
            speechBtn.innerHTML = '<span class="dashicons dashicons-microphone"></span>';

            if (event.error === 'not-allowed') {
                alert('Microphone access was denied. Please allow microphone access to use speech-to-text.');
            } else if (navigator.userAgent.toLowerCase().includes('firefox')) {
                alert('Speech recognition failed. Note: Firefox desktop often requires third-party extensions or specific OS setups for speech recognition to function even when enabled in about:config.');
            } else {
                alert('Speech recognition error: ' + event.error);
            }
        };

        recognition.onend = function () {
            isRecording = false;
            speechBtn.innerHTML = '<span class="dashicons dashicons-microphone"></span>';
        };

        speechBtn.addEventListener('click', function () {
            if (isRecording) {
                recognition.stop();
            } else {
                try {
                    recognition.start();
                } catch (e) {
                    console.error('Failed to start speech recognition', e);
                    alert('Failed to start speech recognition. Your browser might not fully support this feature.');
                }
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPlaygroundSpeech);
} else {
    initPlaygroundSpeech();
}

document.addEventListener('DOMContentLoaded', function () {
    function escapeHtml(unsafe) {
        return (unsafe || '').toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }


    if (typeof jQuery !== 'undefined') {
        const chatWrapper = jQuery('#aiutoma-playground-chat-wrapper');
        if (chatWrapper.length) {
            if (jQuery.fn.resizable) {
                chatWrapper.resizable({
                    handles: 'se, s'
                });
            }
        }
    }

    window.aiutomaRestoreSession = function(id, skipConfirm = false) {
        if (!skipConfirm) {
            if (!confirm('Are you sure you want to replace current chat with this session?')) return;
            window.location.href = window.location.pathname + '?page=aiutoma&session_id=' + encodeURIComponent(id);
            return;
        }
        const btn = document.querySelector('.aiutoma-recent-prompt-btn[onclick*="' + id + '"]');
        let oldText = 'Restore';
        if (btn) {
            oldText = btn.innerText;
            btn.innerText = 'Loading...';
            btn.disabled = true;
        }
        fetch(window.aiutomaSettings.restUrl.replace('ai-chat', 'get-session') + '?id=' + id, {
            headers: { 'X-WP-Nonce': window.aiutomaSettings.nonceRest }
        }).then(r => r.json()).then(data => {
            if (data.is_full_state) {
                // Call restoreChatState defined below
                if (typeof restoreChatState === 'function') {
                    restoreChatState(data);
                } else {
                    const chatEl = document.getElementById('aiutoma-playground-chat');
                    if (chatEl && data.html) {
                        chatEl.innerHTML = data.html;
                        const wrapper = document.getElementById('aiutoma-playground-chat-wrapper');
                        if (wrapper) wrapper.classList.add('has-content');
                    }
                    if (data.conversation_id) window.aiutomaCurrentConversationId = data.conversation_id;
                    if (data.session_prompts) window.aiutomaSessionPrompts = data.session_prompts;
                }
                localStorage.setItem('aiutoma_chat_state', JSON.stringify(data));
                alert('Session restored successfully.');
            } else {
                alert('Invalid session data.');
            }
        }).catch(err => alert('Failed to load session')).finally(() => {
            if (btn) {
                btn.innerText = oldText;
                btn.disabled = false;
            }
        });
    };

    window.aiutomaExportSession = function(id) {
        fetch(window.aiutomaSettings.restUrl.replace('ai-chat', 'get-session') + '?id=' + id, {
            headers: { 'X-WP-Nonce': window.aiutomaSettings.nonceRest }
        }).then(r => r.json()).then(data => {
            if (data.is_full_state) {
                const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(data, null, 2));
                const downloadAnchorNode = document.createElement('a');
                downloadAnchorNode.setAttribute("href", dataStr);
                downloadAnchorNode.setAttribute("download", "aiutoma_session_" + id + ".json");
                document.body.appendChild(downloadAnchorNode);
                downloadAnchorNode.click();
                downloadAnchorNode.remove();
            } else {
                alert('Invalid session data.');
            }
        }).catch(err => alert('Failed to load session for export.'));
    };

    document.addEventListener('click', function (e) {
        const toggleDistractionFreeBtn = e.target.closest('.toggle-distraction-free');
        if (toggleDistractionFreeBtn) {
            e.preventDefault();
            const card = toggleDistractionFreeBtn.closest('.aiutoma-playground-card');
            if (card) {
                card.classList.toggle('distraction-free');
                const icon = toggleDistractionFreeBtn.querySelector('.dashicons');
                if (icon) {
                    icon.classList.toggle('dashicons-fullscreen-alt');
                    icon.classList.toggle('dashicons-fullscreen-exit-alt');
                }
            }
        }
    });


    // AI Playground chat
    const sendBtn = document.getElementById('aiutoma-playground-send');
    window.aiutomaAbortController = null;

    function restoreSendBtn() {
        if (!sendBtn) return;
        window.aiutomaAbortController = null;
        sendBtn.classList.add('button-primary');
        sendBtn.classList.remove('aiutoma-btn-danger');
        sendBtn.innerHTML = '<span class="dashicons dashicons-controls-play"></span>';
        sendBtn.title = 'Send';
        sendBtn.disabled = false;
    }

    const modelSelect = document.getElementById('aiutoma-playground-model');
    const fallbackContainer = document.getElementById('aiutoma-fallback-models-container');
    const fallbackModelsCheckbox = document.getElementById('aiutoma-fallback-models');

    if (modelSelect) {
        modelSelect.addEventListener('change', function () {
            if (fallbackContainer) {
                fallbackContainer.style.display = this.value === '' ? 'inline-block' : 'none';
            }
        });

        if (fallbackContainer) {
            fallbackContainer.style.display = modelSelect.value === '' ? 'inline-block' : 'none';
        }

        let modelsUrl = window.aiutomaSettings.restUrl.replace('ai-chat', 'ai-models');
        if (window.aiutomaSettings.hasDevExtension) {
            // Always enforce safe mode for models request to prevent it failing during 500 errors
            modelsUrl += (modelsUrl.includes('?') ? '&' : '?') + 'aiutoma_enforce_safe_mode=1';
        }

        fetch(modelsUrl, {
            headers: { 'X-WP-Nonce': window.aiutomaSettings.nonceRest },
            cache: 'no-cache'
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.models) {
                    if (typeof data.models === 'object' && Object.keys(data.models).length > 0) {
                        const firstVal = Object.values(data.models)[0];
                        if (typeof firstVal === 'object') {
                            Object.entries(data.models).forEach(([groupName, groupModels]) => {
                                const optgroup = document.createElement('optgroup');
                                optgroup.label = groupName;
                                Object.entries(groupModels).forEach(([id, name]) => {
                                    const opt = document.createElement('option');
                                    opt.value = id;
                                    opt.textContent = name;
                                    optgroup.appendChild(opt);
                                });
                                modelSelect.appendChild(optgroup);
                            });
                        } else {
                            Object.entries(data.models).forEach(([id, name]) => {
                                const opt = document.createElement('option');
                                opt.value = id;
                                opt.textContent = name;
                                modelSelect.appendChild(opt);
                            });
                        }
                    }

                    if (window.aiutomaSettings.preferredModel) {
                        const exists = Array.from(modelSelect.options).some(opt => opt.value === window.aiutomaSettings.preferredModel);
                        if (exists) {
                            modelSelect.value = window.aiutomaSettings.preferredModel;
                            if (fallbackContainer) {
                                fallbackContainer.style.display = 'none';
                            }
                        }
                    }

                    if (typeof jQuery !== 'undefined') {
                        if (jQuery.fn.selectWoo) {
                            jQuery(modelSelect).selectWoo({
                                width: '350px'
                            });
                            jQuery(modelSelect).on('select2:select', function (e) {
                                modelSelect.dispatchEvent(new Event('change'));
                            });
                        } else if (jQuery.fn.select2) {
                            jQuery(modelSelect).select2({
                                width: '350px'
                            });
                            jQuery(modelSelect).on('select2:select', function (e) {
                                modelSelect.dispatchEvent(new Event('change'));
                            });
                        }
                    }
                }
            })
            .catch(e => console.error('Failed to load AI models', e));
    }

    // Session prompts import/export
    window.aiutomaSessionPrompts = window.aiutomaSessionPrompts || [];
    window.aiutomaSessionMessages = window.aiutomaSessionMessages || [];
    window.aiutomaPromptQueue = window.aiutomaPromptQueue || [];
    window.aiutomaCurrentConversationId = window.aiutomaCurrentConversationId || null;

    const exportBtn = document.getElementById('aiutoma-export-session');
    if (exportBtn && window.aiutomaSessionPrompts.length > 0) {
        exportBtn.style.display = 'inline-block';
    }
    const importBtn = document.getElementById('aiutoma-import-session');
    const importFile = document.getElementById('aiutoma-import-file');
    const toggleSafeModeBtn = document.getElementById('aiutoma-toggle-safe-mode');
    let aiEnforceSafeMode = toggleSafeModeBtn && toggleSafeModeBtn.dataset.active === '1';

    const selectAllBtn = document.getElementById('aiutoma-abilities-select-all');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            document.querySelectorAll('.aiutoma-ability-checkbox').forEach(cb => cb.checked = true);
        });
    }

    const deselectAllBtn = document.getElementById('aiutoma-abilities-deselect-all');
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function () {
            document.querySelectorAll('.aiutoma-ability-checkbox').forEach(cb => cb.checked = false);
        });
    }

    const toggleAbilitiesBtn = document.getElementById('aiutoma-enable-abilities-toggle');
    if (toggleAbilitiesBtn) {
        toggleAbilitiesBtn.addEventListener('change', function () {
            const listWrap = document.getElementById('aiutoma-abilities-list-wrap');
            if (listWrap) {
                listWrap.style.opacity = this.checked ? '1' : '0.5';
                listWrap.style.pointerEvents = this.checked ? 'auto' : 'none';
            }
        });
    }

    const abilitiesContainer = document.getElementById('aiutoma-abilities-list-container');
    if (abilitiesContainer) {
        fetch(window.aiutomaSettings.restUrl.replace('ai-chat', 'get-abilities'), {
            headers: { 'X-WP-Nonce': window.aiutomaSettings.nonceRest },
            cache: 'no-cache',
            credentials: 'same-origin'
        }).then(res => res.json()).then(data => {
            if (data.success && data.abilities && data.abilities.length > 0) {
                let html = '';
                const groups = {};

                data.abilities.forEach(ability => {
                    let group = ability.group || 'Other';
                    if (!groups[group]) groups[group] = [];
                    groups[group].push(ability);
                });

                const sortedGroups = Object.keys(groups).sort((a, b) => {
                    if (a === 'WordPress Core') return -1;
                    if (b === 'WordPress Core') return 1;
                    if (a === 'Aiutoma Engine' || a === 'Aiutoma') return -1;
                    if (b === 'Aiutoma Engine' || b === 'Aiutoma') return 1;
                    return a.localeCompare(b);
                });

                for (const group of sortedGroups) {
                    const groupClass = 'aiutoma-group-' + group.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase() + '-checkbox';
                    html += '<div style="margin-bottom: 15px;">';
                    html += '<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-bottom: 5px;">';
                    html += '<h4 style="margin: 0; font-size: 13px;">' + escapeHtml(group) + '</h4>';
                    html += '<div>';
                    html += `<a href="#" style="font-size: 10px; text-decoration: none;" onclick="event.preventDefault(); document.querySelectorAll('.${groupClass}').forEach(cb => { cb.checked = true; cb.dispatchEvent(new Event('change')); }); return false;">Select All</a> | `;
                    html += `<a href="#" style="font-size: 10px; text-decoration: none;" onclick="event.preventDefault(); document.querySelectorAll('.${groupClass}').forEach(cb => { cb.checked = false; cb.dispatchEvent(new Event('change')); }); return false;">Deselect All</a>`;
                    html += '</div></div>';
                    html += '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px;">';

                    groups[group].forEach(ability => {
                        let friendlyName = ability.label;
                        if (!friendlyName) {
                            const parts = ability.name.split('/');
                            if (parts.length >= 2) {
                                friendlyName = parts[1].replace(/[-_]/g, ' ');
                                friendlyName = friendlyName.replace(/\b\w/g, l => l.toUpperCase());
                            } else {
                                friendlyName = ability.name.replace(/[-_]/g, ' ');
                                friendlyName = friendlyName.replace(/\b\w/g, l => l.toUpperCase());
                            }
                        }
                        let isChecked = (ability.name === 'aiutoma/abilities');

                        html += '<label style="font-size: 11px; display: flex; align-items: flex-start; cursor: pointer;">';
                        html += '<input type="checkbox" class="aiutoma-ability-checkbox ' + groupClass + '" value="' + escapeHtml(ability.name) + '" ' + (isChecked ? 'checked' : '') + ' style="margin-top: 1px; margin-right: 5px;">';
                        html += '<span style="line-height: 1.2;"><strong>' + escapeHtml(friendlyName) + '</strong><br><span style="color: #666; font-size: 10px;">' + escapeHtml(ability.description || '') + '</span></span>';
                        html += '</label>';
                    });

                    html += '</div></div>';
                }

                abilitiesContainer.innerHTML = html;

                // Bind token estimation to the new checkboxes
                document.querySelectorAll('.aiutoma-ability-checkbox').forEach(cb => cb.addEventListener('change', updateTokenEstimate));
                updateTokenEstimate();
            } else {
                abilitiesContainer.innerHTML = '<p style="margin:0;">No abilities registered.</p>';
            }
        }).catch(err => {
            abilitiesContainer.innerHTML = '<p style="margin:0; color: #d63638;">Failed to load abilities.</p>';
        });
    }

    const selectAllSkillsBtn = document.getElementById('aiutoma-skills-select-all');
    if (selectAllSkillsBtn) {
        selectAllSkillsBtn.addEventListener('click', function () {
            document.querySelectorAll('.aiutoma-skill-checkbox').forEach(cb => cb.checked = true);
            if (typeof updateTokenEstimate === 'function') updateTokenEstimate();
        });
    }

    const deselectAllSkillsBtn = document.getElementById('aiutoma-skills-deselect-all');
    if (deselectAllSkillsBtn) {
        deselectAllSkillsBtn.addEventListener('click', function () {
            document.querySelectorAll('.aiutoma-skill-checkbox').forEach(cb => cb.checked = false);
            if (typeof updateTokenEstimate === 'function') updateTokenEstimate();
        });
    }

    const toggleSkillsBtn = document.getElementById('aiutoma-enable-skills-toggle');
    if (toggleSkillsBtn) {
        toggleSkillsBtn.addEventListener('change', function () {
            const listWrap = document.getElementById('aiutoma-skills-list-wrap');
            if (listWrap) {
                listWrap.style.opacity = this.checked ? '1' : '0.5';
                listWrap.style.pointerEvents = this.checked ? 'auto' : 'none';
            }
            if (typeof updateTokenEstimate === 'function') updateTokenEstimate();
        });
    }

    const skillsContainer = document.getElementById('aiutoma-skills-list-container');
    if (skillsContainer) {
        fetch(window.aiutomaSettings.restUrl.replace('ai-chat', 'skills'), {
            headers: { 'X-WP-Nonce': window.aiutomaSettings.nonceRest },
            credentials: 'same-origin',
            cache: 'no-cache'
        }).then(res => res.json()).then(data => {
            if (data.success && data.skills && data.skills.length > 0) {
                let html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px;">';
                data.skills.forEach(skill => {
                    let friendlyName = skill.id.replace(/\.(txt|md)$/i, '').replace(/[-_]/g, ' ');
                    friendlyName = friendlyName.replace(/\b\w/g, l => l.toUpperCase());
                    let isChecked = false;
                    let tokens = Math.ceil((skill.content.trim().split(/\s+/).length || 0) / 0.75);
                    html += '<label style="font-size: 11px; display: flex; align-items: flex-start; cursor: pointer;">';
                    html += '<input type="checkbox" class="aiutoma-skill-checkbox" value="' + escapeHtml(skill.id) + '" data-tokens="' + tokens + '" ' + (isChecked ? 'checked' : '') + ' style="margin-top: 1px; margin-right: 5px;">';
                    html += '<span style="line-height: 1.2;"><strong>' + escapeHtml(friendlyName) + '</strong><br><span style="color: #666; font-size: 10px;">' + (skill.is_builtin ? 'Built-in Skill' : 'Custom Skill') + ' (~' + tokens + ' tokens)</span></span>';
                    html += '</label>';
                });
                html += '</div>';
                skillsContainer.innerHTML = html;
                document.querySelectorAll('.aiutoma-skill-checkbox').forEach(cb => cb.addEventListener('change', updateTokenEstimate));
                if (typeof updateTokenEstimate === 'function') updateTokenEstimate();
            } else {
                skillsContainer.innerHTML = '<p style="margin:0;">No skills found.</p>';
            }
        }).catch(err => {
            skillsContainer.innerHTML = '<p style="margin:0; color: #d63638;">Failed to load skills.</p>';
        });
    }

    if (aiEnforceSafeMode) {
        setTimeout(async () => {
            try {
                let testUrl = window.aiutomaSettings.restUrl.replace('ai-chat', 'ai-models');
                if (window.aiutomaSettings.hasDevExtension) {
                    testUrl += (testUrl.includes('?') ? '&' : '?') + 'aiutoma_enforce_safe_mode=1';
                }
                const backendRes = await fetch(testUrl, {
                    headers: { 'X-WP-Nonce': window.aiutomaSettings.nonceRest },
                    cache: 'no-cache'
                });
                const frontendRes = await fetch(window.aiutomaSettings.homeUrl);

                if (backendRes.ok && backendRes.status === 200 && frontendRes.ok && frontendRes.status === 200) {
                    aiEnforceSafeMode = false;
                    if (toggleSafeModeBtn) {
                        toggleSafeModeBtn.classList.remove('aiutoma-safe-mode-active');
                        toggleSafeModeBtn.dataset.active = "0";
                    }
                    if (document.getElementById('aiutoma-safemode-status')) {
                        document.getElementById('aiutoma-safemode-status').innerText = 'Native (All Plugins Active)';
                    }

                    const toggleUrl = window.aiutomaSettings.restUrl.replace('ai-chat', 'toggle-safe-mode') + (window.aiutomaSettings.hasDevExtension ? '?aiutoma_enforce_safe_mode=1' : '');
                    await fetch(toggleUrl, {
                        method: 'POST',
                        headers: { 'X-WP-Nonce': window.aiutomaSettings.nonceRest, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ force: 'disable' })
                    });

                    const chatEl = document.getElementById('aiutoma-playground-chat');
                    if (chatEl) {
                        chatEl.insertAdjacentHTML('beforeend', '<div class="aiutoma-msg-tool-result aiutoma-success" style="margin-bottom: 10px; padding: 10px; background: #eaf5ea; border-left: 4px solid #46b450; font-size: 13px;"><strong>System:</strong> Initial 500 error check passed. Safe Mode has been automatically disabled.</div>');
                        chatEl.scrollTop = chatEl.scrollHeight;
                    }
                }
            } catch (e) { }
        }, 1500);
    }

    if (toggleSafeModeBtn) {
        toggleSafeModeBtn.addEventListener('click', async function () {
            const originalTitle = toggleSafeModeBtn.title;
            toggleSafeModeBtn.title = 'Toggling...';
            toggleSafeModeBtn.style.opacity = '0.7';
            try {
                const isCurrentlyActive = toggleSafeModeBtn.dataset.active === '1';
                const actionForce = isCurrentlyActive ? 'disable' : 'enable';

                const toggleUrl = window.aiutomaSettings.restUrl.replace('ai-chat', 'toggle-safe-mode') + (window.aiutomaSettings.hasDevExtension ? '?aiutoma_enforce_safe_mode=1' : '');
                const response = await fetch(toggleUrl, {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': window.aiutomaSettings.nonceRest, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ force: actionForce })
                });
                const data = await response.json();
                if (data.success) {
                    if (data.safe_mode) {
                        aiEnforceSafeMode = true;
                        toggleSafeModeBtn.classList.add('aiutoma-safe-mode-active');
                        toggleSafeModeBtn.dataset.active = "1";
                        const statusEl = document.getElementById('aiutoma-safemode-status');
                        if (statusEl) statusEl.innerText = 'Strict Safe Mode Enforced (.aiutoma_safe)';
                    } else {
                        aiEnforceSafeMode = false;
                        toggleSafeModeBtn.classList.remove('aiutoma-safe-mode-active');
                        toggleSafeModeBtn.dataset.active = "0";
                        const statusEl = document.getElementById('aiutoma-safemode-status');
                        if (statusEl) statusEl.innerText = 'Native (All Plugins Active)';
                    }
                }
            } catch (err) {
                console.error('Error toggling safe mode', err);
            }
            toggleSafeModeBtn.title = originalTitle;
            toggleSafeModeBtn.style.opacity = '1';
        });
    }

    function getChatContext() {
        const modelSelect = document.getElementById('aiutoma-playground-model');
        const fallbackModelsCheckbox = document.getElementById('aiutoma-fallback-models-checkbox');
        const enableAbilitiesToggle = document.getElementById('aiutoma-enable-abilities-toggle');
        const enableSkillsToggle = document.getElementById('aiutoma-enable-skills-toggle');
        
        const selectedAbilities = [];
        if (enableAbilitiesToggle && enableAbilitiesToggle.checked) {
            document.querySelectorAll('.aiutoma-ability-checkbox:checked').forEach(cb => selectedAbilities.push(cb.value));
        }

        const selectedSkills = [];
        if (enableSkillsToggle && enableSkillsToggle.checked) {
            document.querySelectorAll('.aiutoma-skill-checkbox:checked').forEach(cb => selectedSkills.push(cb.value));
        }

        return {
            model: modelSelect ? modelSelect.value : '',
            fallback_models: fallbackModelsCheckbox ? fallbackModelsCheckbox.checked : false,
            enable_tools: enableAbilitiesToggle ? enableAbilitiesToggle.checked : true,
            enabled_abilities: selectedAbilities,
            enable_skills: enableSkillsToggle ? enableSkillsToggle.checked : false,
            enabled_skills: selectedSkills,
            system_info: document.getElementById('aiutoma-include-system-info') ? document.getElementById('aiutoma-include-system-info').checked : false,
            session_context: document.getElementById('aiutoma-session-context') ? document.getElementById('aiutoma-session-context').value : '',
            permanent_context: document.getElementById('aiutoma-permanent-context') ? document.getElementById('aiutoma-permanent-context').value : '',
            rag_types: window.aiutomaSessionRagChecked || []
        };
    }

    function simpleSanitizedMarkdown(text) {
        let safeText = text || '';
        
        const codeBlocks = [];
        safeText = safeText.replace(/```([\s\S]*?)```/g, function(match, p1) {
            codeBlocks.push(escapeHtml(p1));
            return `__AIUTOMA_CODE_BLOCK_${codeBlocks.length - 1}__`;
        });
        
        const inlineCode = [];
        safeText = safeText.replace(/`([^`]+)`/g, function(match, p1) {
            inlineCode.push(escapeHtml(p1));
            return `__AIUTOMA_INLINE_CODE_${inlineCode.length - 1}__`;
        });

        const doc = new DOMParser().parseFromString(safeText, 'text/html');
        const dangerousTags = ['script', 'style', 'iframe', 'object', 'embed', 'applet', 'meta', 'link', 'base', 'form'];
        dangerousTags.forEach(tag => {
            doc.body.querySelectorAll(tag).forEach(el => el.remove());
        });
        doc.body.querySelectorAll('*').forEach(el => {
            for (let i = el.attributes.length - 1; i >= 0; i--) {
                const attr = el.attributes[i];
                if (attr.name.toLowerCase().startsWith('on') || attr.value.toLowerCase().includes('javascript:')) {
                    el.removeAttribute(attr.name);
                }
            }
        });
        safeText = doc.body.innerHTML;

        const hasBlockTags = /<\/?(p|div|h[1-6]|ul|ol|li|blockquote|pre|table|thead|tbody|tfoot|tr|th|td|hr|section|article|header|footer)\b/i.test(safeText);

        safeText = safeText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        safeText = safeText.replace(/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

        if (!hasBlockTags) {
            safeText = safeText.replace(/\n/g, '<br>');
        } else {
            safeText = safeText.replace(/<br\s*\/?>\s*(<\/?(?:p|div|h[1-6]|ul|ol|li|blockquote|pre|table|thead|tbody|tfoot|tr|th|td|hr)\b)/gi, '$1');
            safeText = safeText.replace(/(<\/?(?:p|div|h[1-6]|ul|ol|li|blockquote|pre|table|thead|tbody|tfoot|tr|th|td|hr)\b[^>]*>)\s*<br\s*\/?>/gi, '$1');
        }

        safeText = safeText.replace(/__AIUTOMA_CODE_BLOCK_(\d+)__/g, function(match, i) {
            return '<pre class="aiutoma-msg-sql-pre" style="white-space: pre-wrap;"><code>' + codeBlocks[i] + '</code></pre>';
        });
        
        safeText = safeText.replace(/__AIUTOMA_INLINE_CODE_(\d+)__/g, function(match, i) {
            return '<code style="background: #f0f0f1; padding: 2px 4px; border-radius: 3px;">' + inlineCode[i] + '</code>';
        });

        return safeText;
    }

    function saveChatState() {
        const chatEl = document.getElementById('aiutoma-playground-chat');
        if (chatEl && window.aiutomaSessionMessages.length > 0) {
            const state = {
                is_full_state: true,
                conversation_id: window.aiutomaCurrentConversationId,
                session_prompts: window.aiutomaSessionPrompts,
                messages: window.aiutomaSessionMessages,
                context: getChatContext(),
                html: chatEl.innerHTML
            };
            localStorage.setItem('aiutoma_chat_state', JSON.stringify(state));

            const exportBtn = document.getElementById('aiutoma-export-session');
            if (exportBtn) exportBtn.style.display = 'inline-block';
            
            fetch(window.aiutomaSettings.restUrl.replace('ai-chat', 'save-session'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.aiutomaSettings.nonceRest
                },
                body: JSON.stringify(state),
                credentials: 'same-origin'
            }).catch(e => console.error('Failed to save session to server', e));
        }
    }

    function restoreChatState(state) {
        if (state && state.is_full_state) {
            const chatEl = document.getElementById('aiutoma-playground-chat');
            if (chatEl) {
                chatEl.innerHTML = '';
                if (state.messages && Array.isArray(state.messages)) {
                    state.messages.forEach(msg => {
                        const div = document.createElement('div');
                        div.className = msg.role === 'user' ? 'aiutoma-msg-user' : 'aiutoma-msg-ai';
                        
                        let text = '';
                        if (msg.parts && Array.isArray(msg.parts)) {
                            msg.parts.forEach(p => { if (p.text) text += p.text; });
                        }
                        
                        if (msg.role === 'user') {
                            const strong = document.createElement('strong');
                            strong.textContent = 'You:';
                            div.appendChild(strong);
                            div.appendChild(document.createElement('br'));
                            const contentDiv = document.createElement('div');
                            contentDiv.style.whiteSpace = 'pre-wrap';
                            contentDiv.textContent = text;
                            div.appendChild(contentDiv);
                        } else {
                            const headerDiv = document.createElement('div');
                            headerDiv.style.display = 'flex';
                            headerDiv.style.justifyContent = 'space-between';
                            headerDiv.style.alignItems = 'center';
                            headerDiv.style.marginBottom = '6px';
                            const strong = document.createElement('strong');
                            strong.textContent = 'AI:';
                            headerDiv.appendChild(strong);
                            div.appendChild(headerDiv);

                            const contentDiv = document.createElement('div');
                            contentDiv.innerHTML = simpleSanitizedMarkdown(text);
                            div.appendChild(contentDiv);
                        }
                        chatEl.appendChild(div);
                    });
                } else if (state.html) {
                    const warnDiv = document.createElement('div');
                    warnDiv.className = 'aiutoma-msg-ai';
                    warnDiv.style.background = '#ffebe8';
                    warnDiv.style.borderLeft = '4px solid #dc3232';
                    warnDiv.textContent = 'Legacy session HTML not rendered for security reasons. Extracting plain text...';
                    chatEl.appendChild(warnDiv);
                    
                    const doc = new DOMParser().parseFromString(state.html, 'text/html');
                    const legacyText = document.createElement('div');
                    legacyText.className = 'aiutoma-msg-ai';
                    legacyText.style.whiteSpace = 'pre-wrap';
                    legacyText.textContent = doc.body.textContent;
                    chatEl.appendChild(legacyText);
                }
                
                const wrapper = document.getElementById('aiutoma-playground-chat-wrapper');
                if (wrapper) wrapper.classList.add('has-content');
            }
            if (state.conversation_id) {
                window.aiutomaCurrentConversationId = state.conversation_id;
            }
            if (state.session_prompts) {
                window.aiutomaSessionPrompts = state.session_prompts;
                const exportBtn = document.getElementById('aiutoma-export-session');
                if (exportBtn && window.aiutomaSessionPrompts.length > 0) exportBtn.style.display = 'inline-block';
            }
            if (state.messages) {
                window.aiutomaSessionMessages = state.messages;
            }
            
            if (state.context) {
                const ctx = state.context;
                if (ctx.model) {
                    const modelSelect = document.getElementById('aiutoma-playground-model');
                    if (modelSelect) modelSelect.value = ctx.model;
                }
                if (ctx.fallback_models !== undefined) {
                    const fallbackCb = document.getElementById('aiutoma-fallback-models-checkbox');
                    if (fallbackCb) fallbackCb.checked = ctx.fallback_models;
                }
                if (ctx.enable_tools !== undefined) {
                    const toggle = document.getElementById('aiutoma-enable-abilities-toggle');
                    if (toggle) {
                        toggle.checked = ctx.enable_tools;
                        // Trigger change to update UI wrapper visibility
                        toggle.dispatchEvent(new Event('change'));
                    }
                }
                if (ctx.enabled_abilities) {
                    document.querySelectorAll('.aiutoma-ability-checkbox').forEach(cb => {
                        cb.checked = ctx.enabled_abilities.includes(cb.value);
                    });
                }
                if (ctx.enable_skills !== undefined) {
                    const toggle = document.getElementById('aiutoma-enable-skills-toggle');
                    if (toggle) {
                        toggle.checked = ctx.enable_skills;
                        toggle.dispatchEvent(new Event('change'));
                    }
                }
                if (ctx.enabled_skills) {
                    document.querySelectorAll('.aiutoma-skill-checkbox').forEach(cb => {
                        cb.checked = ctx.enabled_skills.includes(cb.value);
                    });
                }
                if (ctx.system_info !== undefined) {
                    const sysInfo = document.getElementById('aiutoma-include-system-info');
                    if (sysInfo) sysInfo.checked = ctx.system_info;
                }
                if (ctx.session_context !== undefined) {
                    const sessCtx = document.getElementById('aiutoma-session-context');
                    if (sessCtx) sessCtx.value = ctx.session_context;
                }
                if (ctx.permanent_context !== undefined) {
                    const permCtx = document.getElementById('aiutoma-permanent-context');
                    if (permCtx) permCtx.value = ctx.permanent_context;
                }
                if (ctx.rag_types) {
                    window.aiutomaSessionRagChecked = ctx.rag_types;
                    document.querySelectorAll('.aiutoma-rag-checkbox').forEach(cb => {
                        cb.checked = ctx.rag_types.includes(cb.dataset.type);
                    });
                }
            }
            // Re-initialize any CodeMirror instances if needed, or they remain as static code blocks.
            // In most cases, previous messages don't need re-execution.
        }
    }

    // Manual restore logic
    const restoreBtn = document.getElementById('aiutoma-restore-last-session');
    if (restoreBtn) {
        try {
            const saved = localStorage.getItem('aiutoma_chat_state');
            if (saved) {
                restoreBtn.style.display = 'inline-block';
                restoreBtn.addEventListener('click', function () {
                    restoreChatState(JSON.parse(saved));
                    this.style.display = 'none';
                    alert('Session restored successfully.');
                });
            }
        } catch (e) {
            console.error('Failed to check saved chat state', e);
        }
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            if (window.aiutomaSessionPrompts.length === 0) {
                alert('No prompts submitted in this session yet.');
                return;
            }
            const chatEl = document.getElementById('aiutoma-playground-chat');
            const state = {
                is_full_state: true,
                conversation_id: window.aiutomaCurrentConversationId,
                session_prompts: window.aiutomaSessionPrompts,
                messages: window.aiutomaSessionMessages,
                context: getChatContext()
            };
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(state, null, 2));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", "aiutoma_session_" + Date.now() + ".json");
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
        });
    }

    if (importBtn && importFile) {
        importBtn.addEventListener('click', function () {
            importFile.click();
        });

        importFile.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const data = JSON.parse(e.target.result);
                    if (data && data.is_full_state) {
                        restoreChatState(data);
                        localStorage.setItem('aiutoma_chat_state', JSON.stringify(data));
                        alert('Session restored successfully.');
                    } else if (Array.isArray(data) && data.length > 0) {
                        window.aiutomaPromptQueue = data;
                        checkPromptQueue();
                    } else {
                        alert('No valid session or prompts found in file.');
                    }
                } catch (err) {
                    alert('Invalid JSON format.');
                }
            };
            reader.readAsText(file);
            importFile.value = ''; // reset
        });
    }

    function checkPromptQueue() {
        if (window.aiutomaPromptQueue.length > 0 && (!sendBtn || !sendBtn.disabled)) {
            const nextPrompt = window.aiutomaPromptQueue.shift();
            const promptEl = document.getElementById('aiutoma-playground-prompt');
            if (promptEl && sendBtn) {
                promptEl.value = nextPrompt;
                sendBtn.click();
            }
        }
    }

    let aiutomaAttachments = [];
    const attachMediaBtn = document.getElementById('aiutoma-attach-media');
    const attachmentPreview = document.getElementById('aiutoma-playground-attachment-preview');

    if (attachMediaBtn && typeof wp !== 'undefined' && wp.media) {
        let mediaUploader;
        attachMediaBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media({
                title: 'Select Media',
                button: { text: 'Attach' },
                multiple: true
            });
            mediaUploader.on('select', function () {
                const selection = mediaUploader.state().get('selection');
                let newAttachments = [];
                selection.map(function (attachment) {
                    attachment = attachment.toJSON();
                    if (!aiutomaAttachments.find(a => a.id === attachment.id)) {
                        aiutomaAttachments.push(attachment);
                        newAttachments.push(attachment);
                    }
                });
                renderAttachmentPreview();
            });
            mediaUploader.open();
        });

        function fetchMediaMarkdown(attachments) {
            const container = document.getElementById('aiutoma-media-contexts-container');
            if (!container) return;
            
            container.style.display = 'flex';
            
            attachments.forEach(att => {
                if (document.getElementById(`aiutoma-media-context-box-${att.id}`)) return;
                
                const box = document.createElement('div');
                box.className = 'aiutoma-media-context-box';
                box.id = `aiutoma-media-context-box-${att.id}`;
                box.style.position = 'relative';
                
                const label = document.createElement('label');
                label.style.fontWeight = '500';
                label.style.color = '#135e96';
                label.style.display = 'block';
                label.style.marginBottom = '5px';
                label.innerHTML = `<span class="dashicons dashicons-text-page" style="vertical-align: middle;"></span> <span class="aiutoma-media-context-title">${att.filename}</span>`;
                
                const closeBtn = document.createElement('span');
                closeBtn.innerHTML = '&times;';
                closeBtn.style.float = 'right';
                closeBtn.style.cursor = 'pointer';
                closeBtn.style.color = '#d63638';
                closeBtn.style.fontWeight = 'bold';
                closeBtn.style.fontSize = '16px';
                closeBtn.title = 'Remove this context';
                closeBtn.addEventListener('click', function() {
                    box.remove();
                    if (container.children.length === 0) {
                        container.style.display = 'none';
                    }
                });
                label.appendChild(closeBtn);
                
                const ta = document.createElement('textarea');
                ta.className = 'aiutoma-media-context-textarea';
                ta.dataset.id = att.id;
                ta.dataset.filename = att.filename;
                ta.style.width = '100%';
                ta.style.height = '150px';
                ta.style.background = '#fff';
                ta.style.border = '1px solid #8c8f94';
                ta.style.borderRadius = '4px';
                ta.style.padding = '10px';
                ta.style.fontFamily = 'monospace';
                ta.value = `[Loading Markdown for ${att.filename}...]`;
                
                box.appendChild(label);
                box.appendChild(ta);
                container.appendChild(box);

                fetch(window.aiutomaSettings.restUrl.replace('ai-chat', 'convert-media'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.aiutomaSettings.nonceRest
                    },
                    body: JSON.stringify({ attachment_id: att.id }),
                    credentials: 'same-origin'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && typeof data.markdown !== 'undefined') {
                        ta.value = data.markdown;
                    } else {
                        ta.value = `--- Failed to convert ${att.filename}: ${data.message || 'Unknown error'} ---`;
                    }
                })
                .catch(err => {
                    ta.value = `--- Failed to convert ${att.filename} ---`;
                });
            });
        }

        // aiutoma-force-md listener removed

        function renderAttachmentPreview() {
            if (aiutomaAttachments.length === 0) {
                attachmentPreview.style.display = 'none';
                const container = document.getElementById('aiutoma-media-contexts-container');
                if (container) {
                    container.style.display = 'none';
                    container.innerHTML = '';
                }
                return;
            }
            attachmentPreview.innerHTML = '';
            attachmentPreview.style.display = 'flex';

            aiutomaAttachments.forEach(function (attachment, index) {
                const wrapper = document.createElement('div');
                wrapper.className = 'attachment aiutoma-attached-media';
                wrapper.style.position = 'relative';
                wrapper.style.display = 'inline-block';
                wrapper.style.margin = '4px 8px 4px 4px';
                wrapper.style.width = '80px';
                wrapper.style.height = '80px';
                wrapper.style.border = '1px solid #dcdcde';
                wrapper.style.backgroundColor = '#f0f0f1';
                wrapper.style.boxShadow = 'inset 0 0 15px rgba(0,0,0,0.1), inset 0 0 0 1px rgba(255,255,255,0.05)';
                wrapper.title = attachment.filename || 'Attachment';
                
                let imgSrc = attachment.icon;
                let showFilename = false;
                let imgStyles = "max-width: 100%; max-height: 100%; display: block;";

                if (attachment.type === 'image') {
                    imgSrc = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                } else {
                    showFilename = true;
                    imgStyles = "max-width: 40px; max-height: 40px; margin-bottom: 14px; display: block;";
                }
                
                let innerHtml = `
                    <div class="attachment-preview" style="width: 100%; height: 100%;">
                        <div class="thumbnail" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; overflow: hidden;">
                            <div class="centered" style="position: absolute; top:0; left:0; width:100%; height:100%; display:flex; align-items:center; justify-content:center;">
                                <img src="${imgSrc}" style="${imgStyles}" alt="" />
                            </div>`;
                if (showFilename) {
                    innerHtml += `
                            <div class="filename" style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(255,255,255,0.9); border-top: 1px solid #dcdcde; padding: 2px 4px; font-size: 10px; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: center; color: #2c3338;">
                                <div>${attachment.filename}</div>
                            </div>`;
                }
                innerHtml += `
                        </div>
                    </div>
                `;
                wrapper.innerHTML = innerHtml;

                const removeBtn = document.createElement('span');
                removeBtn.innerHTML = '&times;';
                removeBtn.style.position = 'absolute';
                removeBtn.style.top = '-8px';
                removeBtn.style.right = '-8px';
                removeBtn.style.background = '#d63638';
                removeBtn.style.color = '#fff';
                removeBtn.style.borderRadius = '50%';
                removeBtn.style.width = '18px';
                removeBtn.style.height = '18px';
                removeBtn.style.lineHeight = '16px';
                removeBtn.style.textAlign = 'center';
                removeBtn.style.fontSize = '14px';
                removeBtn.style.cursor = 'pointer';
                removeBtn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.3)';
                removeBtn.style.zIndex = '10';
                removeBtn.dataset.index = index;

                removeBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const removedAtt = aiutomaAttachments[this.dataset.index];
                    aiutomaAttachments.splice(this.dataset.index, 1);
                    
                    const contextBox = document.getElementById(`aiutoma-media-context-box-${removedAtt.id}`);
                    if (contextBox) {
                        contextBox.remove();
                    }
                    const container = document.getElementById('aiutoma-media-contexts-container');
                    if (container && container.children.length === 0) {
                        container.style.display = 'none';
                    }
                    
                    renderAttachmentPreview();
                });

                wrapper.appendChild(removeBtn);
                
                const isMediaContent = attachment.type && (attachment.type.includes('image') || attachment.type.includes('video') || attachment.type.includes('audio'));
                if (!isMediaContent) {
                    const extractBtn = document.createElement('span');
                    extractBtn.innerHTML = '<span class="dashicons dashicons-media-text" style="font-size:11px; line-height:18px;"></span>';
                    extractBtn.title = 'Extract text to Markdown';
                    extractBtn.style.position = 'absolute';
                    extractBtn.style.top = '-8px';
                    extractBtn.style.right = '15px'; // slightly left of remove button
                    extractBtn.style.background = '#2271b1';
                    extractBtn.style.color = '#fff';
                    extractBtn.style.borderRadius = '50%';
                    extractBtn.style.width = '18px';
                    extractBtn.style.height = '18px';
                    extractBtn.style.lineHeight = '18px';
                    extractBtn.style.textAlign = 'center';
                    extractBtn.style.cursor = 'pointer';
                    extractBtn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.3)';
                    extractBtn.style.zIndex = '10';
                    extractBtn.dataset.index = index;

                    extractBtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        fetchMediaMarkdown([aiutomaAttachments[this.dataset.index]]);
                    });
                    wrapper.appendChild(extractBtn);
                }

                attachmentPreview.appendChild(wrapper);
            });
        }
    }

    if (sendBtn) {
        const promptElMain = document.getElementById('aiutoma-playground-prompt');
        if (promptElMain) {
            promptElMain.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    if (!sendBtn.disabled) {
                        sendBtn.click();
                    }
                }
            });
        }

        sendBtn.addEventListener('click', async function () {
            if (window.aiutomaAbortController) {
                window.aiutomaAbortController.abort();
                return;
            }

            const promptEl = document.getElementById('aiutoma-playground-prompt');
            const chatEl = document.getElementById('aiutoma-playground-chat');
            let prompt = promptEl.value.trim();
            if (!prompt && aiutomaAttachments.length === 0) return;

            if (aiutomaAttachments.length > 0) {
                aiutomaAttachments.forEach(att => {
                    prompt += "\n\n[Attached Media ID: " + att.id + " | URL: " + att.url + "]";
                });
            }
            
            let extractedContexts = '';
            const textareas = document.querySelectorAll('.aiutoma-media-context-textarea');
            textareas.forEach(ta => {
                if (ta.value.trim() !== '' && !ta.value.includes('[Loading Markdown')) {
                    extractedContexts += `\n\n--- Start of ${ta.dataset.filename} ---\n${ta.value.trim()}\n--- End of ${ta.dataset.filename} ---\n`;
                }
            });

            if (extractedContexts !== '') {
                prompt += "\n\n[Extracted Document Context]\n" + extractedContexts + "\n[/Extracted Document Context]";
            }

            let displayPrompt = prompt;
            if (extractedContexts !== '') {
                displayPrompt = displayPrompt.replace("\n\n[Extracted Document Context]\n" + extractedContexts + "\n[/Extracted Document Context]", "");
            }

            window.aiutomaSessionPrompts.push(prompt);
            window.aiutomaSessionMessages.push({ role: 'user', parts: [{ text: displayPrompt }] });

            const wrapper = document.getElementById('aiutoma-playground-chat-wrapper');
            if (wrapper) wrapper.classList.add('has-content');

            if (aiutomaAttachments.length > 0) {
                aiutomaAttachments.forEach(att => {
                    displayPrompt = displayPrompt.replace("[Attached Media ID: " + att.id + " | URL: " + att.url + "]", "<br><em>Attached File: <a href='" + att.url + "' target='_blank'>" + escapeHtml(att.filename || 'View') + " (ID: " + att.id + ")</a></em>");
                });
            }

            chatEl.insertAdjacentHTML('beforeend', '<div class="aiutoma-msg-user"><strong>You:</strong><br>' + displayPrompt.replace(/\n/g, '<br>') + '</div>');
            promptEl.value = '';
            promptEl.style.boxShadow = 'none';

            if (aiutomaAttachments.length > 0) {
                aiutomaAttachments = [];
                if (attachmentPreview) {
                    attachmentPreview.innerHTML = '';
                    attachmentPreview.style.display = 'none';
                }
            }
            
            const container = document.getElementById('aiutoma-media-contexts-container');
            if (container) {
                container.style.display = 'none';
                container.innerHTML = '';
            }

            window.aiutomaAbortController = new AbortController();
            sendBtn.classList.remove('button-primary');
            sendBtn.classList.add('aiutoma-btn-danger');
            sendBtn.innerHTML = '<span class="dashicons dashicons-no-alt"></span>';
            sendBtn.title = 'Stop AI';

            const doStep = async (requestBody, stepMessage, activeToolNodes = [], isAutoRetry = false) => {
                if (!window.aiutomaAbortController) {
                    window.aiutomaAbortController = new AbortController();
                    if (sendBtn) {
                        sendBtn.classList.remove('button-primary');
                        sendBtn.classList.add('aiutoma-btn-danger');
                        sendBtn.innerHTML = '<span class="dashicons dashicons-no-alt"></span>';
                        sendBtn.title = 'Stop AI';
                    }
                }
                if (!document.getElementById('aiutoma-spinner-style')) {
                    document.head.insertAdjacentHTML('beforeend', '<style id="aiutoma-spinner-style">@keyframes aiutoma-spin { to { transform: rotate(360deg); } }</style>');
                }
                const loadingId = 'loading-' + Date.now();
                chatEl.insertAdjacentHTML('beforeend', '<div id="' + loadingId + '" class="aiutoma-msg-loading"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-opacity="0.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><em>' + stepMessage + '</em></div>');
                chatEl.scrollTop = chatEl.scrollHeight;

                try {
                    let fetchUrl = window.aiutomaSettings.restUrl;
                    if (aiEnforceSafeMode && window.aiutomaSettings.hasDevExtension) {
                        fetchUrl += (fetchUrl.includes('?') ? '&' : '?') + 'aiutoma_enforce_safe_mode=1';
                    }

                    if (window.aiutomaSettings.debugMode) console.debug("[Aiutoma Playground] Sending API request:", { url: fetchUrl, data: requestBody });

                    const response = await fetch(fetchUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': window.aiutomaSettings.nonceRest
                        },
                        body: JSON.stringify(requestBody),
                        credentials: 'same-origin',
                        signal: window.aiutomaAbortController.signal
                    });

                    if (window.aiutomaSettings.debugMode) console.debug("[Aiutoma Playground] HTTP Response Status:", response.status);

                    let data;
                    try {
                        data = await response.json();
                        if (window.aiutomaSettings.debugMode) console.debug("[Aiutoma Playground] JSON Response Data:", data);
                    } catch (parseError) {
                        const err = new Error(`Server returned ${response.status}: ${response.statusText} (Invalid JSON)`);
                        err.status = response.status;
                        throw err;
                    }

                    if (data.conversation_id) {
                        window.aiutomaCurrentConversationId = data.conversation_id;
                    }
                    document.getElementById(loadingId).remove();

                    const friendlyNames = {
                        'wpab__core__get-site-info': 'Reading Site Information',
                        'wpab__core__get-user-info': 'Reading User Information',
                        'wpab__core__get-environment-info': 'Reading Environment Information',
                        'wpab__ai__execute-php': 'Executing PHP Code',
                        'wpab__ai__generate-image': 'Generating Image',
                        'wpab__ai__read-file': 'Reading File',
                        'wpab__ai__modify-file': 'Modifying File',
                        'wpab__ai__list-directory': 'Listing Directory',
                        'wpab__ai__search-web': 'Searching Web',
                        'wpab__ai__db-query': 'Executing DB Query',
                        'wpab__ai__manage-plugins': 'Managing Plugins',
                        'wpab__ai__manage-themes': 'Managing Themes',
                        'wpab__ai__manage-system': 'Managing System Info',
                        'wpab__ai__manage-debug': 'Managing Debug Log',
                        'wpab__ai__manage-posts': 'Managing Posts/Pages',
                        'wpab__ai__manage-comments': 'Managing Comments',
                        'wpab__ai__manage-users': 'Managing Users & Profiles',
                        'wpab__ai__dev-manage-users': 'Managing Users & Roles (Dev)',
                        'wpab__ai__manage-media': 'Managing Media Library',
                        'wpab__ai__manage-menus': 'Managing Menus',
                        'wpab__ai__manage-woocommerce': 'Managing WooCommerce',
                        'wpab__ai__manage-wpml': 'Managing WPML',
                        'wpab__ai__get-post-details': 'Getting Post Details',
                        'wpab__ai__get-post-terms': 'Getting Post Terms',
                        'wpab__ai__title-generation': 'Generating Title',
                        'wpab__ai__comment-analysis': 'Analyzing Comments',
                        'wpab__ai__editorial-updates': 'Applying Editorial Updates',
                        'wpab__ai__content-classification': 'Classifying Content',
                        'wpab__ai__excerpt-generation': 'Generating Excerpt',
                        'wpab__ai__summarization': 'Summarizing Content',
                        'wpab__ai__editorial-notes': 'Writing Editorial Notes',
                        'wpab__ai__alt-text-generation': 'Generating Image Alt Text',
                        'wpab__ai__content-resizing': 'Resizing Content',
                        'wpab__ai__meta-description': 'Generating Meta Description',
                        'wpab__ai__image-generation': 'Generating Image',
                        'wpab__ai__image-import': 'Importing Image',
                        'wpab__ai__image-prompt-generation': 'Generating Image Prompt'
                    };

                    if (data.success) {
                        if (data.previous_results && data.previous_results.length > 0) {
                            let criticalExecuted = false;
                            data.previous_results.forEach((res, index) => {
                                if (res.name === 'wpab__ai__execute-php' || res.name === 'wpab__ai__modify-file' || res.name === 'wpab__ai__db-query') {
                                    criticalExecuted = true;
                                }
                                const node = activeToolNodes[index];
                                if (node) {
                                    const iconSpan = node.querySelector('.aiutoma-tool-icon');
                                    if (iconSpan) iconSpan.innerText = (res.response && res.response.error) ? '❌' : '✔️';

                                    const detailsDiv = node.querySelector('.aiutoma-tool-details');
                                    if (detailsDiv) {
                                        let msg = 'Completed.';
                                        if (res.response && res.response.message) {
                                            msg = res.response.message;
                                        } else if (res.response && res.response.error) {
                                            msg = 'Error: ' + res.response.error;
                                        }
                                        let rollbackBtnHtml = '';
                                        if (res.response && res.response.backup_id) {
                                            rollbackBtnHtml = `<div class="aiutoma-rollback-btn-wrapper"><button type="button" class="button button-small aiutoma-rollback-btn" data-backup-id="${escapeHtml(res.response.backup_id)}">↩️ Rollback Action</button></div>`;
                                        }
                                        const resultHtml = '<div class="aiutoma-msg-tool-result ' + ((res.response && res.response.error) ? 'aiutoma-error' : 'aiutoma-success') + '"><strong>Result:</strong> ' + msg + '<details class="aiutoma-msg-tool-details"><summary>(technical output returned to AI)</summary><pre class="aiutoma-msg-tool-pre">' + JSON.stringify(res.response, null, 2).replace(/</g, "&lt;").replace(/>/g, "&gt;") + '</pre></details>' + rollbackBtnHtml + '</div>';
                                        detailsDiv.insertAdjacentHTML('beforeend', resultHtml);

                                        // Auto-close when completed to keep UI clean
                                        detailsDiv.style.display = 'none';
                                    }
                                }
                            });

                            if (criticalExecuted) {
                                try {
                                    const feResponse = await fetch(window.aiutomaSettings.homeUrl);
                                    if (!feResponse.ok && feResponse.status >= 500) {
                                        const safeModeMsg = window.aiutomaSettings.hasDevExtension ? ' Safe Mode is being activated automatically.' : '';
                                        chatEl.insertAdjacentHTML('beforeend', '<div class="aiutoma-msg-error"><strong>System:</strong> ⚠️ WARNING: The frontend of your website is currently returning a ' + feResponse.status + ' Error! Your last action may have broken the site.' + safeModeMsg + '</div>');

                                        if (!aiEnforceSafeMode) {
                                            aiEnforceSafeMode = true;
                                            const statusEl = document.getElementById('aiutoma-safemode-status');
                                            if (statusEl) statusEl.innerText = 'Strict Safe Mode Enforced (Auto-Recovered)';

                                            const toggleSafeModeBtn = document.getElementById('aiutoma-toggle-safe-mode');
                                            if (toggleSafeModeBtn) {
                                                toggleSafeModeBtn.classList.add('aiutoma-safe-mode-active');
                                                toggleSafeModeBtn.dataset.active = "1";
                                            }

                                            try {
                                                const toggleUrl = window.aiutomaSettings.restUrl.replace('ai-chat', 'toggle-safe-mode') + (window.aiutomaSettings.hasDevExtension ? '?aiutoma_enforce_safe_mode=1' : '');
                                                await fetch(toggleUrl, {
                                                    method: 'POST',
                                                    headers: { 'X-WP-Nonce': window.aiutomaSettings.nonceRest, 'Content-Type': 'application/json' },
                                                    body: JSON.stringify({ force: 'enable' })
                                                });
                                            } catch (e) { }
                                        }

                                        // Send a prompt to the AI to debug it
                                        setTimeout(() => {
                                            const safeActiveStr = window.aiutomaSettings.hasDevExtension ? ' Safe Mode is now active.' : '';
                                            const debugPrompt = "SYSTEM ALERT: The last action caused a Fatal Error on the frontend of the website. The homepage is returning HTTP " + feResponse.status + "." + safeActiveStr + " Please use the wpab__ai__manage-debug tool to enable the debug log, find the error, and fix it immediately.";
                                            chatEl.insertAdjacentHTML('beforeend', '<div class="aiutoma-msg-user" style="background-color: #ffebe8; border-left: 4px solid #dc3232;"><strong>System Auto-Prompt:</strong><br>' + debugPrompt + '</div>');
                                            chatEl.scrollTop = chatEl.scrollHeight;
                                            doStep({
                                                conversation_id: data.conversation_id,
                                                prompt: debugPrompt,
                                                model: document.getElementById('aiutoma-playground-model') ? document.getElementById('aiutoma-playground-model').value : ''
                                            }, window.aiutomaSettings.textAiThinking, [], true);
                                        }, 1000);
                                    }
                                } catch (e) {
                                    // ignore network errors for verification
                                }
                            }
                        }

                        if (data.action === 'tool_result' && data.tools) {
                            data.tools.forEach(t => {
                                const resultDiv = document.createElement('div');
                                resultDiv.className = 'aiutoma-tool-result';
                                resultDiv.style.cssText = 'margin-bottom: 10px; padding: 10px; background: #eaf5ea; border-left: 4px solid #46b450; border-radius: 3px; font-size: 13px;';

                                let preStr = '<pre class="aiutoma-pre-wrap">';
                                let stringified = typeof t.result === 'object' ? JSON.stringify(t.result, null, 2) : String(t.result);

                                let rollbackBtnHtml = '';
                                if (typeof t.result === 'object' && t.result !== null && t.result.backup_id) {
                                    rollbackBtnHtml = `<div class="aiutoma-rollback-btn-wrapper"><button type="button" class="button button-small aiutoma-rollback-btn" data-backup-id="${escapeHtml(t.result.backup_id)}">↩️ Rollback Action</button></div>`;
                                }

                                resultDiv.innerHTML = `<strong>Result: ${t.name}</strong><br>${preStr}${escapeHtml(stringified)}</pre>${rollbackBtnHtml}`;

                                chatEl.appendChild(resultDiv);
                            });
                        }

                        if (data.action === 'tool_calls') {
                            const nextActiveNodes = [];
                            const toolsWrapper = document.createElement('div');
                            toolsWrapper.style.cssText = 'margin-bottom:15px; margin-top: 15px; background: #f8f9fa; border: 1px dashed #ccc; padding: 10px; border-radius: 4px; font-size: 13px;';
                            toolsWrapper.innerHTML = '<strong>System Tasks:</strong><br>';

                            let requiresApproval = false;

                            data.tools.forEach(t => {
                                const isDbQuery = t.name.includes('db-query') || t.name.includes('db_query');
                                const isSensitive = t.name.includes('execute-php') || t.name.includes('execute_php') || isDbQuery || t.name.includes('modify-file') || t.name.includes('modify_file');

                                let needsApproval = t.name.includes('execute-php') || t.name.includes('execute_php') || t.name.includes('modify-file') || t.name.includes('modify_file');

                                if (isDbQuery && t.args.query) {
                                    const qUpper = t.args.query.trim().toUpperCase();
                                    if (!qUpper.startsWith('SELECT') && !qUpper.startsWith('SHOW') && !qUpper.startsWith('DESCRIBE')) {
                                        needsApproval = true;
                                    }
                                } else if (isDbQuery) {
                                    needsApproval = true;
                                }

                                if (needsApproval) {
                                    requiresApproval = true;
                                }

                                let friendlyName = friendlyNames[t.name];
                                if (!friendlyName) {
                                    if (t.name === 'aiutoma/abilities' || t.name === 'wpab__aiutoma__abilities' || t.name.endsWith('abilities')) {
                                        if (t.args && t.args.action === 'execute' && t.args.ability_name) {
                                            let targetName = t.args.ability_name;
                                            let formattedTarget = targetName;
                                            if (targetName.includes('/')) {
                                                let [ns, ab] = targetName.split('/');
                                                if (ns.toLowerCase() === 'woocommerce') ns = 'WooCommerce';
                                                else ns = ns.charAt(0).toUpperCase() + ns.slice(1);
                                                ab = ab.replace(/-/g, ' ').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                                                formattedTarget = ns + ': ' + ab;
                                            }
                                            friendlyName = 'Ability: ' + formattedTarget;
                                        } else if (t.args && t.args.action === 'list') {
                                            friendlyName = 'Ability: Discover Tools' + (t.args.search ? ' ("' + t.args.search + '")' : '');
                                        } else if (t.args && t.args.action === 'get') {
                                            friendlyName = 'Ability: Inspect (' + (t.args.ability_name || '') + ')';
                                        } else {
                                            friendlyName = 'Aiutoma: Abilities';
                                        }
                                    } else if (t.name.startsWith('wpab__')) {
                                        let parts = t.name.split('__');
                                        if (parts.length >= 3) {
                                            let category = parts[1];
                                            if (category.toLowerCase() === 'woocommerce') category = 'WooCommerce';
                                            else category = category.charAt(0).toUpperCase() + category.slice(1);

                                            let action = parts.slice(2).join(' ').replace(/-/g, ' ').replace(/_/g, ' ');
                                            action = action.replace(/\b\w/g, c => c.toUpperCase());
                                            friendlyName = category + ': ' + action;
                                        } else {
                                            friendlyName = t.name;
                                        }
                                    } else {
                                        friendlyName = t.name;
                                    }
                                }
                                const toolNode = document.createElement('div');
                                toolNode.style.marginBottom = '8px';

                                const header = document.createElement('div');
                                header.className = 'aiutoma-tool-header-toggle';
                                header.style.cssText = 'display: flex; align-items: center; cursor: pointer; padding: 4px; border-radius: 4px; transition: background 0.2s;';
                                header.onmouseover = () => header.style.background = '#e9ecef';
                                header.onmouseout = () => header.style.background = 'transparent';
                                header.innerHTML = '<span class="aiutoma-tool-icon" style="margin-right: 8px;">⚙️</span><strong>' + friendlyName + '</strong> <span title="Click to view details" class="aiutoma-tool-help">?</span>';

                                const details = document.createElement('div');
                                details.className = 'aiutoma-tool-details';
                                details.style.cssText = 'display: ' + (isSensitive ? 'block' : 'none') + '; margin-top: 5px; padding: 8px; background: #eee; border-radius: 4px; font-family: monospace; font-size: 11px; overflow-x: auto; white-space: pre-wrap;';



                                let displayArgs = '';
                                console.log(t.name);
                                if ((t.name.includes('execute-php') || t.name.includes('execute_php')) && t.args.code) {
                                    if (window.aiutomaSettings.cmSettings && typeof wp !== 'undefined' && wp.codeEditor) {
                                        const taId = 'aiutoma-cm-' + t.name.replace(/[^a-zA-Z0-9]/g, '') + '-' + Math.floor(Math.random() * 1000000);
                                        let editorCode = t.args.code;
                                        if (!editorCode.trim().startsWith('<?php')) {
                                            editorCode = '<?php\n' + editorCode;
                                        }
                                        displayArgs = '<strong>PHP Code (Editable):</strong><br><textarea id="' + taId + '" class="aiutoma-code-ta">' + editorCode.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</textarea>';
                                        const otherArgs = { ...t.args };
                                        delete otherArgs.code;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                        setTimeout(() => {
                                            const ta = document.getElementById(taId);
                                            if (ta) {
                                                try {
                                                    const editor = wp.codeEditor.initialize(ta, window.aiutomaSettings.cmSettings);
                                                    editor.codemirror.setOption('viewportMargin', Infinity);
                                                    toolNode.dataset.cmId = t.id;
                                                    toolNode.cmEditor = editor.codemirror;
                                                    setTimeout(() => {
                                                        editor.codemirror.refresh();
                                                    }, 50);
                                                } catch (err) { }
                                            }
                                        }, 100);
                                    } else {
                                        let codeStr = t.args.code.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                        codeStr = codeStr.replace(/(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/g, '<span class="aiutoma-hl-var">$1</span>');
                                        codeStr = codeStr.replace(/('[^']*')/g, '<span class="aiutoma-hl-string">$1</span>');
                                        codeStr = codeStr.replace(/("[^"]*")/g, '<span class="aiutoma-hl-string">$1</span>');
                                        const keywords = ['return', 'array', 'if', 'else', 'for', 'foreach', 'while', 'function', 'class', 'public', 'private', 'protected', 'new'];
                                        const keywordRegex = new RegExp('\\b(' + keywords.join('|') + ')\\b', 'g');
                                        codeStr = codeStr.replace(keywordRegex, '<span class="aiutoma-hl-keyword">$1</span>');
                                        codeStr = codeStr.replace(/([a-zA-Z_]+)\s*\(/g, '<span class="aiutoma-hl-func">$1</span>(');
                                        codeStr = codeStr.replace(/AIUTOMA_COLOR_([a-zA-Z0-9]+)/g, '"color: #$1;"');

                                        displayArgs = '<strong>PHP Code:</strong><br><pre class="aiutoma-msg-sql-pre"><code>' + codeStr + '</code></pre>';

                                        const otherArgs = { ...t.args };
                                        delete otherArgs.code;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                    }
                                } else if ((t.name.includes('db-query') || t.name.includes('db_query')) && t.args.query) {
                                    if (window.aiutomaSettings.cmSqlSettings && typeof wp !== 'undefined' && wp.codeEditor) {
                                        const taId = 'aiutoma-cm-' + t.name.replace(/[^a-zA-Z0-9]/g, '') + '-' + Math.floor(Math.random() * 1000000);
                                        displayArgs = '<strong>SQL Query (Editable):</strong><br><textarea id="' + taId + '" class="aiutoma-code-ta">' + t.args.query.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</textarea>';
                                        const otherArgs = { ...t.args };
                                        delete otherArgs.query;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                        setTimeout(() => {
                                            const ta = document.getElementById(taId);
                                            if (ta) {
                                                try {
                                                    const editor = wp.codeEditor.initialize(ta, window.aiutomaSettings.cmSqlSettings);
                                                    editor.codemirror.setOption('viewportMargin', Infinity);
                                                    toolNode.dataset.cmQueryId = t.id;
                                                    toolNode.cmEditorQuery = editor.codemirror;
                                                    setTimeout(() => {
                                                        editor.codemirror.refresh();
                                                    }, 50);
                                                } catch (err) { }
                                            }
                                        }, 100);
                                    } else {
                                        let queryStr = t.args.query.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                        const sqlKeywords = ['SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'INSERT', 'INTO', 'UPDATE', 'SET', 'DELETE', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'ORDER BY', 'GROUP BY', 'LIMIT'];
                                        const sqlKeywordRegex = new RegExp('\\b(' + sqlKeywords.join('|') + ')\\b', 'gi');
                                        queryStr = queryStr.replace(sqlKeywordRegex, '<span class="aiutoma-hl-keyword">$1</span>');

                                        displayArgs = '<strong>SQL Query:</strong><br><pre class="aiutoma-msg-sql-pre"><code>' + queryStr + '</code></pre>';

                                        const otherArgs = { ...t.args };
                                        delete otherArgs.query;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                    }
                                } else if ((t.name.includes('modify-file') || t.name.includes('modify_file')) && t.args.content) {
                                    if (window.aiutomaSettings.cmSettings && typeof wp !== 'undefined' && wp.codeEditor) {
                                        const taId = 'aiutoma-cm-' + t.name.replace(/[^a-zA-Z0-9]/g, '') + '-' + Math.floor(Math.random() * 1000000);
                                        displayArgs = '<strong>File Content (Editable):</strong><br><textarea id="' + taId + '" class="aiutoma-code-ta">' + t.args.content.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</textarea>';
                                        const otherArgs = { ...t.args };
                                        delete otherArgs.content;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                        setTimeout(() => {
                                            const ta = document.getElementById(taId);
                                            if (ta) {
                                                try {
                                                    const editor = wp.codeEditor.initialize(ta, window.aiutomaSettings.cmSettings);
                                                    editor.codemirror.setOption('viewportMargin', Infinity);
                                                    toolNode.dataset.cmFileId = t.id;
                                                    toolNode.cmEditorFile = editor.codemirror;
                                                    setTimeout(() => {
                                                        editor.codemirror.refresh();
                                                    }, 50);
                                                } catch (err) { }
                                            }
                                        }, 100);
                                    } else {
                                        displayArgs = '<strong>File Content:</strong><br><pre class="aiutoma-msg-sql-pre"><code>' + t.args.content.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</code></pre>';
                                        const otherArgs = { ...t.args };
                                        delete otherArgs.content;
                                        if (Object.keys(otherArgs).length > 0) {
                                            displayArgs += '<br><strong>Other Arguments:</strong><br>' + JSON.stringify(otherArgs, null, 2);
                                        }
                                    }
                                } else {
                                    displayArgs = '<strong>Arguments:</strong><br>' + JSON.stringify(t.args, null, 2);
                                }

                                details.innerHTML = displayArgs;

                                header.classList.add('aiutoma-tool-header-toggle');

                                toolNode.appendChild(header);
                                toolNode.appendChild(details);
                                toolsWrapper.appendChild(toolNode);
                                nextActiveNodes.push(toolNode);
                            });

                            const getSessionPayload = () => {
                                const enableAbilitiesToggle = document.getElementById('aiutoma-enable-abilities-toggle');
                                const enable_tools = enableAbilitiesToggle ? enableAbilitiesToggle.checked : true;
                                const selectedAbilities = [];
                                if (enable_tools) {
                                    document.querySelectorAll('.aiutoma-ability-checkbox:checked').forEach(cb => {
                                        selectedAbilities.push(cb.value);
                                    });
                                }
                                const enableSkillsToggle = document.getElementById('aiutoma-enable-skills-toggle');
                                const enable_skills = enableSkillsToggle ? enableSkillsToggle.checked : false;
                                const selectedSkills = [];
                                if (enable_skills) {
                                    document.querySelectorAll('.aiutoma-skill-checkbox:checked').forEach(cb => {
                                        selectedSkills.push(cb.value);
                                    });
                                }
                                return {
                                    enable_tools: enable_tools,
                                    enabled_abilities: selectedAbilities,
                                    enable_skills: enable_skills,
                                    enabled_skills: selectedSkills,
                                    session_context: document.getElementById('aiutoma-session-context') ? document.getElementById('aiutoma-session-context').value : '',
                                    permanent_context: document.getElementById('aiutoma-permanent-context') ? document.getElementById('aiutoma-permanent-context').value : '',
                                    object_type: window.aiutomaSettings.objectType || ''
                                };
                            };

                            if (requiresApproval) {
                                const approvalWrapper = document.createElement('div');
                                approvalWrapper.style.cssText = 'margin-top: 10px; display: flex; gap: 10px; align-items: center; border-top: 1px solid #ddd; padding-top: 10px;';

                                const approveBtn = document.createElement('button');
                                approveBtn.className = 'button button-primary';
                                approveBtn.innerText = 'Approve & Execute';

                                const cancelBtn = document.createElement('button');
                                cancelBtn.className = 'button button-secondary';
                                cancelBtn.innerText = 'Cancel';

                                approvalWrapper.appendChild(approveBtn);
                                approvalWrapper.appendChild(cancelBtn);
                                toolsWrapper.appendChild(approvalWrapper);

                                chatEl.appendChild(toolsWrapper);
                                chatEl.scrollTop = chatEl.scrollHeight;

                                const globalAutoApprove = document.getElementById('aiutoma-global-auto-approve');
                                if (globalAutoApprove && globalAutoApprove.checked) {
                                    approvalWrapper.style.display = 'none';
                                    await doStep(Object.assign({
                                        conversation_id: data.conversation_id,
                                        execute_tools: true,
                                        model: modelSelect ? modelSelect.value : '',
                                        fallback_models: fallbackModelsCheckbox ? fallbackModelsCheckbox.checked : false
                                    }, getSessionPayload()), window.aiutomaSettings.textAiThinking, nextActiveNodes);
                                } else {
                                    approveBtn.onclick = async () => {
                                        approveBtn.disabled = true;
                                        cancelBtn.disabled = true;
                                        approveBtn.innerText = 'Executing...';

                                        const modified_tools = {};
                                        nextActiveNodes.forEach(node => {
                                            if (node.dataset.cmId && node.cmEditor) {
                                                let cmValue = node.cmEditor.getValue();
                                                if (cmValue.trim().startsWith('<?php')) {
                                                    cmValue = cmValue.replace(/^\s*<\?php\s*/i, '');
                                                }
                                                modified_tools[node.dataset.cmId] = { code: cmValue };
                                            } else if (node.dataset.cmQueryId && node.cmEditorQuery) {
                                                modified_tools[node.dataset.cmQueryId] = { query: node.cmEditorQuery.getValue() };
                                            } else if (node.dataset.cmFileId && node.cmEditorFile) {
                                                modified_tools[node.dataset.cmFileId] = { content: node.cmEditorFile.getValue() };
                                            }
                                        });

                                        await doStep(Object.assign({
                                            conversation_id: data.conversation_id,
                                            execute_tools: true,
                                            modified_tools: modified_tools,
                                            model: modelSelect ? modelSelect.value : '',
                                            fallback_models: fallbackModelsCheckbox ? fallbackModelsCheckbox.checked : false
                                        }, getSessionPayload()), window.aiutomaSettings.textAiThinking, nextActiveNodes);

                                        approvalWrapper.style.display = 'none';
                                    };

                                    cancelBtn.onclick = async () => {
                                        approveBtn.disabled = true;
                                        cancelBtn.disabled = true;
                                        cancelBtn.innerText = 'Cancelled';

                                        await doStep(Object.assign({
                                            conversation_id: data.conversation_id,
                                            cancel_tools: true,
                                            model: modelSelect ? modelSelect.value : '',
                                            fallback_models: fallbackModelsCheckbox ? fallbackModelsCheckbox.checked : false
                                        }, getSessionPayload()), window.aiutomaSettings.textAiThinking, nextActiveNodes);

                                        approvalWrapper.style.display = 'none';
                                    };
                                }
                            } else {
                                chatEl.appendChild(toolsWrapper);
                                chatEl.scrollTop = chatEl.scrollHeight;

                                await doStep(Object.assign({
                                    conversation_id: data.conversation_id,
                                    execute_tools: true,
                                    model: modelSelect ? modelSelect.value : '',
                                    fallback_models: fallbackModelsCheckbox ? fallbackModelsCheckbox.checked : false
                                }, getSessionPayload()), window.aiutomaSettings.textAiThinking, nextActiveNodes);
                            }
                        } else {
                            let aiResponse = data.response || '';
                            const escapedResponse = escapeHtml(aiResponse);
                            let tokenHtml = '';
                            if (data.token_usage && data.token_usage.totalTokens) {
                                tokenHtml = `<div style="text-align: right; font-size: 11px; color: #888; margin-top: 8px; border-top: 1px dashed #ccc; padding-top: 4px;">⚡ Sent: ${data.token_usage.promptTokens} | Received: ${data.token_usage.completionTokens} | Total: ${data.token_usage.totalTokens}</div>`;
                            }
                            
                            window.aiutomaSessionMessages.push({ role: 'model', parts: [{ text: aiResponse }] });
                            
                            const copyBtnHtml = `<button type="button" class="button button-small aiutoma-copy-btn" data-text="${escapedResponse}" title="Copy to clipboard"><span class="dashicons dashicons-clipboard" style="font-size: 16px; width: 16px; height: 16px; margin-top: 3px;"></span> Copy</button>`;
                            const headerHtml = `<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;"><strong>AI:</strong>${copyBtnHtml}</div>`;
                            const formattedResponse = simpleSanitizedMarkdown(aiResponse);
                            const redirectMatch = aiResponse.match(/\[REDIRECT_TO_BLOCK:\s*(\d+)\]/i);
                            if (redirectMatch) {
                                const blockId = redirectMatch[1];
                                chatEl.insertAdjacentHTML('beforeend', '<div class="aiutoma-msg-ai">' + headerHtml + formattedResponse + '<br><br><em>Redirecting to block editor...</em>' + tokenHtml + '</div>');
                                setTimeout(() => {
                                    window.location.href = window.ajaxurl.replace('admin-ajax.php', 'post.php?post=' + blockId + '&action=edit');
                                }, 1500);
                            } else {
                                chatEl.insertAdjacentHTML('beforeend', '<div class="aiutoma-msg-ai">' + headerHtml + formattedResponse + tokenHtml + '</div>');
                            }
                            restoreSendBtn();
                            checkPromptQueue();
                        }
                    } else {
                        if (response.status >= 500 || (data.data && data.data.status >= 500)) {
                            const err = new Error(data.message || 'Unknown error');
                            err.status = response.status >= 500 ? response.status : (data.data ? data.data.status : 500);
                            throw err;
                        }

                        let errorMessage = data.message || 'Unknown error';
                        errorMessage = errorMessage.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
                        chatEl.insertAdjacentHTML('beforeend', '<div class="aiutoma-msg-error"><strong>Error:</strong><br>' + errorMessage + '</div>');
                        restoreSendBtn();
                        checkPromptQueue();
                    }
                } catch (e) {
                    const l = document.getElementById(loadingId);
                    if (l) l.remove();

                    if (e.name === 'AbortError') {
                        window.aiutomaPromptQueue = [];
                        chatEl.insertAdjacentHTML('beforeend', '<div class="aiutoma-msg-error" style="background: #fff8e5; border-color: #f0c33c; color: #8a6d3b;"><strong>System:</strong> Task aborted by user.</div>');
                        chatEl.scrollTop = chatEl.scrollHeight;
                        restoreSendBtn();
                        return;
                    }

                    let errorMessage = e.message || 'Unknown error';
                    errorMessage = errorMessage.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
                    chatEl.insertAdjacentHTML('beforeend', '<div class="aiutoma-msg-error"><strong>Error:</strong><br>' + errorMessage + '</div>');
                    restoreSendBtn();
                    checkPromptQueue();

                    const isSiteError = e.status >= 500 || (e.message && e.message.includes('500'));

                    if (!isAutoRetry && isSiteError) {
                        if (!aiEnforceSafeMode) {
                            aiEnforceSafeMode = true;
                            const statusEl = document.getElementById('aiutoma-safemode-status');
                            if (statusEl) statusEl.innerText = 'Strict Safe Mode Enforced (Auto-Recovered)';
                            const toggleBtn = document.getElementById('aiutoma-toggle-safe-mode');
                            if (toggleBtn) {
                                toggleBtn.classList.add('aiutoma-safe-mode-active');
                                toggleBtn.dataset.active = "1";
                            }
                            const toggleUrl = window.aiutomaSettings.restUrl.replace('ai-chat', 'toggle-safe-mode') + (window.aiutomaSettings.hasDevExtension ? '?aiutoma_enforce_safe_mode=1' : '');
                            fetch(toggleUrl, {
                                method: 'POST',
                                headers: { 'X-WP-Nonce': window.aiutomaSettings.nonceRest, 'Content-Type': 'application/json' },
                                body: JSON.stringify({ force: 'enable' })
                            }).catch(() => { });
                        }

                        setTimeout(() => {
                            const safeActiveStr = window.aiutomaSettings.hasDevExtension ? ' Safe Mode has been activated.' : '';
                            const errorPrompt = "SYSTEM ALERT: The last action resulted in a Fatal Error (" + (e.message || 'Unknown error') + ")." + safeActiveStr + " Please use the wpab__ai__manage-debug tool to enable the debug log, find the error, and fix it.";
                            chatEl.insertAdjacentHTML('beforeend', '<div class="aiutoma-msg-user" style="background-color: #ffebe8; border-left: 4px solid #dc3232;"><strong>System Auto-Prompt:</strong><br>' + errorPrompt + '</div>');
                            chatEl.scrollTop = chatEl.scrollHeight;
                            doStep({
                                conversation_id: requestBody.conversation_id || window.aiutomaCurrentConversationId,
                                prompt: errorPrompt,
                                model: document.getElementById('aiutoma-playground-model') ? document.getElementById('aiutoma-playground-model').value : ''
                            }, window.aiutomaSettings.textAiThinking, [], true);
                        }, 1000);
                    }
                }
                chatEl.scrollTop = chatEl.scrollHeight;
                saveChatState();
            };

            const payload = {
                prompt: prompt,
                model: modelSelect ? modelSelect.value : '',
                fallback_models: fallbackModelsCheckbox ? fallbackModelsCheckbox.checked : false,
                system_info_context: (document.getElementById('aiutoma-include-system-info') && document.getElementById('aiutoma-include-system-info').checked && document.getElementById('aiutoma-system-info-context')) ? document.getElementById('aiutoma-system-info-context').value : '',
                session_context: document.getElementById('aiutoma-session-context') ? document.getElementById('aiutoma-session-context').value : '',
                permanent_context: document.getElementById('aiutoma-permanent-context') ? document.getElementById('aiutoma-permanent-context').value : '',
                object_type: window.aiutomaSettings.objectType || ''
            };

            const enableAbilitiesToggle = document.getElementById('aiutoma-enable-abilities-toggle');
            payload.enable_tools = enableAbilitiesToggle ? enableAbilitiesToggle.checked : true;

            if (payload.enable_tools) {
                const selectedAbilities = [];
                document.querySelectorAll('.aiutoma-ability-checkbox:checked').forEach(cb => {
                    selectedAbilities.push(cb.value);
                });
                payload.enabled_abilities = selectedAbilities;
            } else {
                payload.enabled_abilities = [];
            }

            const enableSkillsToggle = document.getElementById('aiutoma-enable-skills-toggle');
            payload.enable_skills = enableSkillsToggle ? enableSkillsToggle.checked : false;

            if (payload.enable_skills) {
                const selectedSkills = [];
                document.querySelectorAll('.aiutoma-skill-checkbox:checked').forEach(cb => {
                    selectedSkills.push(cb.value);
                });
                payload.enabled_skills = selectedSkills;
            } else {
                payload.enabled_skills = [];
            }

            let ragContext = '';
            document.querySelectorAll('.aiutoma-rag-checkbox:checked').forEach(cb => {
                const type = cb.dataset.type;
                const ta = document.getElementById('aiutoma-rag-context-' + type);
                if (ta && ta.value) {
                    ragContext += "\n--- RAG DATA: " + type.toUpperCase() + " ---\n" + ta.value + "\n";
                }
            });
            if (ragContext) {
                payload.rag_context = ragContext;
            }

            // Uncheck after first prompt to save tokens
            if (!window.aiutomaSessionRagChecked) window.aiutomaSessionRagChecked = [];
            document.querySelectorAll('.aiutoma-rag-checkbox:checked').forEach(cb => {
                if (!window.aiutomaSessionRagChecked.includes(cb.dataset.type)) {
                    window.aiutomaSessionRagChecked.push(cb.dataset.type);
                }
                cb.checked = false;
            });

            if (window.aiutomaCurrentConversationId) {
                payload.conversation_id = window.aiutomaCurrentConversationId;
            }
            await doStep(payload, window.aiutomaSettings.textAiThinking);
        });
    }
    // Clear backups
    const clearBackupsBtn = document.getElementById('aiutoma-clear-backups');
    if (clearBackupsBtn) {
        clearBackupsBtn.addEventListener('click', function () {
            if (!confirm('Are you sure you want to delete all temporary AI action backups? You will not be able to rollback recent actions anymore.')) return;

            const originalText = clearBackupsBtn.innerText;
            clearBackupsBtn.disabled = true;
            clearBackupsBtn.innerText = 'Clearing...';

            fetch(window.aiutomaSettings.restUrl.replace('/ai-chat', '/delete-ai-backups'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.aiutomaSettings.nonceRest
                },
                credentials: 'same-origin'
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('Backups cleared successfully.');
                        const container = document.getElementById('aiutoma-backups-container');
                        if (container) {
                            container.style.display = 'none';
                        }
                    } else {
                        alert('Failed to clear backups.');
                    }
                })
                .catch(e => {
                    console.error(e);
                    alert('Error clearing backups.');
                })
                .finally(() => {
                    clearBackupsBtn.disabled = false;
                    clearBackupsBtn.innerText = originalText;
                });
        });
    }

    // Rollback action delegate
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('aiutoma-rollback-btn')) {
            const btn = e.target;
            const backupId = btn.dataset.backupId;
            if (!confirm('Are you sure you want to rollback this action? This will restore the file/database to its exact state before this tool was executed.')) return;

            btn.disabled = true;
            btn.textContent = 'Rolling back...';

            fetch(window.aiutomaSettings.restUrl.replace('/ai-chat', '/rollback-ai-action'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.aiutomaSettings.nonceRest
                },
                body: JSON.stringify({ backup_id: backupId }),
                credentials: 'same-origin'
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        btn.textContent = '✅ Rolled Back';
                        btn.style.color = 'green';
                        btn.style.borderColor = 'green';
                    } else {
                        btn.disabled = false;
                        btn.textContent = '↩️ Rollback Action';
                        alert('Rollback failed: ' + (res.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error('Rollback error', err);
                    btn.disabled = false;
                    btn.textContent = '↩️ Rollback Action';
                    alert('Rollback failed.');
                });
        }
    });

    // Tool details toggle delegate
    document.addEventListener('click', function (e) {
        const toolHeader = e.target.closest('.aiutoma-tool-header-toggle');
        if (toolHeader) {
            e.preventDefault();
            const details = toolHeader.nextElementSibling;
            if (details && details.classList.contains('aiutoma-tool-details')) {
                const isHidden = window.getComputedStyle(details).display === 'none';
                details.style.display = isHidden ? 'block' : 'none';
            }
        }
    });

    const playgroundChatEl = document.getElementById('aiutoma-playground-chat');
    if (playgroundChatEl) {
        playgroundChatEl.addEventListener('click', function (e) {
            if (e.target.closest('.aiutoma-copy-btn')) {
                const btn = e.target.closest('.aiutoma-copy-btn');
                let textToCopy = btn.getAttribute('data-text');
                if (!textToCopy) {
                    const aiMsg = btn.closest('.aiutoma-msg-ai');
                    if (aiMsg) {
                        const clone = aiMsg.cloneNode(true);
                        const header = clone.querySelector('div');
                        if (header) header.remove();
                        textToCopy = clone.innerText.trim();
                    }
                }
                textToCopy = textToCopy || '';

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(textToCopy).then(() => {
                        const origHtml = btn.innerHTML;
                        btn.innerHTML = 'Copied!';
                        setTimeout(() => { btn.innerHTML = origHtml; }, 2000);
                    }).catch(err => {
                        console.error('Could not copy text: ', err);
                    });
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = textToCopy;
                    textarea.style.position = 'fixed';
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        const origHtml = btn.innerHTML;
                        btn.innerHTML = 'Copied!';
                        setTimeout(() => { btn.innerHTML = origHtml; }, 2000);
                    } catch (err) {
                        console.error('Fallback copy failed', err);
                    }
                    document.body.removeChild(textarea);
                }
            }
        });
    }

    // Token Estimator for Context
    function updateTokenEstimate() {
        let contextWords = 0;
        let ragWords = 0;

        // Context textareas
        document.querySelectorAll('.aiutoma-context-textarea').forEach(ta => {
            if (ta.value.trim() !== '') {
                // Check if it belongs to a checkbox
                let include = true;
                let isRag = false;
                if (ta.id === 'aiutoma-system-info-context') {
                    const cb = document.getElementById('aiutoma-include-system-info');
                    if (cb && !cb.checked) include = false;
                } else if (ta.id.startsWith('aiutoma-rag-context-')) {
                    isRag = true;
                    const type = ta.id.replace('aiutoma-rag-context-', '');
                    const cb = document.getElementById('aiutoma-include-rag-info-' + type);
                    if (cb && !cb.checked) include = false;
                }

                if (include) {
                    if (isRag) {
                        ragWords += ta.value.trim().split(/\s+/).length;
                    } else {
                        contextWords += ta.value.trim().split(/\s+/).length;
                    }
                }
            }
        });

        const contextEstimate = contextWords > 0 ? Math.ceil(contextWords / 0.75) : 0;
        const ragEstimate = ragWords > 0 ? Math.ceil(ragWords / 0.75) : 0;

        let abilitiesEstimate = 0;
        const enableAbilitiesToggle = document.getElementById('aiutoma-enable-abilities-toggle');
        if (enableAbilitiesToggle && enableAbilitiesToggle.checked) {
            const checkedAbilities = document.querySelectorAll('.aiutoma-ability-checkbox:checked').length;
            abilitiesEstimate = checkedAbilities * 110;
        }

        let skillsEstimate = 0;
        const enableSkillsToggle = document.getElementById('aiutoma-enable-skills-toggle');
        if (enableSkillsToggle && enableSkillsToggle.checked) {
            document.querySelectorAll('.aiutoma-skill-checkbox:checked').forEach(cb => {
                skillsEstimate += parseInt(cb.dataset.tokens || 0, 10);
            });
        }

        let baseDisplay = document.getElementById('aiutoma-token-estimate-display');
        if (!baseDisplay) {
            const contextCard = document.querySelector('.aiutoma-context-card summary h2');
            if (contextCard) {
                baseDisplay = document.createElement('span');
                baseDisplay.id = 'aiutoma-token-estimate-display';
                baseDisplay.style.cssText = 'float: right; font-size: 12px; font-weight: normal; color: #666; margin-top: 4px; padding-left: 10px;';
                contextCard.appendChild(baseDisplay);
            }
        }
        if (baseDisplay) {
            baseDisplay.innerHTML = `⚡ Est. Tokens: <strong>~${contextEstimate}</strong>`;
        }

        let skillsDisplay = document.getElementById('aiutoma-skills-token-estimate-display');
        if (!skillsDisplay) {
            const skillsCard = document.querySelector('.aiutoma-skills-card summary h2');
            if (skillsCard) {
                skillsDisplay = document.createElement('span');
                skillsDisplay.id = 'aiutoma-skills-token-estimate-display';
                skillsDisplay.style.cssText = 'float: right; font-size: 12px; font-weight: normal; color: #666; margin-top: 4px; padding-left: 10px;';
                skillsCard.appendChild(skillsDisplay);
            }
        }
        if (skillsDisplay) {
            skillsDisplay.innerHTML = `⚡ Est. Tokens: <strong>~${skillsEstimate}</strong>`;
            skillsDisplay.style.display = (enableSkillsToggle && enableSkillsToggle.checked) ? 'inline' : 'none';
        }

        let ragDisplay = document.getElementById('aiutoma-rag-token-estimate-display');
        if (!ragDisplay) {
            const ragCard = document.querySelector('.aiutoma-rag-card summary h2');
            if (ragCard) {
                ragDisplay = document.createElement('span');
                ragDisplay.id = 'aiutoma-rag-token-estimate-display';
                ragDisplay.style.cssText = 'float: right; font-size: 12px; font-weight: normal; color: #666; margin-top: 4px; padding-left: 10px;';
                ragCard.appendChild(ragDisplay);
            }
        }
        if (ragDisplay) {
            ragDisplay.innerHTML = `⚡ Est. Tokens: <strong>~${ragEstimate}</strong>`;
            ragDisplay.style.display = ragEstimate > 0 ? 'inline' : 'none';
        }

        let abilitiesDisplay = document.getElementById('aiutoma-abilities-token-estimate-display');
        if (!abilitiesDisplay) {
            const abilitiesCard = document.querySelector('.aiutoma-abilities-card summary h2');
            if (abilitiesCard) {
                abilitiesDisplay = document.createElement('span');
                abilitiesDisplay.id = 'aiutoma-abilities-token-estimate-display';
                abilitiesDisplay.style.cssText = 'float: right; font-size: 12px; font-weight: normal; color: #666; margin-top: 4px; padding-left: 10px;';
                abilitiesCard.appendChild(abilitiesDisplay);
            }
        }
        if (abilitiesDisplay) {
            abilitiesDisplay.innerHTML = `⚡ Est. Tokens: <strong>~${abilitiesEstimate}</strong>`;
            abilitiesDisplay.style.display = (enableAbilitiesToggle && enableAbilitiesToggle.checked) ? 'inline' : 'none';
        }
    }

    // Bind token estimate events
    document.querySelectorAll('.aiutoma-context-textarea').forEach(ta => ta.addEventListener('input', updateTokenEstimate));
    document.querySelectorAll('input[type="checkbox"][id^="aiutoma-include-"]').forEach(cb => cb.addEventListener('change', updateTokenEstimate));
    if (document.getElementById('aiutoma-enable-abilities-toggle')) {
        document.getElementById('aiutoma-enable-abilities-toggle').addEventListener('change', updateTokenEstimate);
    }
    const promptElToBind = document.getElementById('aiutoma-playground-prompt');
    if (promptElToBind) {
        promptElToBind.addEventListener('input', updateTokenEstimate);
    }
    const loadRagBtn = document.getElementById('aiutoma-load-rag-data');
    if (loadRagBtn) {
        loadRagBtn.addEventListener('click', async function () {
            loadRagBtn.disabled = true;
            loadRagBtn.innerText = 'Loading...';
            try {
                const res = await fetch(window.aiutomaSettings.ragUrl + '?t=' + Date.now(), {
                    headers: { 'X-WP-Nonce': window.aiutomaSettings.nonceRest }
                });
                if (!res.ok) throw new Error('Network response was not ok');
                const data = await res.json();

                let groupedRag = {};
                data.forEach(item => {
                    let type = item.post_type || 'other';
                    if (type === 'post') type = 'Posts';
                    else if (type === 'product') type = 'Products';
                    else if (type === 'user') type = 'Users';
                    else if (type === 'term') type = 'Terms';
                    else if (type === 'plugin') type = 'Plugins';
                    else type = type.charAt(0).toUpperCase() + type.slice(1);

                    if (!groupedRag[type]) groupedRag[type] = [];
                    groupedRag[type].push(item);
                });

                const container = document.getElementById('aiutoma-rag-container');
                container.innerHTML = '';
                container.style.display = 'block';

                if (Object.keys(groupedRag).length === 0) {
                    container.innerHTML = '<p>No RAG data found.</p>';
                    return;
                }

                Object.entries(groupedRag).forEach(([type, items]) => {
                    const safeType = type.toLowerCase().replace(/ /g, '_');

                    const colDiv = document.createElement('div');
                    colDiv.className = 'aiutoma-context-col';

                    const label = document.createElement('label');
                    label.setAttribute('for', 'aiutoma-rag-context-' + safeType);
                    label.innerHTML = `<strong>${type}</strong>
                        <input type="checkbox" class="aiutoma-rag-checkbox" data-type="${safeType}" id="aiutoma-include-rag-info-${safeType}" value="1" style="margin-left:5px; margin-top:-2px;">
                        <span style="font-size:11px; font-weight:normal;">Pass with prompt</span>`;

                    const textarea = document.createElement('textarea');
                    textarea.id = 'aiutoma-rag-context-' + safeType;
                    textarea.className = 'aiutoma-context-textarea';
                    textarea.readOnly = true;
                    textarea.value = JSON.stringify(items, null, 2);

                    colDiv.appendChild(label);
                    colDiv.appendChild(textarea);
                    container.appendChild(colDiv);

                    textarea.addEventListener('input', updateTokenEstimate);
                    document.getElementById('aiutoma-include-rag-info-' + safeType).addEventListener('change', updateTokenEstimate);
                });
                updateTokenEstimate();
            } catch (err) {
                console.error(err);
                loadRagBtn.innerText = 'Error loading data';
                loadRagBtn.disabled = false;
            }
        });
    }

    updateTokenEstimate(); // Initial call
    
    // Session auto-restore on page load: hydrate context settings if provided by ui.php
    if (window.aiutomaSessionContext && typeof window.aiutomaSessionContext === 'object' && Object.keys(window.aiutomaSessionContext).length > 0) {
        const ctx = window.aiutomaSessionContext;
        if (ctx.model) {
            const modelSelect = document.getElementById('aiutoma-playground-model');
            if (modelSelect) {
                modelSelect.value = ctx.model;
                modelSelect.dispatchEvent(new Event('change'));
            }
        }
        if (ctx.fallback_models !== undefined) {
            const fallbackCb = document.getElementById('aiutoma-fallback-models');
            if (fallbackCb) fallbackCb.checked = !!ctx.fallback_models;
        }
        if (ctx.enable_tools !== undefined) {
            const toggle = document.getElementById('aiutoma-enable-abilities-toggle');
            if (toggle) {
                toggle.checked = !!ctx.enable_tools;
                toggle.dispatchEvent(new Event('change'));
            }
        }
        if (Array.isArray(ctx.enabled_abilities)) {
            document.querySelectorAll('.aiutoma-ability-checkbox').forEach(cb => {
                cb.checked = ctx.enabled_abilities.includes(cb.value);
            });
        }
        if (ctx.enable_skills !== undefined) {
            const toggle = document.getElementById('aiutoma-enable-skills-toggle');
            if (toggle) {
                toggle.checked = !!ctx.enable_skills;
                toggle.dispatchEvent(new Event('change'));
            }
        }
        if (Array.isArray(ctx.enabled_skills)) {
            document.querySelectorAll('.aiutoma-skill-checkbox').forEach(cb => {
                cb.checked = ctx.enabled_skills.includes(cb.value);
            });
        }
        if (ctx.system_info !== undefined) {
            const sysInfo = document.getElementById('aiutoma-include-system-info');
            if (sysInfo) sysInfo.checked = !!ctx.system_info;
        }
        if (ctx.session_context !== undefined) {
            const sessCtx = document.getElementById('aiutoma-session-context');
            if (sessCtx) sessCtx.value = ctx.session_context;
        }
        if (ctx.permanent_context !== undefined) {
            const permCtx = document.getElementById('aiutoma-permanent-context');
            if (permCtx) permCtx.value = ctx.permanent_context;
        }
        if (Array.isArray(ctx.rag_types)) {
            window.aiutomaSessionRagChecked = ctx.rag_types;
            document.querySelectorAll('.aiutoma-rag-checkbox').forEach(cb => {
                cb.checked = ctx.rag_types.includes(cb.dataset.type);
            });
        }
        updateTokenEstimate();
    }
});
