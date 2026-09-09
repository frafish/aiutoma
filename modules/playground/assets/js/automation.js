// Aiutoma Automation Settings JS
document.addEventListener('DOMContentLoaded', function() {
    if (typeof aiutomaAutomationData === 'undefined') return;

    fetch(aiutomaAutomationData.restModelsUrl, {
        headers: {
            'X-WP-Nonce': aiutomaAutomationData.nonce
        }
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.models) {
            const select = document.getElementById('aiutoma-task-model');
            if (select) {
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
                if (select.dataset.selected) {
                    select.value = select.dataset.selected;
                }
                if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                    jQuery(select).select2({ width: '100%' });
                }
            }
        }
    });

    const taskSchedule = document.getElementById('aiutoma-task-schedule');
    if (taskSchedule) {
        taskSchedule.addEventListener('change', function() {
            document.getElementById('aiutoma-custom-cron-wrap').style.display = this.value === 'custom' ? 'block' : 'none';
            document.getElementById('aiutoma-once-wrap').style.display = this.value === 'once' ? 'block' : 'none';
            if (this.value === 'once') {
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                document.getElementById('aiutoma-task-execute-time').min = now.toISOString().slice(0, 16);
            }
        });
    }

    const saveTaskBtn = document.getElementById('aiutoma-save-task');
    if (saveTaskBtn) {
        saveTaskBtn.addEventListener('click', function() {
            const btn = this;
            const name = document.getElementById('aiutoma-task-name').value;
            const prompt = document.getElementById('aiutoma-task-prompt').value;
            let schedule = document.getElementById('aiutoma-task-schedule').value;
            let execute_time = 0;
            if (schedule === 'custom') {
                schedule = document.getElementById('aiutoma-custom-cron').value;
                if (!schedule) { alert('Custom cron syntax required'); return; }
            } else if (schedule === 'once') {
                const dateVal = document.getElementById('aiutoma-task-execute-time').value;
                if (!dateVal) { alert('Date and Time is required for Execute Once'); return; }
                execute_time = new Date(dateVal).getTime() / 1000;
                if (execute_time < (Date.now() / 1000)) {
                    alert('Please select a future date and time for the execution.');
                    return;
                }
            }
            
            const allowCriticalEl = document.getElementById('aiutoma-task-allow-critical');
            const allow_critical = allowCriticalEl ? allowCriticalEl.checked : true;
            const model = document.getElementById('aiutoma-task-model').value;
            const pause_time = document.getElementById('aiutoma-task-pause-time') ? document.getElementById('aiutoma-task-pause-time').value : '2';
            const task_id = document.getElementById('aiutoma-task-id') ? document.getElementById('aiutoma-task-id').value : '';
            
            if (!name || !prompt) { alert('Name and prompt are required'); return; }
            btn.disabled = true;
            btn.innerText = 'Saving...';
            
            fetch(aiutomaAutomationData.restSaveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiutomaAutomationData.nonce
                },
                body: JSON.stringify({ id: task_id, name: name, prompt: prompt, schedule: schedule, allow_critical: allow_critical, model: model, pause_time: pause_time, execute_time: execute_time })
            }).then(r => r.json()).then(res => {
                location.reload();
            });
        });
    }

    document.querySelectorAll('.aiutoma-delete-task').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Delete this task?')) return;
            const id = this.dataset.id;
            fetch(aiutomaAutomationData.restDeleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiutomaAutomationData.nonce
                },
                body: JSON.stringify({ id: id })
            }).then(r => r.json()).then(res => {
                location.reload();
            });
        });
    });
    
    document.querySelectorAll('.aiutoma-run-task').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const originalText = this.innerText;
            this.innerText = 'Running...';
            this.disabled = true;
            fetch(aiutomaAutomationData.restRunUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiutomaAutomationData.nonce
                },
                body: JSON.stringify({ id: id })
            }).then(r => r.json()).then(res => {
                alert('Task completed!');
                location.reload();
            });
        });
    });
});
