// Aiutoma WPML Admin JS
jQuery(document).ready(function ($) {
    if (typeof aiutomaWpmlData === 'undefined') return;

    // --- WPML TRANSLATION DASHBOARD LOGIC (Bulk Content) ---
    if (aiutomaWpmlData.isWpmlPage) {
        let pendingItems = [];
        let isTranslating = false;
        let currentLang = '';

        $('#aiutoma_scan_missing').on('click', function () {
            let typeStr = $('#aiutoma_wpml_type').val();
            let lang = $('#aiutoma_wpml_lang').val();
            let statusVal = $('#aiutoma_wpml_status').val();
            currentLang = lang;

            $('#aiutoma_refresh_bulk').hide();

            let btn = $(this);
            btn.prop('disabled', true).text(aiutomaWpmlData.textScanning);

            $.ajax({
                url: aiutomaWpmlData.restGetMissingUrl,
                method: 'GET',
                data: { type: typeStr, target_lang: lang, status: statusVal },
                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', aiutomaWpmlData.nonce); },
                success: function (res) {
                    btn.prop('disabled', false).text(aiutomaWpmlData.textFilter);
                    if (res.success) {
                        pendingItems = res.items;
                        $('#aiutoma_missing_count').text(pendingItems.length);

                        let html = '';
                        if (pendingItems.length > 0) {
                            pendingItems.forEach(function (item) {
                                let icon = item.status === 'needs_update'
                                    ? '<span class="dashicons dashicons-update aiutoma-wpml-icon-update" title="' + aiutomaWpmlData.textNeedsUpdate + '"></span>'
                                    : '<span class="dashicons dashicons-plus aiutoma-wpml-icon-missing" title="' + aiutomaWpmlData.textMissing + '"></span>';

                                html += '<div id="item-' + item.id + '" class="aiutoma-wpml-item-row">' +
                                    '<label class="wpml-checkbox aiutoma-wpml-checkbox-label"><input type="checkbox" class="aiutoma-item-checkbox" value="' + item.id + '" checked> <span></span></label> ' +
                                    '<a href="' + item.edit_url + '" target="_blank" class="aiutoma-wpml-item-title">' + item.title + '</a>' +
                                    item.langs_html +
                                    '<div class="aiutoma-wpml-item-actions">' + icon + ' <span class="aiutoma-wpml-item-id">ID: ' + item.id + '</span></div>' +
                                    '</div>';
                            });
                            $('#aiutoma_start_bulk').show();
                            $('#aiutoma_select_all').prop('disabled', false).prop('checked', true).parent().show();
                        } else {
                            html = '<p>' + aiutomaWpmlData.textNoMissing + '</p>';
                            $('#aiutoma_start_bulk').hide();
                            $('#aiutoma_select_all').prop('disabled', false).parent().hide();
                        }
                        $('#aiutoma_missing_list').html(html);
                        $('#aiutoma_local_search').val('');
                        $('#aiutoma_bulk_results').fadeIn();
                        $('#aiutoma_bulk_progress').text('');
                    } else {
                        alert('Error: ' + res.message);
                    }
                },
                error: function () {
                    btn.prop('disabled', false).text(aiutomaWpmlData.textFilter);
                    alert(aiutomaWpmlData.textCommError);
                }
            });
        });

        $('#aiutoma_local_search').on('keyup', function () {
            let searchVal = $(this).val().toLowerCase();
            $('#aiutoma_missing_list > div').each(function () {
                let title = $(this).find('a').first().text().toLowerCase();
                if (title.indexOf(searchVal) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        $('#aiutoma_select_all').on('change', function () {
            $('.aiutoma-item-checkbox').prop('checked', $(this).is(':checked'));
        });

        $('#aiutoma_start_bulk').on('click', function () {
            let selectedIds = [];
            $('.aiutoma-item-checkbox:checked').each(function () {
                selectedIds.push($(this).val().toString());
            });

            if (selectedIds.length === 0) {
                alert(aiutomaWpmlData.textSelectOne);
                return;
            }

            pendingItems = pendingItems.filter(item => selectedIds.includes(item.id.toString()));

            if (!confirm(aiutomaWpmlData.textConfirmBulk.replace('%d', pendingItems.length))) return;

            $(this).prop('disabled', true);
            $('.aiutoma-item-checkbox').prop('disabled', true);
            $('#aiutoma_select_all').prop('disabled', true);
            isTranslating = true;
            processNext();
        });

        function processNext() {
            if (pendingItems.length === 0) {
                $('#aiutoma_bulk_progress').text(aiutomaWpmlData.textComplete);
                $('#aiutoma_start_bulk').prop('disabled', false).hide();
                isTranslating = false;

                if ($('#aiutoma_refresh_bulk').length === 0) {
                    $('#aiutoma_start_bulk').after('<button class="wpml-button base-btn wpml-button--outlined" id="aiutoma_refresh_bulk" style="margin-left:10px;">' + aiutomaWpmlData.textRefresh + '</button>');
                    $('#aiutoma_refresh_bulk').on('click', function () {
                        $('#aiutoma_scan_missing').trigger('click');
                    });
                }
                $('#aiutoma_refresh_bulk').show();

                return;
            }

            let item = pendingItems.shift();
            $('#aiutoma_bulk_progress').text(aiutomaWpmlData.textTranslating + ' ' + item.title + ' (' + pendingItems.length + ' remaining...)');
            $('#item-' + item.id).addClass('aiutoma-wpml-translating');

            $.ajax({
                url: aiutomaWpmlData.restTranslateUrl,
                method: 'POST',
                data: {
                    object_id: item.id,
                    object_type: item.object_type,
                    taxonomy: item.taxonomy,
                    target_lang: currentLang
                },
                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', aiutomaWpmlData.nonce); },
                success: function (res) {
                    $('#item-' + item.id).removeClass('aiutoma-wpml-translating');
                    if (res.success) {
                        $('#item-' + item.id).addClass('aiutoma-wpml-success').append('<span class="aiutoma-wpml-badge-success">Success</span>');
                        if (res.edit_url) {
                            let flagImg = $('#item-' + item.id).find('img[alt="' + currentLang + '"]');
                            if (flagImg.length > 0) {
                                flagImg.removeClass('aiutoma-wpml-flag-inactive');
                                let parent = flagImg.parent();
                                if (parent.is('span') && !parent.attr('title')) {
                                    parent.replaceWith('<a href="' + res.edit_url + '" target="_blank" style="display:flex;">' + flagImg.prop('outerHTML') + '</a>');
                                }
                            }
                        }
                    } else {
                        $('#item-' + item.id).addClass('aiutoma-wpml-failed').append('<span class="aiutoma-wpml-badge-failed">Failed</span>');
                        console.error('Translation error for item ID ' + item.id + ':', res.message || res);
                    }
                    processNext();
                },
                error: function (xhr, status, error) {
                    $('#item-' + item.id).removeClass('aiutoma-wpml-translating').addClass('aiutoma-wpml-failed').append('<span class="aiutoma-wpml-badge-failed">Failed</span>');
                    console.error('Translation AJAX failed for item ID ' + item.id + ':', error);
                    processNext();
                }
            });
        }
    }

    // --- STRINGS TRANSLATION LOGIC ---
    if (aiutomaWpmlData.isWpmlPage) {
        let pendingStrings = [];
        let isTranslatingStrings = false;
        let currentStringLang = '';

        $('#aiutoma_scan_strings').on('click', function (e) {
            e.preventDefault();
            if (isTranslatingStrings) return;

            let domain = $('#aiutoma_wpml_string_domain').val();
            let lang = $('#aiutoma_wpml_string_lang').val();
            currentStringLang = lang;

            let btn = $(this);
            btn.prop('disabled', true).text(aiutomaWpmlData.textScanning);

            $.ajax({
                url: aiutomaWpmlData.restStringsGetMissingUrl,
                method: 'GET',
                data: { domain: domain, target_lang: lang },
                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', aiutomaWpmlData.nonce); },
                success: function (res) {
                    btn.prop('disabled', false).text(aiutomaWpmlData.textFilterStrings);
                    if (res.success) {
                        pendingStrings = res.items;
                        $('#aiutoma_missing_strings_count').text(pendingStrings.length);

                        let html = '';
                        if (pendingStrings.length > 0) {
                            pendingStrings.forEach(function (item) {
                                html += '<div class="string-item aiutoma-wpml-string-row">' +
                                    '<label class="wpml-checkbox aiutoma-wpml-checkbox-label"><input type="checkbox" class="aiutoma-string-checkbox" value="' + item.id + '" checked> <span></span></label> ' +
                                    '<div class="aiutoma-wpml-string-content">' +
                                    '<div class="string-name">' + item.name + '</div>' +
                                    '<div class="string-value">' + item.value + '</div>' +
                                    '</div>' +
                                    '<div class="aiutoma-wpml-string-actions"><span class="dashicons dashicons-plus aiutoma-wpml-icon-missing" title="Missing Translation"></span></div>' +
                                    '</div>';
                            });
                            $('#aiutoma_start_bulk_strings').show();
                            $('#aiutoma_select_all_strings').prop('disabled', false).prop('checked', true).parent().show();
                        } else {
                            html = '<p class="aiutoma-wpml-no-strings">' + aiutomaWpmlData.textNoMissingStrings + '</p>';
                            $('#aiutoma_start_bulk_strings').hide();
                            $('#aiutoma_select_all_strings').prop('disabled', false).parent().hide();
                        }
                        $('#aiutoma_missing_strings_list').html(html);
                        $('#aiutoma_local_search_strings').val('');
                        $('#aiutoma_bulk_strings_results').fadeIn();
                        $('#aiutoma_bulk_strings_progress').text('');
                    } else {
                        alert('Error: ' + res.message);
                    }
                },
                error: function () {
                    btn.prop('disabled', false).text(aiutomaWpmlData.textFilterStrings);
                    alert(aiutomaWpmlData.textCommError);
                }
            });
        });

        $('#aiutoma_local_search_strings').on('keyup', function () {
            let searchVal = $(this).val().toLowerCase();
            $('#aiutoma_missing_strings_list > div').each(function () {
                let text = $(this).find('.string-name').text().toLowerCase() + ' ' + $(this).find('.string-value').text().toLowerCase();
                if (text.indexOf(searchVal) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        $('#aiutoma_select_all_strings').on('change', function () {
            $('.aiutoma-string-checkbox').prop('checked', $(this).is(':checked'));
        });

        $('#aiutoma_start_bulk_strings').on('click', function () {
            let selectedIds = [];
            $('.aiutoma-string-checkbox:checked').each(function () {
                selectedIds.push($(this).val().toString());
            });

            if (selectedIds.length === 0) {
                alert(aiutomaWpmlData.textSelectOneString);
                return;
            }

            pendingStrings = pendingStrings.filter(item => selectedIds.includes(item.id.toString()));

            if (!confirm(aiutomaWpmlData.textConfirmBulkStrings.replace('%d', pendingStrings.length))) return;

            $(this).prop('disabled', true);
            $('.aiutoma-string-checkbox').prop('disabled', true);
            $('#aiutoma_select_all_strings').prop('disabled', true);
            isTranslatingStrings = true;
            processNextString();
        });

        function processNextString() {
            if (pendingStrings.length === 0) {
                $('#aiutoma_bulk_strings_progress').text(aiutomaWpmlData.textStringsComplete);
                $('#aiutoma_start_bulk_strings').prop('disabled', false).hide();
                isTranslatingStrings = false;
                return;
            }

            let item = pendingStrings.shift();
            $('#aiutoma_bulk_strings_progress').text(aiutomaWpmlData.textTranslating + ' ' + item.name + ' (' + pendingStrings.length + ' remaining...)');

            $.ajax({
                url: aiutomaWpmlData.restStringsTranslateUrl,
                method: 'POST',
                data: { string_id: item.id, target_lang: currentStringLang },
                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', aiutomaWpmlData.nonce); },
                success: function (res) {
                    if (res.success) {
                        $('#aiutoma_missing_strings_list').find('input[value="' + item.id + '"]').closest('div').addClass('aiutoma-wpml-string-success').find('.dashicons').removeClass('dashicons-plus').addClass('dashicons-yes-alt aiutoma-wpml-icon-success');
                    } else {
                        $('#aiutoma_missing_strings_list').find('input[value="' + item.id + '"]').closest('div').find('.dashicons').removeClass('dashicons-plus').addClass('dashicons-warning aiutoma-wpml-icon-failed');
                    }
                    processNextString();
                },
                error: function () {
                    $('#aiutoma_missing_strings_list').find('input[value="' + item.id + '"]').closest('div').find('.dashicons').removeClass('dashicons-plus').addClass('dashicons-warning aiutoma-wpml-icon-failed');
                    processNextString();
                }
            });
        }
    }

    // --- INJECT BUTTONS IN WP EDIT SCREENS ---
    if (aiutomaWpmlData.injectButtons) {
        function injectButtons() {
            // Post edit screen (icl_div)
            $('#icl_div .icl_lang_row').each(function () {
                let $row = $(this);
                if ($row.find('.aiutoma-translate-btn').length > 0) return;

                let langCode = $row.find('input[name^="icl_multi_"]').attr('name');
                if (langCode) {
                    langCode = langCode.replace('icl_multi_', '');
                } else {
                    let $link = $row.find('a[href*="lang="]');
                    if ($link.length) {
                        let url = new URL($link.attr('href'), window.location.origin);
                        langCode = url.searchParams.get('lang');
                    }
                }

                if (langCode && $row.find('.dashicons-plus').length) {
                    let $btn = $('<button type="button" class="button button-small aiutoma-translate-btn" data-lang="' + langCode + '" title="Translate with AI"><span class="dashicons dashicons-admin-site-alt3"></span> AI</button>');
                    $row.find('.icl_lang_row_status').append($btn);
                }
            });

            // Taxonomy edit screen
            $('[id^="icl_tax_"]').each(function () {
                let $wrapper = $(this);
                $wrapper.find('tr').each(function () {
                    let $tr = $(this);
                    if ($tr.find('.aiutoma-translate-btn').length > 0) return;

                    let $link = $tr.find('a[href*="lang="]');
                    if ($link.length && $tr.find('.dashicons-plus').length) {
                        let url = new URL($link.attr('href'), window.location.origin);
                        let langCode = url.searchParams.get('lang');

                        if (langCode) {
                            let $btn = $('<button type="button" class="button button-small aiutoma-translate-btn" data-lang="' + langCode + '" title="Translate with AI"><span class="dashicons dashicons-admin-site-alt3"></span> AI</button>');
                            $tr.find('td').last().append($btn);
                        }
                    }
                });
            });

            // WP List Table (Posts and Terms)
            $('table.wp-list-table tbody tr').each(function () {
                let $tr = $(this);
                let rowId = $tr.attr('id');
                if (!rowId) return;

                let objectId = '';
                let objectType = '';
                let tax = '';

                if (rowId.startsWith('post-')) {
                    objectId = rowId.replace('post-', '');
                    objectType = 'post';
                } else if (rowId.startsWith('tag-')) {
                    objectId = rowId.replace('tag-', '');
                    objectType = 'term';
                } else {
                    return;
                }

                $tr.find('a[href*="source_lang="]').each(function () {
                    let $addLink = $(this);
                    if ($addLink.next('.aiutoma-list-translate-btn').length > 0) return;

                    let href = $addLink.attr('href');
                    let url;
                    try {
                        url = new URL(href, window.location.origin);
                    } catch (e) { return; }

                    let langCode = url.searchParams.get('lang');
                    if (objectType === 'term') tax = url.searchParams.get('taxonomy');

                    if (langCode) {
                        let $btn = $('<button type="button" class="button button-small aiutoma-list-translate-btn" data-lang="' + langCode + '" data-id="' + objectId + '" data-type="' + objectType + '" data-tax="' + tax + '" title="Translate with AI"><span class="dashicons dashicons-admin-site-alt3"></span></button>');
                        $addLink.after($btn);
                    }
                });
            });
        }

        injectButtons();

        $(document).on('click', '.aiutoma-translate-btn', function (e) {
            e.preventDefault();
            let $btn = $(this);
            let lang = $btn.data('lang');
            let postId = $('#post_ID').val();
            let tagId = $('input[name="tag_ID"]').val();
            let tax = $('input[name="taxonomy"]').val();

            let objectId = postId ? postId : tagId;
            let objectType = postId ? 'post' : 'term';

            if (!objectId) {
                alert('Could not determine object ID. Please save first.');
                return;
            }

            $btn.html('<span class="dashicons dashicons-update-alt aiutoma-wpml-spin"></span>');
            $btn.prop('disabled', true);

            $.ajax({
                url: aiutomaWpmlData.restTranslateUrl,
                method: 'POST',
                headers: { 'X-WP-Nonce': aiutomaWpmlData.nonce },
                data: {
                    object_id: objectId,
                    object_type: objectType,
                    taxonomy: tax,
                    target_lang: lang
                },
                success: function (res) {
                    if (res.success) {
                        alert(aiutomaWpmlData.textTranslatedSuccess);
                        window.location.reload();
                    } else {
                        alert('Error: ' + res.message);
                        $btn.html('<span class="dashicons dashicons-admin-site-alt3"></span> AI');
                        $btn.prop('disabled', false);
                    }
                },
                error: function (err) {
                    alert(aiutomaWpmlData.textCommError);
                    $btn.html('<span class="dashicons dashicons-admin-site-alt3"></span> AI');
                    $btn.prop('disabled', false);
                }
            });
        });

        $(document).on('click', '.aiutoma-list-translate-btn', function (e) {
            e.preventDefault();
            let $btn = $(this);
            let lang = $btn.data('lang');
            let objId = $btn.data('id');
            let objType = $btn.data('type');
            let tax = $btn.data('tax');

            $btn.html('<span class="dashicons dashicons-update-alt aiutoma-wpml-spin"></span>');
            $btn.prop('disabled', true);

            $.ajax({
                url: aiutomaWpmlData.restTranslateUrl,
                method: 'POST',
                headers: { 'X-WP-Nonce': aiutomaWpmlData.nonce },
                data: {
                    object_id: objId,
                    object_type: objType,
                    taxonomy: tax,
                    target_lang: lang
                },
                success: function (res) {
                    if (res.success) {
                        $btn.replaceWith('<span class="dashicons dashicons-yes-alt aiutoma-wpml-list-success" title="Translated!"></span>');
                    } else {
                        alert('Error: ' + res.message);
                        $btn.html('<span class="dashicons dashicons-admin-site-alt3"></span>');
                        $btn.prop('disabled', false);
                    }
                },
                error: function () {
                    alert(aiutomaWpmlData.textCommError);
                    $btn.html('<span class="dashicons dashicons-admin-site-alt3"></span>');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Auto-translate checkbox logic
        if ($('.aiutoma-auto-translate-wrap').length === 0) {
            var checkboxHtml = '<div class="aiutoma-auto-translate-wrap">' +
                '<label><input type="checkbox" name="aiutoma_wpml_auto_retranslate" value="1" ' + aiutomaWpmlData.autoTranslateChecked + '> ' +
                '<strong>' + aiutomaWpmlData.textAutoTranslate + '</strong></label>' +
                '</div>';

            var attempts = 0;
            var checkExist = setInterval(function () {
                attempts++;
                var $minorEdit = $('#icl_minor_edit, input[name="icl_minor_edit"]');

                if ($minorEdit.length > 0) {
                    clearInterval(checkExist);
                    if ($('.aiutoma-auto-translate-wrap').length === 0) {
                        var $target = $minorEdit.closest('label').length ? $minorEdit.closest('label') : $minorEdit;
                        var $prev = $target.prev('br');
                        if ($prev.length) {
                            $prev.before(checkboxHtml);
                        } else {
                            $target.before(checkboxHtml);
                        }
                    }
                } else if (attempts > 20) { // 5 seconds timeout
                    clearInterval(checkExist);
                    if ($('.aiutoma-auto-translate-wrap').length === 0) {
                        var $container = $('#icl_div .inside, .wpml-app').first();
                        if ($container.length) {
                            $container.append(checkboxHtml);
                        }
                    }
                }
            }, 250);
        }
    }

    if (aiutomaWpmlData.isNativeWpmlJobs) {
        setInterval(function () {
            if (document.getElementById('aiutoma-native-translate-container')) return;

            const bulkActions = document.querySelectorAll('.wpml-bulk-actions');
            const wrap = document.querySelector('.wrap') || document.querySelector('#wpbody-content') || document.querySelector('#wpcontent') || document.body;
            
            if (bulkActions.length === 0 && !wrap) return;

            const container = document.createElement('div');
            container.id = 'aiutoma-native-translate-container';
            container.style.display = 'inline-flex';
            container.style.alignItems = 'center';

            const btn = document.createElement('button');
            btn.id = 'aiutoma-native-translate-btn';
            btn.className = 'button button-primary button-large';
            btn.innerHTML = '✨ Translate Selected via Aiutoma';
            btn.style.background = '#815efc';
            btn.style.borderColor = '#6b4ce3';
            btn.style.color = '#fff';

            const progress = document.createElement('span');
            progress.id = 'aiutoma-native-translate-progress';
            progress.style.marginLeft = '15px';
            progress.style.fontWeight = 'bold';
            progress.style.color = '#815efc';

            const settingsBtn = document.createElement('a');
            settingsBtn.href = '?page=aiutoma-wpml&tab=settings';
            settingsBtn.target = '_blank';
            settingsBtn.className = 'button button-large';
            settingsBtn.style.marginLeft = '10px';
            settingsBtn.style.paddingBottom = '18px';
            settingsBtn.style.display = 'inline-flex';
            settingsBtn.style.alignItems = 'center';
            settingsBtn.style.justifyContent = 'center';
            settingsBtn.innerHTML = '<span class="dashicons dashicons-admin-generic"></span>';
            settingsBtn.title = 'Aiutoma WPML Settings';

            container.appendChild(btn);
            container.appendChild(settingsBtn);
            container.appendChild(progress);

            let target = null;
            if (bulkActions.length > 0) {
                // Find the last VISIBLE bulk actions container
                for (let i = bulkActions.length - 1; i >= 0; i--) {
                    if (bulkActions[i].offsetParent !== null) {
                        target = bulkActions[i];
                        break;
                    }
                }
            }

            if (target) {
                console.log("Aiutoma: Appending to visible .wpml-bulk-actions");
                target.appendChild(container);
            } else if (wrap) {
                console.log("Aiutoma: Fallback to wrap/body");
                wrap.appendChild(container);
            }

            btn.addEventListener('click', async function (e) {
                e.preventDefault();

                const cbs = document.querySelectorAll('.ant-checkbox-wrapper-checked input[type="checkbox"], .ant-checkbox-input:checked, input[type="checkbox"]:checked');
                const jobIds = [];

                cbs.forEach(cb => {
                    let val = null;
                    const tr = cb.closest('.ant-table-row, tr');
                    
                    if (tr && tr.classList.contains('ant-table-row')) {
                        // Translation Jobs React Table (uses RID)
                        if (tr.dataset && tr.dataset.rowKey) {
                            val = 'rid_' + tr.dataset.rowKey;
                        }
                    } else if (tr && tr.id && tr.id.match(/_(\d+)$/)) {
                        // Classic Translation Queue (uses Job ID)
                        const match = tr.id.match(/_(\d+)$/);
                        if (match) val = 'job_' + match[1];
                    } else if (cb.value && !isNaN(parseInt(cb.value)) && cb.value !== 'on') {
                        // Translation Dashboard (uses Post ID in checkbox value)
                        val = 'post_' + parseInt(cb.value);
                    } else if (tr && tr.dataset && tr.dataset.rowKey) {
                        val = 'raw_' + tr.dataset.rowKey;
                    }

                    if (val && !jobIds.includes(val)) {
                        jobIds.push(val);
                    }
                });

                if (!jobIds.length) {
                    alert('Please select at least one job from the WPML list.');
                    return;
                }

                console.log("Aiutoma: Extracted Job IDs to translate: ", jobIds);

                if (!confirm('Are you sure you want to translate ' + jobIds.length + ' jobs via Aiutoma?')) return;

                btn.disabled = true;

                for (let i = 0; i < jobIds.length; i++) {
                    const jobId = jobIds[i];
                    progress.textContent = `Translating job ${i + 1} of ${jobIds.length}...`;

                    try {
                        console.log("Aiutoma: Sending request for Job ID:", jobId);
                        const res = await fetch(aiutomaWpmlData.restXliffTranslateUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': aiutomaWpmlData.nonce
                            },
                            body: JSON.stringify({
                                job_id: jobId
                            })
                        });
                        const data = await res.json();
                        console.log("Aiutoma: Response for Job ID " + jobId + ":", data);
                        if (!data.success) {
                            console.error('Aiutoma Translation Error for Job ' + jobId + ':', data.message);
                            alert('Error translating job ' + jobId + ': ' + data.message);
                        }
                    } catch (err) {
                        console.error('Request failed for Job ' + jobId, err);
                        alert('Request failed for Job ' + jobId);
                    }
                }

                progress.textContent = 'Done! Reloading...';
                setTimeout(() => window.location.reload(), 1500);
            });
        }, 1000);
    }

    if (aiutomaWpmlData.isWpmlPage && $.fn && $.fn.select2) {
        $('#aiutoma_wpml_model').select2({ width: '300px' });
        $('#aiutoma_wpml_fallback_models').select2({ width: '300px' });
    }
});

