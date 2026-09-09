// Aiutoma SEO JS
jQuery(document).ready(function($) {
    if (typeof aiutomaSeoData !== 'undefined' && aiutomaSeoData.isSettingsPage) {
        // Load Models
        fetch(aiutomaSeoData.restModelsVisionUrl, {
            headers: { 'X-WP-Nonce': aiutomaSeoData.nonce }
        }).then(res => res.json()).then(data => {
            if (data.success && data.models) {
                const select = $('#aiutoma-seo-model');
                if (select.length) {
                    Object.entries(data.models).forEach(([group, models]) => {
                        const optgroup = $('<optgroup>').attr('label', group);
                        Object.entries(models).forEach(([id, name]) => {
                            const opt = $('<option>').val(id).text(name);
                            if (id === aiutomaSeoData.preferredModel) opt.prop('selected', true);
                            optgroup.append(opt);
                        });
                        select.append(optgroup);
                    });
                    if ($.fn.select2) select.select2({ width: '300px' });
                }
            }
        });

        // Load Text Models
        fetch(aiutomaSeoData.restModelsUrl, {
            headers: { 'X-WP-Nonce': aiutomaSeoData.nonce }
        }).then(res => res.json()).then(data => {
            if (data.success && data.models) {
                const select = $('#aiutoma-seo-text-model');
                if (select.length) {
                    Object.entries(data.models).forEach(([group, models]) => {
                        const optgroup = $('<optgroup>').attr('label', group);
                        Object.entries(models).forEach(([id, name]) => {
                            const opt = $('<option>').val(id).text(name);
                            if (id === aiutomaSeoData.textModel) opt.prop('selected', true);
                            optgroup.append(opt);
                        });
                        select.append(optgroup);
                    });
                    if ($.fn.select2) select.select2({ width: '300px' });
                }
            }
        });

        $('.aiutoma-seo-save-btn').on('click', function() {
            const btn = $('.aiutoma-seo-save-btn');
            btn.prop('disabled', true).text(aiutomaSeoData.textSaving);
            
            let dataPayload = {};
            if ($('#aiutoma-seo-auto').length) {
                dataPayload.auto_optimize = $('#aiutoma-seo-auto').is(':checked') ? 'true' : 'false';
                dataPayload.preferred_model = $('#aiutoma-seo-model').val();
                dataPayload.text_model = $('#aiutoma-seo-text-model').val();
            }
            if ($('#aiutoma-md-enabled').length) {
                dataPayload.markdown_enabled = $('#aiutoma-md-enabled').is(':checked') ? 'true' : 'false';
                dataPayload.markdown_llmstxt_enabled = $('#aiutoma-md-llmstxt-enabled').is(':checked') ? 'true' : 'false';
                dataPayload.markdown_cpts = $('.aiutoma-md-cpt-checkbox:checked').map(function() { return this.value; }).get();
            }

            $.ajax({
                url: aiutomaSeoData.restSaveUrl,
                method: 'POST',
                headers: { 'X-WP-Nonce': aiutomaSeoData.nonce },
                data: dataPayload,
                success: function(res) {
                    btn.prop('disabled', false).text(aiutomaSeoData.textSaveSettings);
                }
            });
        });

        let unoptimizedIds = [];
        
        $('#aiutoma-seo-scan').on('click', function() {
            const btn = $(this);
            btn.prop('disabled', true).text(aiutomaSeoData.textScanning);
            $.ajax({
                url: aiutomaSeoData.restMediaUrl,
                method: 'GET',
                success: function(media) {
                    unoptimizedIds = media.map(m => m.id);
                    btn.prop('disabled', false).text(aiutomaSeoData.textScanComplete + ' ' + unoptimizedIds.length);
                    if (unoptimizedIds.length > 0) {
                        $('#aiutoma-seo-start-bulk').show();
                    }
                }
            });
        });

        $('#aiutoma-seo-start-bulk').on('click', async function() {
            const btn = $(this);
            btn.prop('disabled', true).text(aiutomaSeoData.textProcessing);
            $('#aiutoma-seo-log').show();
            const logList = $('#aiutoma-seo-log-list');
            
            for (let i = 0; i < unoptimizedIds.length; i++) {
                const id = unoptimizedIds[i];
                logList.append(`<li id="seo-log-${id}">${aiutomaSeoData.textProcessingImg} ${id}... </li>`);
                
                try {
                    const res = await $.ajax({
                        url: aiutomaSeoData.restOptimizeMediaUrl,
                        method: 'POST',
                        headers: { 'X-WP-Nonce': aiutomaSeoData.nonce },
                        data: {
                            attachment_id: id,
                            model: $('#aiutoma-seo-model').val()
                        }
                    });
                    if (res.success) {
                        $(`#seo-log-${id}`).append(`<span class="aiutoma-seo-status-success">${aiutomaSeoData.textDoneAlt} "${res.data.alt_text}"</span>`);
                    } else {
                        $(`#seo-log-${id}`).append(`<span class="aiutoma-seo-status-error">${aiutomaSeoData.textFailed}</span>`);
                    }
                } catch (e) {
                    $(`#seo-log-${id}`).append(`<span class="aiutoma-seo-status-error">${aiutomaSeoData.textError}: ${e.responseText || e.statusText}</span>`);
                }
            }
            btn.text(aiutomaSeoData.textFinished);
        });

        // Content SEO Logic
        if ($('#aiutoma-content-seo-model').length) {
            fetch(aiutomaSeoData.restModelsUrl, {
                headers: { 'X-WP-Nonce': aiutomaSeoData.nonce }
            }).then(res => res.json()).then(data => {
                if (data.success && data.models) {
                    const select = $('#aiutoma-content-seo-model');
                    Object.entries(data.models).forEach(([group, models]) => {
                        const optgroup = $('<optgroup>').attr('label', group);
                        Object.entries(models).forEach(([id, name]) => {
                            const opt = $('<option>').val(id).text(name);
                            optgroup.append(opt);
                        });
                        select.append(optgroup);
                    });
                    if ($.fn.select2) select.select2({ width: '300px' });
                }
            });
        }

        $('#aiutoma-content-seo-load').on('click', function() {
            const btn = $(this);
            const type = $('#aiutoma-content-seo-type').val();
            btn.prop('disabled', true).text(aiutomaSeoData.textLoading);
            $('#aiutoma-content-seo-table').hide();
            $('#aiutoma-content-seo-table tbody').empty();
            $('#aiutoma-content-seo-bulk').hide();

            $.ajax({
                url: aiutomaSeoData.restContentListUrl,
                method: 'GET',
                headers: { 'X-WP-Nonce': aiutomaSeoData.nonce },
                data: { post_type: type, paged: 1 },
                success: function(res) {
                    btn.prop('disabled', false).text(aiutomaSeoData.textLoadContent);
                    if (res.success && res.data && res.data.length > 0) {
                        res.data.forEach(post => {
                            const editUrl = aiutomaSeoData.adminPostEditUrl + post.id;
                            const tr = $('<tr>').attr('id', 'content-seo-' + post.id);
                            tr.append('<th scope="row" class="check-column"><input type="checkbox" class="content-seo-cb" value="' + post.id + '"></th>');
                            tr.append('<td><strong><a href="' + editUrl + '" target="_blank">' + post.title + '</a></strong><br><small class="aiutoma-seo-slug">/' + post.slug + '</small></td>');
                            tr.append('<td>' + (post.excerpt ? '<span class="aiutoma-seo-excerpt">' + post.excerpt.substring(0, 80) + (post.excerpt.length > 80 ? '...' : '') + '</span>' : '<em class="aiutoma-seo-no-excerpt">No excerpt</em>') + '</td>');
                            tr.append('<td class="status-cell">' + post.status + '</td>');
                            tr.append('<td><button type="button" class="button btn-optimize-single" data-id="' + post.id + '">' + aiutomaSeoData.textOptimize + '</button></td>');
                            $('#aiutoma-content-seo-table tbody').append(tr);
                        });
                        $('#aiutoma-content-seo-table').show();
                        $('#aiutoma-content-seo-bulk').show();
                    } else {
                        $('#aiutoma-content-seo-table tbody').append('<tr><td colspan="5">' + aiutomaSeoData.textNoContent + '</td></tr>');
                        $('#aiutoma-content-seo-table').show();
                    }
                }
            });
        });

        $('#cb-select-all').on('change', function() {
            $('.content-seo-cb').prop('checked', $(this).is(':checked'));
        });

        const optimizePost = async (id) => {
            const tr = $('#content-seo-' + id);
            const btn = tr.find('.btn-optimize-single');
            const status = tr.find('.status-cell');
            
            btn.prop('disabled', true).text(aiutomaSeoData.textWorking);
            status.html('<span class="aiutoma-seo-status-processing">' + aiutomaSeoData.textProcessing + '</span>');
            
            try {
                const res = await $.ajax({
                    url: aiutomaSeoData.restOptimizeContentUrl,
                    method: 'POST',
                    headers: { 'X-WP-Nonce': aiutomaSeoData.nonce },
                    data: {
                        post_id: id,
                        model: $('#aiutoma-content-seo-model').val()
                    }
                });
                
                if (res.success) {
                    status.html('<span class="aiutoma-seo-status-success">' + aiutomaSeoData.textOptimized + '</span>');
                    btn.text(aiutomaSeoData.textDone);
                } else {
                    status.html('<span class="aiutoma-seo-status-error">' + aiutomaSeoData.textFailed + '</span>');
                    btn.prop('disabled', false).text(aiutomaSeoData.textRetry);
                }
            } catch (e) {
                status.html('<span class="aiutoma-seo-status-error">' + aiutomaSeoData.textError + '</span>');
                btn.prop('disabled', false).text(aiutomaSeoData.textRetry);
            }
        };

        $(document).on('click', '.btn-optimize-single', function() {
            optimizePost($(this).data('id'));
        });

        $('#aiutoma-content-seo-bulk').on('click', async function() {
            const selected = $('.content-seo-cb:checked').map(function() { return this.value; }).get();
            if (selected.length === 0) {
                alert(aiutomaSeoData.textSelectOne);
                return;
            }
            
            const btn = $(this);
            btn.prop('disabled', true).text(aiutomaSeoData.textProcessingItems.replace('%d', selected.length));
            
            for (let i = 0; i < selected.length; i++) {
                await optimizePost(selected[i]);
            }
            
            btn.prop('disabled', false).text(aiutomaSeoData.textOptimizeSelected);
        });
    }

    // Media Library Hook
    $(document).on('click.aiutomaseo', '.aiutoma-seo-optimize-btn', function(e) {
        if (typeof aiutomaSeoData === 'undefined') return;
        
        e.preventDefault();
        var btn = $(this);
        var spinner = btn.siblings('.aiutoma-seo-spinner');
        var postId = btn.data('id');
        
        btn.prop('disabled', true);
        spinner.addClass('is-active');
        
        $.ajax({
            url: aiutomaSeoData.restOptimizeMediaUrl,
            method: 'POST',
            headers: { 'X-WP-Nonce': aiutomaSeoData.nonce },
            data: { attachment_id: postId },
            success: function(res) {
                spinner.removeClass('is-active');
                btn.prop('disabled', false).text(aiutomaSeoData.textOptimizedWithExclamation);
                setTimeout(function(){ btn.text(aiutomaSeoData.textGenerateMeta); }, 3000);
                
                if (typeof wp !== 'undefined' && wp.media && wp.media.model && wp.media.model.Attachment) {
                    var attachment = wp.media.model.Attachment.get(postId);
                    if (attachment) {
                        attachment.fetch();
                    }
                } else {
                    location.reload();
                }
            },
            error: function(err) {
                spinner.removeClass('is-active');
                btn.prop('disabled', false).text(aiutomaSeoData.textError);
                alert("Error: " + (err.responseJSON ? err.responseJSON.message : err.statusText));
            }
        });
    });
});
