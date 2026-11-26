(() => {
    const panel = document.getElementById('live-queue');
    if (!panel) return;

    const content = panel.querySelector('[data-queue-content]');
    const connection = panel.querySelector('[data-queue-connection]');
    const announcement = panel.querySelector('[data-queue-announcement]');
    const refreshLink = panel.querySelector('[data-queue-refresh]');
    const clinicDate = panel.querySelector('[data-queue-date]');
    let pending = false;
    let signedOut = false;
    let lastMarkup = content.innerHTML.trim();

    function setConnection(label, state) {
        if (connection.textContent === label) return;
        connection.textContent = label;
        connection.dataset.state = state;
        connection.hidden = false;
    }

    function patientStatus() {
        return new Map(Array.from(content.querySelectorAll('[data-visit]'), visit => {
            const status = visit.querySelector('[data-visit-status]').textContent.trim();
            const delayed = visit.querySelector('[data-visit-wait]')?.textContent === 'Delayed';
            const summary = [visit.querySelector('h3').textContent, status];
            if (delayed) summary.push('Consultation delayed');
            return [visit.dataset.visit, summary.join('. ')];
        }));
    }

    function syncPatientHistory() {
        for (const visit of content.querySelectorAll('[data-visit]')) {
            const historyRow = document.getElementById('appointment-row-' + visit.dataset.visit);
            if (!historyRow) continue;
            const state = visit.dataset.visitState;
            const badge = historyRow.querySelector('[data-history-status]');
            const canCancel = state === 'booked' || state === 'waiting';
            badge.textContent = state === 'no_show' ? 'Missed' : state.charAt(0).toUpperCase() + state.slice(1);
            badge.className = 'badge badge-' + (state === 'completed' ? 'completed' : (canCancel || state === 'in consultation' ? 'booked' : 'cancelled'));
            const cancel = historyRow.querySelector('[data-history-cancel]');
            if (cancel) cancel.hidden = !canCancel;
        }
    }

    async function refresh() {
        if (pending || signedOut || document.hidden || content.contains(document.activeElement)) return;
        pending = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch('queue_status.php', {
                credentials: 'same-origin',
                signal: controller.signal,
                cache: 'no-store',
                redirect: 'error',
            });
            if (response.status === 401 || response.status === 403) {
                signedOut = true;
                content.replaceChildren();
                setConnection('Sign in to see live updates', 'offline');
                refreshLink.href = 'login.php';
                refreshLink.textContent = 'Sign in';
                return;
            }
            if (!response.ok || response.headers.get('X-CarePulse-Queue') !== '1') {
                throw new Error('Queue unavailable');
            }
            const date = response.headers.get('X-CarePulse-Date');
            if (date && date !== clinicDate.textContent) clinicDate.textContent = date;
            const next = document.createElement('template');
            next.innerHTML = (await response.text()).trim();
            const markup = next.innerHTML;
            if (markup !== lastMarkup && !content.contains(document.activeElement)) {
                const previous = patientStatus();
                const disclosures = new Set(Array.from(
                    content.querySelectorAll('[data-preserve-disclosure][open]'), detail => detail.dataset.preserveDisclosure
                ));
                for (const detail of next.content.querySelectorAll('[data-preserve-disclosure]')) {
                    detail.open = disclosures.has(detail.dataset.preserveDisclosure);
                }
                content.replaceChildren(next.content);
                lastMarkup = markup;
                syncPatientHistory();
                const changes = [];
                for (const [id, status] of patientStatus()) {
                    if (previous.get(id) !== status) {
                        changes.push(status);
                        content.querySelector('[data-visit="' + id + '"]').classList.add('patient-visit-updated');
                    }
                }
                if (changes.length) announcement.textContent = changes.join('. ');
            }
            setConnection('Live updates', 'live');
        } catch {
            setConnection('Updates paused. Showing last results.', 'offline');
        } finally {
            clearTimeout(timeout);
            pending = false;
        }
    }

    refreshLink.addEventListener('click', event => {
        if (signedOut) return;
        event.preventDefault();
        refresh();
    });
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('online', refresh);
    window.setInterval(refresh, 12000);
    syncPatientHistory();
    refresh();

    const timing = panel.querySelector('[data-queue-timing]');
    if (timing) {
        const doctor = timing.elements.doctor_id;
        doctor.addEventListener('change', () => {
            const selected = doctor.selectedOptions[0];
            timing.elements.min_minutes.value = selected.dataset.min;
            timing.elements.max_minutes.value = selected.dataset.max;
        });
    }
})();
