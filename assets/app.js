(function () {
    'use strict';

    if (!window.APP) return;

    async function post(action, params) {
        const fd = new FormData();
        fd.set('action', action);
        fd.set('csrf_token', window.APP.csrf);
        for (const k in params) {
            if (params[k] !== undefined && params[k] !== null) fd.set(k, params[k]);
        }
        const res = await fetch('index.php', { method: 'POST', body: fd });
        return res.json();
    }

    function setMessage(el, text, ok) {
        if (!el) return;
        el.textContent = text;
        el.className = 'form-message ' + (ok ? 'success' : 'error');
    }

    // ---- Dashboard: active project switcher --------------------------------
    const activeSelect = document.getElementById('active-project-select');
    if (activeSelect) {
        activeSelect.addEventListener('change', async () => {
            if (!activeSelect.value) return;
            await post('set_active_project', { id: activeSelect.value });
            window.location.reload();
        });
    }

    // ---- Dashboard: quick command buttons ----------------------------------
    const statusEl = document.getElementById('run-status');
    const outputEl = document.getElementById('run-output');

    async function executeCommand(commandId, customCommand) {
        if (!statusEl) return;
        statusEl.textContent = 'Running...';
        statusEl.className = '';
        outputEl.textContent = '';
        document.querySelectorAll('.run-btn, #run-custom').forEach(b => b.disabled = true);

        try {
            const data = await post('run_command', {
                command_id: commandId,
                custom_command: customCommand || ''
            });

            if (data.message && !data.command) {
                statusEl.textContent = data.message;
                statusEl.className = 'muted';
            } else {
                statusEl.textContent = (data.ok ? 'Success' : 'Failed') +
                    ' — exit code: ' + (data.exit_code ?? 'n/a') +
                    ' — ' + data.duration + 's' +
                    (data.method ? ' — via ' + data.method : '');
                statusEl.className = data.ok ? 'ok' : 'bad';
                let out = '$ ' + data.command + '\n\n';
                out += data.stdout || '';
                if (data.stderr) out += '\n--- stderr ---\n' + data.stderr;
                if (data.message) out += '\n\n' + data.message;
                outputEl.textContent = out;
            }
        } catch (e) {
            statusEl.textContent = 'Request failed: ' + e;
            statusEl.className = 'bad';
        } finally {
            document.querySelectorAll('.run-btn, #run-custom').forEach(b => b.disabled = false);
        }
    }

    document.querySelectorAll('.run-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.commandId;
            if (btn.dataset.danger === '1' && !confirm('Run "' + btn.textContent.trim() + '"? This may change data.')) {
                return;
            }
            executeCommand(id, null);
        });
    });

    const runCustomBtn = document.getElementById('run-custom');
    if (runCustomBtn) {
        runCustomBtn.addEventListener('click', () => {
            const input = document.getElementById('custom-command');
            const cmd = input.value.trim();
            if (!cmd) return;
            if (!confirm('Run this custom command?\n\n' + cmd)) return;
            executeCommand('custom', cmd);
        });
    }

    // ---- Projects page ------------------------------------------------------
    const projectForm = document.getElementById('project-form');
    if (projectForm) {
        const cancelBtn = document.getElementById('project-cancel');
        const msgEl = projectForm.querySelector('.form-message');

        projectForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(projectForm);
            const data = await post('save_project', Object.fromEntries(fd.entries()));
            if (data.ok) {
                window.location.reload();
            } else {
                setMessage(msgEl, data.message, false);
            }
        });

        document.querySelectorAll('.edit-project').forEach(btn => {
            btn.addEventListener('click', () => {
                const p = JSON.parse(btn.dataset.project);
                projectForm.id.value = p.id;
                projectForm.name.value = p.name;
                projectForm.path.value = p.path;
                projectForm.php_bin.value = p.php_bin;
                projectForm.composer_path.value = p.composer_path || '';
                cancelBtn.style.display = 'inline-block';
                projectForm.scrollIntoView({ behavior: 'smooth' });
            });
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                projectForm.reset();
                projectForm.id.value = '';
                cancelBtn.style.display = 'none';
            });
        }

        document.querySelectorAll('.use-project').forEach(btn => {
            btn.addEventListener('click', async () => {
                await post('set_active_project', { id: btn.dataset.id });
                window.location.href = 'index.php?page=dashboard';
            });
        });

        document.querySelectorAll('.delete-project').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Delete this project entry? (Files on disk are untouched.)')) return;
                await post('delete_project', { id: btn.dataset.id });
                window.location.reload();
            });
        });
    }

    // ---- .env editor ----------------------------------------------------------
    const envForm = document.getElementById('env-form');
    if (envForm) {
        const msgEl = envForm.querySelector('.form-message');
        envForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = await post('save_env', { content: envForm.content.value });
            setMessage(msgEl, data.ok ? 'Saved.' : (data.message || 'Failed to save.'), data.ok);
        });
    }

    // ---- System check: composer download --------------------------------------
    const downloadBtn = document.getElementById('download-composer');
    if (downloadBtn) {
        const msgEl = downloadBtn.parentElement.querySelector('.form-message');
        downloadBtn.addEventListener('click', async () => {
            downloadBtn.disabled = true;
            setMessage(msgEl, 'Downloading...', true);
            const data = await post('download_composer', {});
            setMessage(msgEl, data.ok ? 'Downloaded successfully.' : data.message, data.ok);
            downloadBtn.disabled = false;
        });
    }

    // ---- Logs -------------------------------------------------------------------
    const clearLogBtn = document.getElementById('clear-log');
    if (clearLogBtn) {
        clearLogBtn.addEventListener('click', async () => {
            if (!confirm('Clear the activity log?')) return;
            await post('clear_log', {});
            window.location.reload();
        });
    }
})();
