document.addEventListener('DOMContentLoaded', function() {
    // Tab switching logic
    var tabs = document.querySelectorAll('#aiutoma-mcp-tabs .nav-tab');
    var contents = document.querySelectorAll('.aiutoma-mcp-tab-content');
    if (tabs.length > 0 && contents.length > 0) {
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                tabs.forEach(function(t) {
                    t.classList.remove('nav-tab-active');
                });
                contents.forEach(function(c) {
                    c.style.display = 'none';
                });
                tab.classList.add('nav-tab-active');
                var target = document.getElementById(tab.getAttribute('data-target'));
                if (target) {
                    target.style.display = 'block';
                }
            });
        });
    }

    // Clipboard copy logic
    var copyButtons = document.querySelectorAll('.aiutoma-copy-btn');
    copyButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = this.getAttribute('data-copy-target');
            var textToCopy = '';
            
            if (targetId) {
                var targetElement = document.getElementById(targetId);
                if (targetElement) {
                    textToCopy = targetElement.value || targetElement.innerText;
                }
            } else {
                textToCopy = this.getAttribute('data-copy-text');
                if (!textToCopy) {
                    // Try to get next sibling text (for code blocks)
                    var next = this.nextElementSibling;
                    if (next) {
                        textToCopy = next.innerText || next.textContent;
                    } else {
                        var prev = this.previousElementSibling;
                        if (prev) {
                            textToCopy = prev.innerText || prev.textContent;
                        }
                    }
                }
            }

            if (textToCopy) {
                var btnElement = this;
                var copySuccess = function() {
                    var originalText = btnElement.innerText;
                    btnElement.innerText = 'Copied!';
                    setTimeout(() => {
                        btnElement.innerText = originalText;
                    }, 2000);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(textToCopy).then(copySuccess).catch(function(err) {
                        console.error('Failed to copy: ', err);
                        fallbackCopyTextToClipboard(textToCopy, copySuccess);
                    });
                } else {
                    fallbackCopyTextToClipboard(textToCopy, copySuccess);
                }
            }
        });
    });

    function fallbackCopyTextToClipboard(text, onSuccess) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            var successful = document.execCommand('copy');
            if (successful && typeof onSuccess === 'function') {
                onSuccess();
            }
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
        }
        document.body.removeChild(textArea);
    }

    // Prompt Credential Customizer (Antigravity / Cursor)
    var userSelect = document.getElementById('aiutoma_prompt_user_select');
    var passInput = document.getElementById('aiutoma_prompt_app_password');
    var clearBtn = document.getElementById('aiutoma_prompt_clear_pass');
    var toggleBtn = document.getElementById('aiutoma_prompt_toggle_pass');
    var profileLink = document.getElementById('aiutoma_prompt_user_app_url');
    var statusMsg = document.getElementById('aiutoma_prompt_status_msg');
    var authBasic = document.getElementById('aiutoma_prompt_auth_basic');
    var authCurl = document.getElementById('aiutoma_prompt_auth_curl');
    var authTitle = document.getElementById('aiutoma_prompt_auth_title');
    var authLine = document.getElementById('aiutoma_prompt_auth_line');
    var apiKeyLine = document.getElementById('aiutoma_prompt_api_key_line');
    var customizer = document.querySelector('.aiutoma-prompt-customizer');
    var mcpToken = customizer ? customizer.getAttribute('data-token') : '';
    var mcpJsonContent = document.getElementById('aiutoma_mcp_json_content');
    var mcpDownloadBtn = document.getElementById('aiutoma_download_mcp_json_btn');
    var mcpBaseDownloadUrl = mcpDownloadBtn ? mcpDownloadBtn.getAttribute('href') : '';

    if (userSelect && passInput && authBasic && authCurl) {
        function escapeHtml(str) {
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function safeBtoa(str) {
            try {
                if (typeof TextEncoder !== 'undefined') {
                    var bytes = new TextEncoder().encode(str);
                    var binString = '';
                    for (var i = 0; i < bytes.length; i++) {
                        binString += String.fromCharCode(bytes[i]);
                    }
                    return btoa(binString);
                }
                return btoa(unescape(encodeURIComponent(str)));
            } catch (e) {
                return btoa(str);
            }
        }

        function updatePromptCredentials() {
            var selectedOption = userSelect.options[userSelect.selectedIndex];
            var username = (selectedOption && selectedOption.value) ? selectedOption.value : 'user';
            var appUrl = selectedOption ? selectedOption.getAttribute('data-app-url') : '';

            if (profileLink && appUrl) {
                profileLink.href = appUrl;
            }

            var password = passInput.value.trim();
            if (clearBtn) {
                clearBtn.style.display = password ? 'inline-block' : 'none';
            }

            if (password) {
                var credentials = username + ':' + password;
                var encoded = safeBtoa(credentials);

                if (authTitle) {
                    authTitle.innerHTML = '<strong>Authentication Options:</strong><br>';
                }
                if (authLine) {
                    authLine.style.display = 'inline';
                }
                if (apiKeyLine) {
                    apiKeyLine.innerHTML = '- <em>API Key:</em> <code>X-MCP-API-Key: ' + escapeHtml(mcpToken) + '</code>';
                }

                authBasic.textContent = 'Authorization: Basic ' + encoded;
                authCurl.textContent = 'curl -u "' + username + ':' + password + '"';

                authBasic.style.backgroundColor = '#d4edda';
                authBasic.style.color = '#155724';
                authBasic.style.padding = '2px 5px';
                authBasic.style.borderRadius = '3px';

                authCurl.style.backgroundColor = '#d4edda';
                authCurl.style.color = '#155724';
                authCurl.style.padding = '2px 5px';
                authCurl.style.borderRadius = '3px';

                if (mcpJsonContent) {
                    var serverKey = mcpJsonContent.getAttribute('data-server-key') || 'aiutoma';
                    var restUrl = mcpJsonContent.getAttribute('data-rest-url') || '';
                    var config = {
                        "servers": {}
                    };
                    config.servers[serverKey] = {
                        "type": "http",
                        "url": restUrl,
                        "headers": {
                            "Authorization": "Basic " + encoded,
                            "X-MCP-API-Key": mcpToken
                        }
                    };
                    mcpJsonContent.textContent = JSON.stringify(config, null, 4);
                }
                if (mcpDownloadBtn && mcpBaseDownloadUrl) {
                    var cleanUrl = mcpBaseDownloadUrl.replace(/&auth=[^&]*/, '');
                    var sep = cleanUrl.indexOf('?') !== -1 ? '&' : '?';
                    mcpDownloadBtn.href = cleanUrl + sep + 'auth=' + encodeURIComponent(encoded);
                }

                if (statusMsg) {
                    statusMsg.innerHTML = '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong style="color: #1e7e34;">Ready!</strong> Credentials updated for <code>' + escapeHtml(username) + '</code> across Gemini prompt and mcp.json.';
                }
            } else {
                if (authTitle) {
                    authTitle.innerHTML = '<strong>Authentication Options:</strong><br>';
                }
                if (authLine) {
                    authLine.style.display = 'inline';
                }
                if (apiKeyLine) {
                    apiKeyLine.innerHTML = '- <em>API Key:</em> <code>X-MCP-API-Key: ' + escapeHtml(mcpToken) + '</code>';
                }
                if (statusMsg) {
                    statusMsg.innerHTML = '<span class="dashicons dashicons-info"></span> Type or paste an Application Password to generate ready-to-use Basic Auth headers in the prompt and mcp.json below.';
                }

                authBasic.textContent = 'Authorization: Basic <base64(' + username + ':app_password)>';
                authCurl.textContent = 'curl -u "' + username + ':your_application_password"';

                authBasic.style.backgroundColor = '';
                authBasic.style.color = '';
                authBasic.style.padding = '';
                authBasic.style.borderRadius = '';

                authCurl.style.backgroundColor = '';
                authCurl.style.color = '';
                authCurl.style.padding = '';
                authCurl.style.borderRadius = '';

                if (mcpJsonContent) {
                    var sKey = mcpJsonContent.getAttribute('data-server-key') || 'aiutoma';
                    var rUrl = mcpJsonContent.getAttribute('data-rest-url') || '';
                    var defConfig = {
                        "servers": {}
                    };
                    defConfig.servers[sKey] = {
                        "type": "http",
                        "url": rUrl,
                        "headers": {
                            "Authorization": "Basic <base64(" + username + ":application_password)>",
                            "X-MCP-API-Key": mcpToken
                        }
                    };
                    mcpJsonContent.textContent = JSON.stringify(defConfig, null, 4);
                }
                if (mcpDownloadBtn && mcpBaseDownloadUrl) {
                    mcpDownloadBtn.href = mcpBaseDownloadUrl.replace(/&auth=[^&]*/, '');
                }
            }
        }

        userSelect.addEventListener('change', updatePromptCredentials);
        passInput.addEventListener('input', updatePromptCredentials);

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                passInput.value = '';
                passInput.focus();
                updatePromptCredentials();
            });
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                var icon = toggleBtn.querySelector('.dashicons');
                if (passInput.type === 'password') {
                    passInput.type = 'text';
                    if (icon) {
                        icon.classList.remove('dashicons-visibility');
                        icon.classList.add('dashicons-hidden');
                    }
                } else {
                    passInput.type = 'password';
                    if (icon) {
                        icon.classList.remove('dashicons-hidden');
                        icon.classList.add('dashicons-visibility');
                    }
                }
            });
        }

        // Initialize state
        updatePromptCredentials();
    }
});

