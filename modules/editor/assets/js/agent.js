/**
 * Aiutoma Editor Agent
 *
 * Original uncompiled source code written in vanilla JavaScript & jQuery.
 * This file is authored directly and is not generated, compiled, or minified by any build tool.
 *
 * Public repository: https://github.com/frafish/aiutoma
 *
 * @package Aiutoma
 * @license GPL-3.0-or-later
 */

jQuery(document).ready(function($) {
    const $chatbot = $('#aiutoma-agent-chatbot');
    const $header = $('#aiutoma-agent-header');
    const $body = $('#aiutoma-agent-body');
    const $toggleBtn = $('#aiutoma-agent-toggle span');
    const $messages = $('#aiutoma-agent-messages');
    const $prompt = $('#aiutoma-agent-prompt');
    const $sendBtn = $('#aiutoma-agent-send');
    
    let isProcessing = false;
    let conversationId = '';
    
    let isDragging = false;
    let hasDragged = false;
    let dragStartX, dragStartY;
    let initialX, initialY;

    const isSidebarMode = (typeof aiutomaAgentData !== 'undefined' && aiutomaAgentData.uiMode === 'sidebar');

    $header.on('mousedown', function(e) {
        if ($chatbot.hasClass('aiutoma-agent-in-sidebar')) return;
        if ($(e.target).closest('button').length) return;
        
        isDragging = true;
        hasDragged = false;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        
        let rect = $chatbot[0].getBoundingClientRect();
        initialX = rect.left;
        initialY = rect.top;
        
        $(document).on('mousemove.aiutomadrag', function(e) {
            if (!isDragging) return;
            const dx = e.clientX - dragStartX;
            const dy = e.clientY - dragStartY;
            
            if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                if (!hasDragged) {
                    hasDragged = true;
                    $chatbot.css({
                        transition: 'none',
                        right: 'auto',
                        bottom: 'auto',
                        left: initialX + 'px',
                        top: initialY + 'px',
                        margin: 0
                    });
                }
                
                let newLeft = initialX + dx;
                let newTop = initialY + dy;
                
                const maxX = window.innerWidth - $chatbot.outerWidth();
                const maxY = window.innerHeight - $chatbot.outerHeight();
                
                newLeft = Math.max(0, Math.min(newLeft, maxX));
                newTop = Math.max(0, Math.min(newTop, maxY));
                
                $chatbot.css({
                    left: newLeft + 'px',
                    top: newTop + 'px'
                });
            }
        });
        
        $(document).on('mouseup.aiutomadrag', function(e) {
            isDragging = false;
            $(document).off('mousemove.aiutomadrag mouseup.aiutomadrag');
            if (hasDragged) {
                setTimeout(() => hasDragged = false, 100);
                
                // Smart anchoring: bind to closest edges so it expands inward
                let rect = $chatbot[0].getBoundingClientRect();
                let ww = window.innerWidth;
                let wh = window.innerHeight;
                
                let css = {};
                
                if (rect.left > ww / 2) {
                    css.right = (ww - rect.right) + 'px';
                    css.left = 'auto';
                } else {
                    css.left = rect.left + 'px';
                    css.right = 'auto';
                }
                
                if (rect.top > wh / 2) {
                    css.bottom = (wh - rect.bottom) + 'px';
                    css.top = 'auto';
                } else {
                    css.top = rect.top + 'px';
                    css.bottom = 'auto';
                }
                
                $chatbot.css(css);
            }
            $chatbot.css('transition', 'width 0.3s ease, border-radius 0.3s ease');
        });
    });

    $header.on('click', function(e) {
        if ($chatbot.hasClass('aiutoma-agent-in-sidebar')) return;
        if (hasDragged) return;
        if ($(e.target).closest('#aiutoma-agent-settings-toggle').length) return;
        
        if ($body.is(':visible')) {
            $body.slideUp(300);
            $('#aiutoma-agent-model-area').slideUp(300);
            $toggleBtn.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            setTimeout(() => $chatbot.addClass('aiutoma-agent-closed'), 300);
        } else {
            $chatbot.removeClass('aiutoma-agent-closed');
            $body.slideDown(300);
            $toggleBtn.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            $prompt.focus();
        }
    });

    $('#aiutoma-agent-settings-toggle').on('click', function(e) {
        e.stopPropagation();
        $('#aiutoma-agent-model-area').slideToggle(300);
        if (!$body.is(':visible')) {
            $chatbot.removeClass('aiutoma-agent-closed');
            $body.slideDown(300);
            $toggleBtn.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        }
    });

    const modelSelect = document.getElementById('aiutoma-agent-model-select');
    if (modelSelect) {
        fetch(aiutomaAgentData.rest_url.replace('/ai-chat', '/ai-models'), {
            headers: { 'X-WP-Nonce': aiutomaAgentData.nonce }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.models) {
                modelSelect.innerHTML = '';
                if (typeof data.models === 'object' && Object.keys(data.models).length > 0) {
                    Object.entries(data.models).forEach(([groupName, groupModels]) => {
                        const optgroup = document.createElement('optgroup');
                        optgroup.label = groupName;
                        const sortedIds = Object.keys(groupModels).sort();
                        sortedIds.forEach(id => {
                            const opt = document.createElement('option');
                            opt.value = id;
                            opt.textContent = groupModels[id];
                            if (id === aiutomaAgentData.preferredModel) {
                                opt.selected = true;
                            }
                            optgroup.appendChild(opt);
                        });
                        modelSelect.appendChild(optgroup);
                    });
                }
                if ($.fn.select2) {
                    $(modelSelect).select2({ width: '100%', dropdownParent: $('#aiutoma-agent-model-area') });
                }
            } else {
                modelSelect.innerHTML = '<option value="">Failed to load models</option>';
            }
        })
        .catch(err => {
            modelSelect.innerHTML = '<option value="">Error loading models</option>';
        });
    }

    function addMessage(text, sender) {
        if (sender === 'ai') {
            text = text.replace(/\n/g, '<br>');
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
        }
        const $msg = $('<div>').addClass('aiutoma-agent-msg').addClass('aiutoma-agent-' + sender);
        $msg.html(text);
        $messages.append($msg);
        $messages.scrollTop($messages[0].scrollHeight);
    }

    let lastSelectedBlockClientIds = [];
    if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
        wp.data.subscribe(() => {
            const blockEditor = wp.data.select('core/block-editor');
            if (blockEditor) {
                const selected = blockEditor.getSelectedBlockClientIds();
                lastSelectedBlockClientIds = selected || [];
            }
        });
    }

    function buildLiveBlockStructure(blocks, parentPath = '') {
        return (blocks || []).map((block, index) => {
            const path = parentPath ? `${parentPath}.${index}` : `${index}`;
            return {
                client_id: block.clientId,
                path,
                name: block.name,
                attrs: block.attributes || {},
                child_count: (block.innerBlocks || []).length,
                inner_blocks: buildLiveBlockStructure(block.innerBlocks || [], path)
            };
        });
    }

    function findLiveBlockPath(blocks, targetClientId, parentPath = '') {
        for (let index = 0; index < (blocks || []).length; index++) {
            const block = blocks[index];
            const path = parentPath ? `${parentPath}.${index}` : `${index}`;
            if (block.clientId === targetClientId) return path;

            const childPath = findLiveBlockPath(block.innerBlocks || [], targetClientId, path);
            if (childPath) return childPath;
        }
        return null;
    }

    function gatherContext() {
        if (aiutomaAgentData.screen === 'theme-editor' || aiutomaAgentData.screen === 'plugin-editor') {
            let context = "I am on the screen: " + aiutomaAgentData.screen + "\n";
            const currentFile = $('.wp-theme-editor-file-info, .wp-plugin-editor-file-info').text().trim() || $('#file').val() || 'unknown';
            context += "Current Editing File: " + currentFile + "\n";
            
            const passTheme = document.getElementById('aiutoma-agent-pass-theme');
            if (passTheme && passTheme.checked && typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
                try {
                    const settings = wp.data.select('core/block-editor').getSettings();
                    if (settings) {
                        let themeContext = "THEME STYLES (theme.json):\n";
                        if (settings.colors && settings.colors.length > 0) {
                            themeContext += "- Colors: " + settings.colors.map(c => `${c.name} (has-${c.slug}-color)`).join(", ") + "\n";
                        }
                        if (settings.fontSizes && settings.fontSizes.length > 0) {
                            themeContext += "- Font Sizes: " + settings.fontSizes.map(f => `${f.name} (has-${f.slug}-font-size)`).join(", ") + "\n";
                        }
                        if (settings.spacingSizes && settings.spacingSizes.length > 0) {
                            themeContext += "- Spacing Sizes: " + settings.spacingSizes.map(s => `${s.name} (${s.slug})`).join(", ") + "\n";
                        }
                        context += themeContext + "\n";
                    }
                } catch(e) {}
            }
            
            let cmInstance = $('.CodeMirror')[0]?.CodeMirror || (wp && wp.codeEditor && wp.codeEditor.defaultCodeEditor && wp.codeEditor.defaultCodeEditor.codemirror);
            let content = '';
            if (cmInstance) {
                content = cmInstance.getValue();
            } else {
                content = $('#newcontent').val() || '';
            }
            if (content) {
                context += "Current File Content:\n" + content + "\n\n" +
                    "CRITICAL INSTRUCTION: You are assisting with editing raw code.\n" +
                    "To provide modified code, return ONLY the full updated code wrapped in a markdown code block (e.g. ```php, ```css, etc).\n" +
                    "Do NOT use partial snippets. You MUST ALWAYS return the original file in its ENTIRETY, modified where requested, " +
                    "adding appropriate comments to explain your changes.\n" +
                    "The code inside this block will completely replace the content of the current file in the editor.\n";
            }
            return context;
        }

        let context = "I am on the screen: " + aiutomaAgentData.screen + "\n";
        
        // Grab form inputs generically
        const title = $('#title').val() || $('input[name="name"]').val() || '';
        if (title) context += "Title/Name: " + title + "\n";
        
        // Attempt to get Gutenberg content if available
        let content = '';
        if (window.elementor) {
            context += [
                "CRITICAL INSTRUCTION: You are interacting directly with the active Elementor Editor.",
                "To INSERT or APPEND new widgets or sections, you MUST output a JSON representation of the Elementor models inside an ```elementor-insert code block.",
                "Do NOT use standard ```json blocks. The JSON must be an array of Elementor models or a single model. Example:",
                "```elementor-insert",
                "{",
                '  "id": "abc1234",',
                '  "elType": "section",',
                '  "elements": [',
                '    {',
                '      "id": "def5678",',
                '      "elType": "column",',
                '      "elements": [',
                '        {',
                '          "id": "xyz9876",',
                '          "elType": "widget",',
                '          "widgetType": "heading",',
                '          "settings": { "title": "Hello World" }',
                '        }',
                '      ]',
                '    }',
                '  ]',
                "}",
                "```",
                "CRITICAL WIDGET PROPERTIES:",
                "- For 'text-editor' widgets, the HTML text MUST be placed in `settings.editor` (e.g. `\"settings\": { \"editor\": \"<p>My text</p>\" }`). Do not use 'content' or 'text'.",
                "- For 'image' widgets, use `settings.image.url` (e.g. `\"settings\": { \"image\": { \"url\": \"https://example.com/image.jpg\" } }`).",
                "To completely REPLACE the entire page content, use an ```elementor-replace code block.",
                "The system will parse this JSON and inject it into the editor in real-time.",
                ""
            ].join("\n");
        } else if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
            const blockEditorData = wp.data.select('core/block-editor');
            const blocks = blockEditorData.getBlocks();

            context += "LIVE GUTENBERG CANVAS (current browser state, including unsaved changes):\n";
            context += "The following content is the authoritative state of the active editor. Do not use saved post content when interpreting this request.\n\n";
            
            const passTheme = document.getElementById('aiutoma-agent-pass-theme');
            if (passTheme && passTheme.checked) {
                const settings = blockEditorData.getSettings();
                if (settings) {
                    let themeContext = "THEME STYLES (theme.json):\n";
                    if (settings.colors && settings.colors.length > 0) {
                        themeContext += "- Colors: " + settings.colors.map(c => `${c.name} (has-${c.slug}-color)`).join(", ") + "\n";
                    }
                    if (settings.fontSizes && settings.fontSizes.length > 0) {
                        themeContext += "- Font Sizes: " + settings.fontSizes.map(f => `${f.name} (has-${f.slug}-font-size)`).join(", ") + "\n";
                    }
                    if (settings.spacingSizes && settings.spacingSizes.length > 0) {
                        themeContext += "- Spacing Sizes: " + settings.spacingSizes.map(s => `${s.name} (${s.slug})`).join(", ") + "\n";
                    }
                    context += themeContext + "\n";
                }
            }

            
            if (blocks && blocks.length > 0) {
                content = wp.blocks.serialize(blocks);
            }
            context += "LIVE PAGE STRUCTURE (client_id values are valid only in this browser session; use path values when reasoning about structure):\n";
            context += JSON.stringify(buildLiveBlockStructure(blocks)) + "\n\n";
            if (content) context += "LIVE PAGE CONTENT:\n" + content + "\n\n";
            
            let selectedBlocks = [];
            if (lastSelectedBlockClientIds.length > 0) {
                selectedBlocks = lastSelectedBlockClientIds.map(id => wp.data.select('core/block-editor').getBlock(id)).filter(Boolean);
            }
            
            if (selectedBlocks && selectedBlocks.length > 0) {
                const selectedContent = wp.blocks.serialize(selectedBlocks);
                const selectedDetails = selectedBlocks.map(block => ({
                    client_id: block.clientId,
                    path: findLiveBlockPath(blocks, block.clientId),
                    name: block.name,
                    attrs: block.attributes || {}
                }));
                context += "CURRENTLY SELECTED BLOCKS (The user has highlighted these blocks in the live editor. If the user asks to change or rewrite this, refer to these blocks):\n";
                context += JSON.stringify(selectedDetails) + "\n" + selectedContent + "\n\n";
            }
            
            if (wp.blocks && wp.blocks.getBlockTypes) {
                const availableBlocks = wp.blocks.getBlockTypes().map(b => {
                    let attrsInfo = [];
                    if (b.attributes) {
                        for (const [key, val] of Object.entries(b.attributes)) {
                            attrsInfo.push(`${key}(${val.type || 'any'})`);
                        }
                    }
                    let supportsInfo = [];
                    if (b.supports) {
                        if (b.supports.color) supportsInfo.push('color');
                        if (b.supports.spacing) supportsInfo.push('spacing');
                        if (b.supports.typography) supportsInfo.push('typography');
                        if (b.supports.align) supportsInfo.push('align');
                    }
                    let detail = '- ' + b.name;
                    if (attrsInfo.length) detail += ` | attrs: {${attrsInfo.join(', ')}}`;
                    if (supportsInfo.length) detail += ` | supports: [${supportsInfo.join(', ')}]`;
                    return detail;
                }).join('\n');
                context += "AVAILABLE BLOCK TYPES & THEIR PARAMETERS (Use these attributes in the JSON comment of the block):\n" + availableBlocks + "\n\n";
            }
            
            try {
                const patterns = wp.data && wp.data.select('core') ? wp.data.select('core').getBlockPatterns() : null;
                if (patterns && patterns.length) {
                    const availablePatterns = patterns.map(p => p.name + ' ("' + p.title + '")').join(', ');
                    context += "AVAILABLE PATTERNS (insert using <!-- wp:pattern {\"slug\":\"PATTERN_NAME\"} /-->):\n" + availablePatterns + "\n\n";
                }
            } catch (e) {}
            
            context += [
                "CRITICAL INSTRUCTION: You are interacting directly with the active Gutenberg Block Editor.",
                "The LIVE GUTENBERG CANVAS above is authoritative, including unsaved changes.",
                "By default, you MUST NEVER replace the full page content unless explicitly asked.",
                "Always prefer to APPEND new blocks (or insert them at the current position).",
                "To INSERT or APPEND new blocks, you MUST output the raw Gutenberg HTML inside a ```gutenberg-insert code block.",
                "Do NOT use standard ```html blocks.",
                "",
                "CRITICAL RULE FOR BLOCKS: You MUST PRESERVE AND INCLUDE ALL Gutenberg structural comments (e.g., <!-- wp:columns -->, <!-- wp:heading -->, <!-- /wp:columns -->).",
                "NEVER strip them out. If you only output the raw HTML tags (like <div>) without the <!-- wp: --> comments, " +
                "the editor will fail to parse them as individual blocks and will group them into a single uneditable HTML block.",
                "The inner HTML MUST perfectly match the block wrapper comments.",
                "For complex layouts like columns, you MUST use the exact structure with the `wp-block-columns` and `wp-block-column` " +
                "wrapper divs AND their corresponding <!-- wp:column --> comments.",
                "To completely REPLACE the entire page content, use a ```gutenberg-replace code block.",
                "To EDIT and REPLACE the user's currently selected block(s), use a ```gutenberg-edit code block.",
                "IMPORTANT: ALWAYS use these markdown code blocks and NEVER strip the <!-- wp: --> tags!",
                "If you cannot perfectly remember the exact HTML wrapper for a complex core block, it is safer to use a `core/html` block and insert standard raw HTML.",
                ""
            ].join("\n");
        } else {
            content = $('#content').val() || $('#description').val() || '';
            if (content) context += "Content/Description:\n" + content + "\n";
        }
        
        return context;
    }

    async function sendPrompt(text = null, isAuto = false) {
        if (!isAuto) {
            text = $prompt.val().trim();
            if (!text || isProcessing) return;
            addMessage(text, 'user');
            $prompt.val('');
            isProcessing = true;
            $sendBtn.prop('disabled', true).find('span').removeClass('dashicons-controls-play').addClass('dashicons-update').css('animation', 'spin 1s linear infinite');
            addMessage('Thinking...', 'sys');
        }

        let $sysMsg = $messages.find('.aiutoma-agent-sys').last();

        try {
            if (aiutomaAgentData.debugMode) console.debug("[Aiutoma Agent] Gathering context...");
            const sessionContext = gatherContext();
            
            let objectId = $('#post_ID').val() || $('#tag_ID').val() || $('#user_id').val() || '';
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                const currentPostId = wp.data.select('core/editor').getCurrentPostId();
                if (currentPostId) objectId = currentPostId;
            }

            const requestBody = {
                prompt: isAuto ? "" : text,
                session_context: sessionContext,
                conversation_id: conversationId,
                model: $('#aiutoma-agent-model-select').val() || '',
                execute_tools: isAuto,
                object_id: objectId,
                object_type: aiutomaAgentData.screen
            };
            
            if (aiutomaAgentData.debugMode) console.debug("[Aiutoma Agent] Sending API request:", requestBody);

            const response = await fetch(aiutomaAgentData.rest_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiutomaAgentData.nonce
                },
                body: JSON.stringify(requestBody)
            });

            if (aiutomaAgentData.debugMode) console.debug("[Aiutoma Agent] HTTP Response Status:", response.status);
            
            let data;
            const textResponse = await response.text();
            
            try {
                data = JSON.parse(textResponse);
                if (aiutomaAgentData.debugMode) console.debug("[Aiutoma Agent] JSON Response Data:", data);
            } catch (jsonError) {
                console.error("[Aiutoma] Failed to parse JSON response. Raw text:", textResponse);
                throw new Error("Server returned an invalid JSON response. Please check the browser console.");
            }
            
            if (data.success) {
                if (data.conversation_id) {
                    conversationId = data.conversation_id;
                }
                
                if (data.action === 'tool_calls') {
                    $sysMsg.html('Executing actions...');
                    // Automatically continue processing
                    return sendPrompt(null, true);
                } else {
                    $sysMsg.remove();
                    let aiResponse = data.response || "Done.";
                    
                    if (window.elementor) {
                        let jsonContent = "";
                        const elRegex = /```(?:[a-zA-Z0-9-]*)\s*([\s\S]*?)```/gi;
                        let match;
                        while ((match = elRegex.exec(aiResponse)) !== null) {
                            jsonContent += match[1] + "\n";
                        }
                        
                        const htmlRegex = /<pre><code[^>]*>([\s\S]*?)<\/code><\/pre>/gi;
                        while ((match = htmlRegex.exec(aiResponse)) !== null) {
                            jsonContent += match[1].replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, "'") + "\n";
                        }
                        
                        if (!jsonContent.trim()) {
                            // Fallback: maybe the AI just returned pure JSON without markdown backticks
                            let strippedResponse = aiResponse.trim();
                            if (strippedResponse.startsWith('{') || strippedResponse.startsWith('[')) {
                                jsonContent = strippedResponse;
                            }
                        }
                        
                        if (jsonContent.trim()) {
                            console.log("[Aiutoma] Parsed Elementor JSON Content:", jsonContent);
                            try {
                                const models = JSON.parse(jsonContent.trim());
                                const items = Array.isArray(models) ? models : [models];
                                console.log("[Aiutoma] Elementor models to inject:", items);
                                
                                if (aiResponse.toLowerCase().includes('```elementor-replace') || aiResponse.toLowerCase().includes('language-elementor-replace')) {
                                    if (window.elementor && window.elementor.getPreviewView) {
                                        const previewView = window.elementor.getPreviewView();
                                        if (previewView.collection) {
                                            previewView.collection.reset();
                                        }
                                    }
                                }
                                
                                for (const sectionModel of items) {
                                    if (window.$e && window.$e.run) {
                                        window.$e.run('document/elements/create', {
                                            model: sectionModel,
                                            container: window.elementor.getPreviewContainer()
                                        });
                                    } else {
                                        window.elementor.getPreviewView().addChildModel(sectionModel);
                                    }
                                }
                                aiResponse = aiResponse.replace(/```(?:[a-zA-Z0-9-]*)\s*[\s\S]*?```/gi, "<br><em>[Successfully applied Elementor widgets to the page]</em><br>");
                                aiResponse = aiResponse.replace(/<pre><code[^>]*>[\s\S]*?<\/code><\/pre>/gi, "<br><em>[Successfully applied Elementor widgets to the page]</em><br>");
                            } catch (err) {
                                console.error("[Aiutoma] Error parsing Elementor JSON:", err);
                            }
                        } else {
                            console.log("[Aiutoma] No Elementor code blocks matched. aiResponse was:", aiResponse);
                        }
                    } else if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
                        // Handle Meta updates first
                        const metaRegex = /```meta-update\s*([\s\S]*?)```/gi;
                        let metaMatch;
                        while ((metaMatch = metaRegex.exec(aiResponse)) !== null) {
                            try {
                                const metaUpdates = JSON.parse(metaMatch[1]);
                                if (typeof metaUpdates === 'object' && !Array.isArray(metaUpdates)) {
                                    if (wp.data.select('core/editor')) {
                                        wp.data.dispatch('core/editor').editPost({ meta: metaUpdates });
                                        console.log("[Aiutoma] Dispatched meta updates:", metaUpdates);
                                        
                                        // Specific support for ACF / SCF frontend API
                                        if (typeof acf !== 'undefined' && acf.getField) {
                                            for (const key in metaUpdates) {
                                                const field = acf.getField(key);
                                                if (field) field.val(metaUpdates[key]);
                                            }
                                        }
                                    }
                                }
                            } catch(e) { console.error("[Aiutoma] Error parsing meta-update JSON", e); }
                        }
                        aiResponse = aiResponse.replace(/```meta-update\s*[\s\S]*?```/gi, "<br><em>[Successfully updated post meta / ACF fields]</em><br>");

                        let injectedBlocks = [];
                        let hasReplace = aiResponse.includes('```gutenberg-replace') || aiResponse.includes('language-gutenberg-replace');
                        
                        let blockContentToParse = "";
                        
                        // Handle raw markdown blocks
                        const blockRegex = /```(?:[a-zA-Z0-9-]*)\s*([\s\S]*?)```/gi;
                        let match;
                        while ((match = blockRegex.exec(aiResponse)) !== null) {
                            blockContentToParse += match[1] + "\n\n";
                        }
                        
                        // Handle HTML parsed code blocks
                        const htmlRegex = /<pre><code[^>]*>([\s\S]*?)<\/code><\/pre>/gi;
                        while ((match = htmlRegex.exec(aiResponse)) !== null) {
                            let decoded = match[1]
                                .replace(/&lt;/g, '<')
                                .replace(/&gt;/g, '>')
                                .replace(/&amp;/g, '&')
                                .replace(/&quot;/g, '"')
                                .replace(/&#039;/g, "'");
                            blockContentToParse += decoded + "\n\n";
                        }
                        
                        if (!blockContentToParse.trim()) {
                            if (aiResponse.includes('<!-- wp:') || aiResponse.includes('&lt;!-- wp:')) {
                                blockContentToParse = aiResponse
                                    .replace(/&lt;/g, '<')
                                    .replace(/&gt;/g, '>')
                                    .replace(/&amp;/g, '&')
                                    .replace(/&quot;/g, '"')
                                    .replace(/&#039;/g, "'");
                            }
                        }

                        if (blockContentToParse.trim()) {
                            console.log("[Aiutoma] Parsed Gutenberg Content to Parse:", blockContentToParse);
                            try {
                                const allParsed = wp.blocks.parse(blockContentToParse);
                                console.log("[Aiutoma] wp.blocks.parse result:", allParsed);
                                for (const block of allParsed) {
                                    if (block.name === 'core/freeform') {
                                        const content = typeof block.originalContent === 'string' ? block.originalContent : (block.attributes ? block.attributes.content : '');
                                        if (!content || content.trim() === '') {
                                            continue;
                                        }
                                    }
                                    
                                    // Even if block.isValid is false, we keep it! Gutenberg will show a "Attempt Block Recovery" button,
                                    // which is much better than silently skipping the block and confusing the user.
                                    if (block.isValid === false) {
                                        console.warn("[Aiutoma] Parsed block is marked invalid, attempting auto-recovery:", block.name);
                                        if (wp.blocks && wp.blocks.createBlock && block.name) {
                                            try {
                                                // Recreating the block from its parsed attributes generates a perfectly valid HTML structure
                                                const recovered = wp.blocks.createBlock(block.name, block.attributes, block.innerBlocks);
                                                Object.assign(block, recovered); // Overwrite the invalid block with the recovered one
                                                block.isValid = true;
                                            } catch (e) {
                                                console.warn("[Aiutoma] Auto-recovery failed for", block.name, e);
                                            }
                                        }
                                    }
                                    
                                    injectedBlocks.push(block);
                                }
                                console.log("[Aiutoma] injectedBlocks after filter:", injectedBlocks);
                            } catch (err) {
                                console.error("[Aiutoma] Error during native block parsing:", err);
                            }
                        }
                        
                        if (injectedBlocks.length > 0) {
                            try {
                                const blockEditorData = wp.data.select('core/block-editor');
                                const blockEditorDispatch = wp.data.dispatch('core/block-editor');
                                
                                function findPostContentClientId(blocks) {
                                    if (!blocks) return null;
                                    for (const block of blocks) {
                                        if (block.name === 'core/post-content') return block.clientId;
                                        if (block.innerBlocks && block.innerBlocks.length > 0) {
                                            const found = findPostContentClientId(block.innerBlocks);
                                            if (found) return found;
                                        }
                                    }
                                    return null;
                                }
                                
                                const postContentClientId = findPostContentClientId(blockEditorData.getBlocks());

                                if (aiResponse.toLowerCase().includes('```gutenberg-replace') || aiResponse.toLowerCase().includes('language-gutenberg-replace')) {
                                    if (postContentClientId) {
                                        blockEditorDispatch.replaceInnerBlocks(postContentClientId, injectedBlocks);
                                    } else {
                                        blockEditorDispatch.resetBlocks(injectedBlocks);
                                    }
                                } else if (aiResponse.toLowerCase().includes('```gutenberg-edit') || aiResponse.toLowerCase().includes('language-gutenberg-edit')) {
                                    if (lastSelectedBlockClientIds && lastSelectedBlockClientIds.length > 0) {
                                        blockEditorDispatch.replaceBlocks(lastSelectedBlockClientIds, injectedBlocks);
                                    } else {
                                        blockEditorDispatch.insertBlocks(injectedBlocks);
                                    }
                                } else {
                                    if (lastSelectedBlockClientIds && lastSelectedBlockClientIds.length > 0) {
                                        const selectedBlockClientId = lastSelectedBlockClientIds[0];
                                        const blockIndex = blockEditorData.getBlockIndex(selectedBlockClientId);
                                        const rootClientId = blockEditorData.getBlockRootClientId(selectedBlockClientId);
                                        blockEditorDispatch.insertBlocks(injectedBlocks, blockIndex + 1, rootClientId);
                                    } else {
                                        if (postContentClientId) {
                                            const innerCount = blockEditorData.getBlockCount(postContentClientId);
                                            blockEditorDispatch.insertBlocks(injectedBlocks, innerCount, postContentClientId);
                                        } else {
                                            blockEditorDispatch.insertBlocks(injectedBlocks);
                                        }
                                    }
                                }
                                
                                aiResponse = aiResponse.replace(/```(?:[a-zA-Z0-9-]*)\s*[\s\S]*?```/gi, "<br><em>[Successfully applied generated blocks to the page]</em><br>");
                                aiResponse = aiResponse.replace(/<pre><code[^>]*>[\s\S]*?<\/code><\/pre>/gi, "<br><em>[Successfully applied generated blocks to the page]</em><br>");
                                aiResponse = aiResponse.replace(/<!--\s*wp:[\s\S]*?\/wp:[a-zA-Z0-9-]+\s*-->/g, '');
                                aiResponse = aiResponse.replace(/<!--\s*wp:[\s\S]*?\/-->/g, '');
                                aiResponse = aiResponse.replace(/&lt;!--\s*wp:[\s\S]*?\/wp:[a-zA-Z0-9-]+\s*--&gt;/g, '');
                                aiResponse = aiResponse.replace(/&lt;!--\s*wp:[\s\S]*?\/--&gt;/g, '');
                            } catch (err) {
                                console.error("[Aiutoma] Error injecting blocks into editor:", err);
                            }
                        }
                    } else if (aiutomaAgentData.screen === 'theme-editor' || aiutomaAgentData.screen === 'plugin-editor') {
                        const codeRegex = /```(?:[a-zA-Z0-9-]*)\s*([\s\S]*?)```/gi;
                        let codeMatch;
                        let newCode = null;
                        while ((codeMatch = codeRegex.exec(aiResponse)) !== null) {
                            newCode = codeMatch[1];
                        }
                        
                        const htmlCodeRegex = /<pre><code[^>]*>([\s\S]*?)<\/code><\/pre>/gi;
                        while ((codeMatch = htmlCodeRegex.exec(aiResponse)) !== null) {
                            newCode = codeMatch[1].replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, "'");
                        }

                        if (newCode !== null) {
                            let cmInstance = $('.CodeMirror')[0]?.CodeMirror || (wp && wp.codeEditor && wp.codeEditor.defaultCodeEditor && wp.codeEditor.defaultCodeEditor.codemirror);
                            if (cmInstance) {
                                cmInstance.setValue(newCode.trim());
                            } else {
                                $('#newcontent').val(newCode.trim()).trigger('change');
                            }
                            aiResponse = aiResponse.replace(/```(?:[a-zA-Z0-9-]*)\s*[\s\S]*?```/gi, "<br><em>[Successfully updated file content in editor]</em><br>");
                            aiResponse = aiResponse.replace(/<pre><code[^>]*>[\s\S]*?<\/code><\/pre>/gi, "<br><em>[Successfully updated file content in editor]</em><br>");
                        }
                    }

                    addMessage(aiResponse, 'ai');
                }
            } else {
                $sysMsg.remove();
                console.warn("[Aiutoma] API returned an error:", data.message);
                addMessage("Error: " + (data.message || "Unknown error"), 'sys');
            }
        } catch (error) {
            console.error("[Aiutoma] Caught execution error:", error);
            $sysMsg.remove();
            addMessage("Request failed: " + error.message, 'sys');
            isAuto = false; // Force UI to reset in finally block
        } finally {
            if (!isAuto || (isAuto && !isProcessing)) {
                // If it's a final step, or an error happened, reset UI
                isProcessing = false;
                $sendBtn.prop('disabled', false).find('span').removeClass('dashicons-update').addClass('dashicons-controls-play').css('animation', 'none');
                $prompt.focus();
            }
        }
    }

    $sendBtn.on('click', function() { sendPrompt(); });

    $prompt.on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendPrompt();
        }
    });

    // Elementor Panel Integration (Angie-inspired UX)
    if (typeof window.elementor !== 'undefined') {
        window.elementor.on('panel:init', function() {
            setTimeout(function() {
                const $elementsWrapper = $('#elementor-panel-elements-wrapper');
                if ($elementsWrapper.length) {
                    const btnStyle = [
                        'width: 100%',
                        'padding: 10px',
                        'cursor: pointer',
                        'text-align: center',
                        'background: linear-gradient(135deg, #6366f1, #a855f7, #ec4899)',
                        'color: white',
                        'border-radius: 4px',
                        'margin-bottom: 15px',
                        'font-weight: bold',
                        'box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
                        'transition: transform 0.2s, box-shadow 0.2s'
                    ].join('; ');
                    const $aiButton = $(
                        '<div class="elementor-element-wrapper" style="' + btnStyle + '">' +
                        '<span class="dashicons dashicons-superhero" style="margin-right: 8px; vertical-align: middle;"></span> ' +
                        '<span style="vertical-align: middle;">Build with Aiutoma</span>' +
                        '</div>'
                    );
                    
                    $aiButton.on('mouseenter', function() {
                        $(this).css('transform', 'translateY(-2px)');
                        $(this).css('box-shadow', '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)');
                    }).on('mouseleave', function() {
                        $(this).css('transform', 'none');
                        $(this).css('box-shadow', '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)');
                    });

                    $aiButton.on('click', function() {
                        if ($chatbot.hasClass('aiutoma-agent-closed')) {
                            $toggleBtn.click();
                        }
                        
                        $prompt.focus();
                        
                        // Add a subtle highlight animation to the prompt box to draw attention
                        $prompt.css('transition', 'box-shadow 0.3s ease, border-color 0.3s ease');
                        $prompt.css('box-shadow', '0 0 0 3px rgba(168, 85, 247, 0.4)');
                        $prompt.css('border-color', '#a855f7');
                        setTimeout(() => {
                            $prompt.css('box-shadow', 'none');
                            $prompt.css('border-color', '#ccd0d4');
                        }, 1500);
                    });
                    
                    $elementsWrapper.prepend($aiButton);
                }
            }, 1000); // Small delay to ensure Elementor DOM is fully hydrated
        });
    }

    // --- Gutenberg Live Push Polling ---
    if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
        setInterval(async () => {
            try {
                const response = await fetch(aiutomaAgentData.rest_url.replace('/ai-chat', '/editor-pull'), {
                    headers: { 'X-WP-Nonce': aiutomaAgentData.nonce }
                });
                const data = await response.json();
                if (data.success && data.blocks) {
                    const parsedBlocks = wp.blocks.parse(data.blocks);
                    const validBlocks = parsedBlocks.filter(b => b.name !== 'core/freeform' || (b.originalContent && b.originalContent.trim() !== ''));
                    if (validBlocks.length > 0) {
                        const dispatch = wp.data.dispatch('core/block-editor');
                        if (lastSelectedBlockClientIds && lastSelectedBlockClientIds.length > 0) {
                            dispatch.replaceBlocks(lastSelectedBlockClientIds, validBlocks);
                        } else {
                            dispatch.insertBlocks(validBlocks);
                        }
                        console.log("[Aiutoma] External blocks pushed to editor canvas live!");
                    }
                }
            } catch (e) {
                // Silent catch for poll
            }
        }, 3000);
    }

    // --- Gutenberg Native Sidebar Integration ---
    function setupGutenbergSidebar() {
        if (!isSidebarMode) return;

        const isGutenberg = typeof wp !== 'undefined' && wp.blocks && wp.data;
        if (!isGutenberg) {
            $chatbot.addClass('aiutoma-agent-fallback-floating');
            return;
        }

        let isAiutomaActive = false;

        function getTabList() {
            return $('.editor-sidebar__panel-tabs [role="tablist"], .interface-complementary-area-header [role="tablist"]');
        }

        function activateAiutomaTab() {
            isAiutomaActive = true;
            const $tabBtn = $('#aiutoma-agent-tab-btn');
            const $tabList = getTabList();
            const $panel = $('#aiutoma-agent-sidebar-panel');

            // Select Aiutoma tab button
            $tabBtn.attr('aria-selected', 'true').attr('data-active', '').attr('data-composite-item-active', '').addClass('is-active');

            // Deselect sibling Gutenberg tabs (Page, Block, etc.)
            $tabList.find('button[role="tab"]:not(#aiutoma-agent-tab-btn)').each(function() {
                $(this).attr('aria-selected', 'false').removeAttr('data-active').removeAttr('data-composite-item-active').removeClass('is-active');
            });

            // Hide native Gutenberg panels in the complementary area
            $panel.siblings().not('.editor-sidebar__panel-tabs').not('.interface-complementary-area-header').addClass('aiutoma-hidden-by-agent');

            // Show Aiutoma panel and focus chat prompt
            $panel.show();
            $chatbot.removeClass('aiutoma-agent-closed');
            $body.show();
            $prompt.focus();
        }

        function deactivateAiutomaTab() {
            if (!isAiutomaActive) return;
            isAiutomaActive = false;
            const $tabBtn = $('#aiutoma-agent-tab-btn');
            const $panel = $('#aiutoma-agent-sidebar-panel');

            // Deselect Aiutoma tab button
            $tabBtn.attr('aria-selected', 'false').removeAttr('data-active').removeAttr('data-composite-item-active').removeClass('is-active');

            // Hide Aiutoma panel
            $panel.hide();

            // Restore native Gutenberg panels
            $('.aiutoma-hidden-by-agent').removeClass('aiutoma-hidden-by-agent');
        }

        function injectSidebarTab() {
            const $tabList = getTabList();
            if (!$tabList.length) {
                return false;
            }

            const $header = $tabList.closest('.editor-sidebar__panel-tabs, .interface-complementary-area-header');

            // 1. Create or verify Panel exists
            let $panel = $('#aiutoma-agent-sidebar-panel');
            if (!$panel.length) {
                $panel = $('<div id="aiutoma-agent-sidebar-panel" class="editor-sidebar__panel components-panel aiutoma-agent-sidebar-panel" style="display: none;"></div>');
                $header.after($panel);
            }

            // Move chatbot into panel if not already inside
            if (!$panel.has($chatbot).length) {
                $panel.append($chatbot);
                $chatbot.addClass('aiutoma-agent-in-sidebar').removeClass('aiutoma-agent-closed');
                $body.show();
                $('#aiutoma-agent-model-area').hide();
            }

            // 2. Create or verify Tab Button exists in tablist
            let $tabBtn = $('#aiutoma-agent-tab-btn');
            if (!$tabBtn.length || !$tabList.has($tabBtn).length) {
                const $siblingTab = $tabList.find('button[role="tab"]').first();
                let siblingClasses = '';
                let spanClasses = '';

                if ($siblingTab.length) {
                    const classes = ($siblingTab.attr('class') || '').split(/\s+/);
                    siblingClasses = classes.filter(c => c && !c.includes('active') && !c.includes('selected')).join(' ');
                    const $siblingSpan = $siblingTab.find('span').first();
                    if ($siblingSpan.length) {
                        spanClasses = $siblingSpan.attr('class') || '';
                    }
                }

                $tabBtn = $(`
                    <button type="button" role="tab" id="aiutoma-agent-tab-btn" class="aiutoma-agent-sidebar-tab ${siblingClasses}" aria-selected="false" tabindex="-1" data-orientation="horizontal">
                        <span class="${spanClasses}">
                            <span class="dashicons dashicons-superhero" style="font-size:15px;width:15px;height:15px;line-height:15px;vertical-align:middle;margin-right:4px;"></span>
                            Aiutoma
                        </span>
                    </button>
                `);

                $tabList.append($tabBtn);

                $tabBtn.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    activateAiutomaTab();
                });
            }

            // If Aiutoma was already active, preserve active state
            if (isAiutomaActive) {
                $tabBtn.attr('aria-selected', 'true').attr('data-active', '').attr('data-composite-item-active', '').addClass('is-active');
                $panel.siblings().not('.editor-sidebar__panel-tabs').not('.interface-complementary-area-header').addClass('aiutoma-hidden-by-agent');
                $panel.show();
            }

            return true;
        }

        // Deactivate Aiutoma when user clicks a native Gutenberg tab (Page, Block, etc.)
        const nativeTabSelectors = [
            '.editor-sidebar__panel-tabs button[role="tab"]:not(#aiutoma-agent-tab-btn)',
            '.interface-complementary-area-header button[role="tab"]:not(#aiutoma-agent-tab-btn)'
        ].join(', ');
        $(document).on('click', nativeTabSelectors, function() {
            deactivateAiutomaTab();
        });

        // Initialize and watch for Gutenberg sidebar rendering via MutationObserver
        let retryCount = 0;
        const checkInterval = setInterval(function() {
            retryCount++;
            if (injectSidebarTab() || retryCount > 30) {
                clearInterval(checkInterval);
                if (retryCount > 30 && !$('#aiutoma-agent-tab-btn').length) {
                    // Fallback to floating mode if Gutenberg sidebar was never opened
                    $chatbot.addClass('aiutoma-agent-fallback-floating');
                }
            }
        }, 300);

        // Keep in sync during Gutenberg React re-renders
        let observerDebounce = null;
        const observer = new MutationObserver(function() {
            if (observerDebounce) return;
            observerDebounce = setTimeout(function() {
                observerDebounce = null;
                injectSidebarTab();
            }, 100);
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    setupGutenbergSidebar();
});
