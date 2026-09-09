// Aiutoma Chatbot Admin JS
jQuery(document).ready(function($) {
    if (typeof aiutomaChatbotData === 'undefined') return;

    // --- SETTINGS PAGE LOGIC ---
    if (aiutomaChatbotData.isSettingsPage) {
        // Tab switching
        const tabs = document.querySelectorAll("#aiutoma-chatbot-tabs .nav-tab");
        const contents = document.querySelectorAll(".aiutoma-chatbot-tab-content");
        if (tabs.length > 0) {
            tabs.forEach(tab => {
                tab.addEventListener("click", function(e) {
                    e.preventDefault();
                    tabs.forEach(t => t.classList.remove("nav-tab-active"));
                    this.classList.add("nav-tab-active");
                    const target = this.getAttribute("href").replace("#", "tab-");
                    contents.forEach(c => c.style.display = "none");
                    const targetEl = document.getElementById(target);
                    if (targetEl) targetEl.style.display = "block";
                });
            });
        }

        // Abilities token estimate
        const cbs = document.querySelectorAll('.aiutoma-chatbot-ability-cb');
        const display = document.getElementById('aiutoma-chatbot-total-tokens-estimate');
        if (display && cbs.length > 0) {
            function updateEstimate() {
                const count = document.querySelectorAll('.aiutoma-chatbot-ability-cb:checked').length;
                display.innerText = count * 110;
            }
            cbs.forEach(cb => cb.addEventListener('change', updateEstimate));
            updateEstimate();
        }

        // Export/Import
        const exportBtn = document.getElementById('aiutoma_export_btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                const data = JSON.parse(exportBtn.getAttribute('data-export'));
                const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'aiutoma-chatbot-config.json';
                a.click();
                URL.revokeObjectURL(url);
            });
        }

        const importBtn = document.getElementById('aiutoma_import_btn');
        const importFile = document.getElementById('aiutoma_import_file');
        if (importBtn && importFile) {
            importBtn.addEventListener('click', function() {
                importFile.click();
            });
            importFile.addEventListener('change', function(e) {
                if (!e.target.files.length) return;
                const reader = new FileReader();
                reader.onload = function(evt) {
                    try {
                        const config = JSON.parse(evt.target.result);
                        
                        if (config.model) {
                            const modelSelect = document.querySelector('select[name="aiutoma_chatbot_model"]');
                            if (modelSelect) {
                                modelSelect.value = config.model;
                                $(modelSelect).trigger('change');
                            }
                        }
                        
                        if (config.fallback_models && Array.isArray(config.fallback_models)) {
                            const fallbackSelect = document.querySelector('select[name="aiutoma_chatbot_fallback_models[]"]');
                            if (fallbackSelect) {
                                Array.from(fallbackSelect.options).forEach(opt => {
                                    opt.selected = config.fallback_models.includes(opt.value);
                                });
                                $(fallbackSelect).trigger('change');
                            }
                        }
                        
                        e.target.value = '';
                        alert(aiutomaChatbotData.textImportSuccess);
                    } catch (err) {
                        alert(aiutomaChatbotData.textImportError);
                    }
                };
                reader.readAsText(e.target.files[0]);
            });
        }

        // RAG toggles
        const ragToggle = document.getElementById('aiutoma_chatbot_use_rag');
        const dependentRows = document.querySelectorAll('.aiutoma-rag-dependent');
        
        if (ragToggle) {
            function updateRagVisibility() {
                dependentRows.forEach(row => {
                    row.style.display = ragToggle.checked ? '' : 'none';
                });
            }
            ragToggle.addEventListener('change', updateRagVisibility);
            updateRagVisibility();
        }

        // Select2
        if ($.fn.select2) {
            $('select[name="aiutoma_chatbot_model"]').select2({ width: '350px' });
            $('select[name="aiutoma_chatbot_fallback_models[]"]').select2({ width: '350px' });
        }
    }

    // --- LOGS PAGE LOGIC ---
    if (aiutomaChatbotData.isLogsPage) {
        // Summarize Session
        $('#aiutoma_summarize_session_btn').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var sessionId = btn.data('session');
            btn.prop('disabled', true).text(aiutomaChatbotData.textGenerating);
            $('#aiutoma_session_summary_result').hide().html('');
            
            $.ajax({
                url: aiutomaChatbotData.restSummarizeUrl,
                method: 'POST',
                data: { session_id: sessionId },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', aiutomaChatbotData.nonce);
                },
                success: function(response) {
                    btn.prop('disabled', false).text(aiutomaChatbotData.textSummarize);
                    if (response.success && response.summary) {
                        $('#aiutoma_session_summary_result').html('<strong>' + aiutomaChatbotData.textSessionDigest + '</strong><br>' + response.summary).slideDown();
                    } else {
                        alert(response.message || 'Error summarizing session.');
                    }
                },
                error: function() {
                    btn.prop('disabled', false).text(aiutomaChatbotData.textSummarize);
                    alert(aiutomaChatbotData.textCommError);
                }
            });
        });

        // Manual Mode Toggle
        $('#aiutoma_manual_mode_toggle').on('change', function() {
            var isChecked = $(this).is(':checked');
            var sessionId = $(this).data('session');
            if (isChecked) {
                $('#aiutoma_operator_chat_area').slideDown();
            } else {
                $('#aiutoma_operator_chat_area').slideUp();
            }
            
            $.ajax({
                url: aiutomaChatbotData.restToggleManualUrl,
                method: 'POST',
                data: { session_id: sessionId, manual: isChecked ? 1 : 0 },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', aiutomaChatbotData.nonce);
                }
            });
        });
        
        // Operator Send Message
        $('#aiutoma_operator_send_btn').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var sessionId = btn.data('session');
            var msg = $('#aiutoma_operator_message').val().trim();
            if (!msg) return;
            
            btn.prop('disabled', true).text(aiutomaChatbotData.textSending);
            $.ajax({
                url: aiutomaChatbotData.restOperatorSendUrl,
                method: 'POST',
                data: { session_id: sessionId, message: msg },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', aiutomaChatbotData.nonce);
                },
                success: function(response) {
                    btn.prop('disabled', false).text(aiutomaChatbotData.textSendMessage);
                    if (response.success) {
                        $('#aiutoma_operator_message').val('');
                        // Reload just the container via AJAX
                        $.get(window.location.href, function(html) {
                            var newContainer = $(html).find('#aiutoma_chat_history_container');
                            if (newContainer.length) {
                                $('#aiutoma_chat_history_container').replaceWith(newContainer);
                            }
                        });
                    } else {
                        alert(response.message || 'Error sending message.');
                    }
                },
                error: function() {
                    btn.prop('disabled', false).text(aiutomaChatbotData.textSendMessage);
                    alert(aiutomaChatbotData.textCommError);
                }
            });
        });
        
        // Simple Polling for backend view to see live user messages
        if (aiutomaChatbotData.sessionId) {
            setInterval(function() {
                var lastDateStr = $('.aiutoma-chat-message-row').last().data('date');
                if (!lastDateStr) return;
                
                $.ajax({
                    url: aiutomaChatbotData.restPollUrl,
                    method: 'POST',
                    data: { session_id: aiutomaChatbotData.sessionId, last_time: lastDateStr },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', aiutomaChatbotData.nonce);
                    },
                    success: function(response) {
                        if (response.success && response.messages && response.messages.length > 0) {
                            $.get(window.location.href, function(html) {
                                var newContainer = $(html).find('#aiutoma_chat_history_container');
                                if (newContainer.length) {
                                    $('#aiutoma_chat_history_container').replaceWith(newContainer);
                                }
                            });
                        }
                    }
                });
            }, 5000);
        }

        // New activity alert
        if ($('.wp-header-end').length) {
            $('#aiutoma-new-activity-alert').insertAfter('.wp-header-end');
        } else {
            $('#aiutoma-new-activity-alert').prependTo('.wrap');
        }
        
        var lastCheck = aiutomaChatbotData.lastCheckTime;
        setInterval(function() {
            if ($('#aiutoma-new-activity-alert').is(':visible')) {
                return;
            }
            
            $.ajax({
                url: aiutomaChatbotData.restCheckNewActivityUrl,
                method: 'POST',
                data: { last_check: lastCheck },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', aiutomaChatbotData.nonce);
                },
                success: function(response) {
                    if (response.success && response.has_new) {
                        $('#aiutoma-new-activity-alert').fadeIn();
                    }
                }
            });
        }, 10000);
    }
});
