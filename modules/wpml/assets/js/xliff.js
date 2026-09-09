document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('aiutoma-wpml-xliff-list');
    const selectAll = document.getElementById('cb-select-all-xliff');
    const translateBtn = document.getElementById('aiutoma-start-xliff-translation');
    const progress = document.getElementById('aiutoma-xliff-progress');
    if (!tbody) return;

    function loadJobs() {
        fetch(aiutomaWpmlData.restXliffGetJobsUrl, {
                headers: {
                    'X-WP-Nonce': aiutomaWpmlData.nonce
                }
            })
            .then(r => r.json())
            .then(data => {
                tbody.innerHTML = '';
                if (!data.success || !data.jobs.length) {
                    tbody.innerHTML = '<tr><td colspan="8">No pending translation jobs found. Go to WPML -> Translation Management to create some.</td></tr>';
                    return;
                }

                data.jobs.forEach(job => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <th class="check-column">
                    <input type="checkbox" class="xliff-job-cb" value="${job.job_id}" />
                </th>
                <td><strong>${job.edit_url ? `<a href="${job.edit_url}" target="_blank">${job.title}</a>` : job.title}</strong><br><small style="color:#777;">ID: ${job.job_id}</small></td>
                <td>${job.type}</td>
                <td>${job.languages}</td>
                <td>${job.translator}</td>
                <td>${job.deadline}</td>
                <td class="job-status">${job.status}</td>
                <td><a href="#" class="button button-small aiutoma-translate-single" data-id="${job.job_id}" style="border-color:#2271b1; color:#2271b1; border-radius:3px;">Translate</a></td>
            `;
                    tbody.appendChild(tr);
                });

                document.querySelectorAll('.aiutoma-translate-single').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const cb = this.closest('tr').querySelector('.xliff-job-cb');
                        cb.checked = true;
                        translateBtn.click();
                    });
                });
            });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.xliff-job-cb').forEach(cb => cb.checked = this.checked);
        });
    }

    if (translateBtn) {
        translateBtn.addEventListener('click', async function() {
            const cbs = document.querySelectorAll('.xliff-job-cb:checked');
            if (!cbs.length) {
                alert('Please select at least one job.');
                return;
            }

            if (!confirm('Are you sure you want to translate ' + cbs.length + ' jobs via AI?')) return;

            translateBtn.disabled = true;

            for (let i = 0; i < cbs.length; i++) {
                const cb = cbs[i];
                const jobId = cb.value;
                const statusTd = cb.closest('tr').querySelector('.job-status');

                progress.textContent = `Translating job ${i+1} of ${cbs.length}...`;
                statusTd.innerHTML = '<span class="otgs-badge" style="background:#2271b1; color:#fff; padding:2px 6px; border-radius:10px; font-size:11px;">Translating...</span>';

                try {
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

                    if (data.success) {
                        statusTd.innerHTML = '<span class="otgs-badge" style="background:#00a32a; color:#fff; padding:2px 6px; border-radius:10px; font-size:11px;">Complete</span>';
                        cb.checked = false;
                    } else {
                        statusTd.innerHTML = '<span style="color:#d63638;">Error: ' + data.message + '</span>';
                    }
                } catch (e) {
                    statusTd.innerHTML = '<span style="color:#d63638;">Request failed.</span>';
                }
            }

            progress.textContent = 'Translation process finished.';
            translateBtn.disabled = false;
            setTimeout(() => loadJobs(), 2000);
        });
    }

    if (typeof aiutomaWpmlData !== 'undefined' && aiutomaWpmlData.isWpmlPage) {
        loadJobs();
    }
});
