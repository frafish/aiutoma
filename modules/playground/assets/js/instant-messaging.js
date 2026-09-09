document.addEventListener('DOMContentLoaded', function() {
    if (typeof aiutoma_im_vars === 'undefined') return;
    
    fetch(aiutoma_im_vars.api_url, {
        headers: { 'X-WP-Nonce': aiutoma_im_vars.nonce }
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.models) {
            const select = document.getElementById('aiutoma-im-model');
            if (!select) return;
            
            Object.entries(res.models).forEach(([provider, modelsObj]) => {
                const optgroup = document.createElement('optgroup');
                optgroup.label = provider;
                Object.entries(modelsObj).forEach(([id, name]) => {
                    const opt = document.createElement('option');
                    opt.value = id;
                    opt.innerText = name;
                    optgroup.appendChild(opt);
                });
                select.appendChild(optgroup);
            });
            if (select.dataset.selected) select.value = select.dataset.selected;
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                jQuery(select).select2({ width: '100%' });
            }
        }
    });
});
