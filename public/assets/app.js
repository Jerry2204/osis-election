/* Progressive enhancement only: validation and vote integrity live on the server. */
document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        if (form.hasAttribute('data-submit-once')) {
            form.dataset.submitting = 'true';
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.dataset.busy = 'true';
            });
            const status = form.querySelector('.submission-status');
            if (status) status.hidden = false;
        }
    });
});

document.querySelectorAll('[data-countdown]').forEach((element) => {
    // Relative duration avoids assuming that all three laptops have identical clocks.
    const deadline = Date.now() + Number(element.dataset.countdown) * 1000;
    const update = () => {
        const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        element.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
        if (remaining === 0) {
            // Do not interrupt a request whose commit status is not yet known.
            if (!document.querySelector('[data-vote-form][data-submitting="true"]')) {
                window.location.replace('/');
            }
        }
    };
    update();
    window.setInterval(update, 1000);
});

const returnHome = document.querySelector('[data-return-home]');
if (returnHome) {
    let remaining = 5;
    window.setInterval(() => {
        remaining--;
        const counter = returnHome.querySelector('[data-return-seconds]');
        if (counter) counter.textContent = String(Math.max(0, remaining));
        if (remaining <= 0) window.location.replace(returnHome.dataset.returnHome);
    }, 1000);
}

window.addEventListener('pageshow', (event) => {
    // Revalidate old pages rather than restoring a former voter's browser state.
    if (event.persisted) window.location.reload();
});

window.addEventListener('offline', () => {
    const status = document.querySelector('[data-vote-form] .submission-status');
    if (status && !status.hidden) {
        status.textContent = 'Koneksi terputus. Status pengiriman belum dapat dipastikan. Hubungi petugas sebelum mencoba kembali.';
    }
});

const candidateForm = document.querySelector('[data-candidate-form]');
if (candidateForm) {
    const summary = candidateForm.querySelector('[data-selection-summary]');
    const updateSelection = () => {
        const choice = candidateForm.querySelector('input[name="candidate_id"]:checked');
        summary.textContent = choice
            ? `Pilihanmu: ${choice.dataset.candidateNumber} · ${choice.dataset.candidateName}`
            : 'Pilih satu calon untuk melanjutkan.';
    };
    candidateForm.addEventListener('change', updateSelection);
    updateSelection();
}

// All requests below read aggregate status only. Polling never submits a vote.
const turnout = document.querySelector('[data-turnout]');
if (turnout) {
    const message = turnout.querySelector('[data-progress-message]');
    const numberFormat = new Intl.NumberFormat('id-ID');
    const percentFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    let updating = false;
    const update = async () => {
        if (document.hidden || updating) return;
        updating = true;
        try {
            const response = await fetch(turnout.dataset.progressUrl, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(8000),
            });
            if (!response.ok) throw new Error('Status unavailable');
            const data = await response.json();
            if (!Array.isArray(data.classes) || !Number.isFinite(data.registered)) throw new Error('Invalid status');
            if (data.status !== turnout.dataset.currentStatus && !document.activeElement?.closest('form')) {
                window.location.reload();
                return;
            }
            turnout.querySelectorAll('[data-metric]').forEach((element) => {
                const key = element.dataset.metric;
                element.textContent = key === 'percentage' ? `${percentFormat.format(data[key])}%` : numberFormat.format(data[key]);
            });
            turnout.querySelector('[data-overall-progress]').value = data.percentage;
            const body = turnout.querySelector('[data-class-progress]');
            const rows = document.createDocumentFragment();
            data.classes.forEach((item) => {
                const row = document.createElement('tr');
                ['class_name', 'registered', 'participated', 'remaining', 'percentage'].forEach((key, index) => {
                    const cell = document.createElement(index === 0 ? 'th' : 'td');
                    if (index === 0) cell.scope = 'row';
                    cell.textContent = key === 'percentage' ? `${percentFormat.format(item[key])}%`
                        : key === 'class_name' ? item[key] : numberFormat.format(item[key]);
                    row.append(cell);
                });
                rows.append(row);
            });
            if (!data.classes.length) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 5;
                cell.textContent = 'Belum ada data siswa. Impor siswa untuk melihat progres per kelas.';
                row.append(cell);
                rows.append(row);
            }
            body.replaceChildren(rows);
            message.textContent = `Diperbarui ${new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', timeStyle: 'medium' }).format(new Date(data.updated_at))} WIB · setiap 10 detik.`;
            message.classList.remove('update-delayed');
        } catch {
            message.textContent = 'Pembaruan tertunda. Menampilkan data terakhir; akan mencoba kembali.';
            message.classList.add('update-delayed');
        } finally {
            updating = false;
        }
    };
    window.setInterval(update, 10000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) update(); });
}

const liveTally = document.querySelector('[data-live-tally]');
if (liveTally) {
    const message = liveTally.querySelector('[data-tally-message]');
    const numbers = new Intl.NumberFormat('id-ID');
    const percentages = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    let updating = false;
    const update = async () => {
        if (document.hidden || updating) return;
        updating = true;
        try {
            const response = await fetch(liveTally.dataset.tallyUrl, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(8000),
            });
            if (!response.ok) throw new Error('Tally unavailable');
            const data = await response.json();
            if (!['preparation', 'open', 'closed'].includes(data.status) || !Number.isInteger(data.total_votes) || data.total_votes < 0 ||
                !Array.isArray(data.candidates) || data.candidates.some(item => typeof item.name !== 'string' ||
                    !Number.isInteger(item.number) || !Number.isInteger(item.votes) || item.votes < 0 ||
                    !Number.isFinite(item.percentage) || item.percentage < 0 || item.percentage > 100)) throw new Error('Invalid tally');
            const updatedTime = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', timeStyle: 'medium' }).format(new Date(data.updated_at));
            const rows = document.createDocumentFragment();
            data.candidates.forEach((candidate) => {
                const row = document.createElement('tr');
                const personCell = document.createElement('td');
                const person = document.createElement('div');
                person.className = 'tally-candidate';
                const number = document.createElement('small');
                number.textContent = `Nomor ${String(candidate.number).padStart(2, '0')}`;
                const name = document.createElement('strong');
                name.textContent = candidate.name;
                person.append(number, name);
                personCell.append(person);
                const graphCell = document.createElement('td');
                const graph = document.createElement('progress');
                graph.max = 100;
                graph.value = candidate.percentage;
                graph.setAttribute('aria-label', `Perolehan ${candidate.name}: ${candidate.votes} suara`);
                const percentage = document.createElement('small');
                percentage.textContent = `${percentages.format(candidate.percentage)}%`;
                graphCell.append(graph, percentage);
                const votesCell = document.createElement('td');
                votesCell.className = 'vote-count';
                votesCell.textContent = numbers.format(candidate.votes);
                row.append(personCell, graphCell, votesCell);
                rows.append(row);
            });
            if (!data.candidates.length) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 3;
                cell.textContent = 'Belum ada calon terdaftar. Tambahkan calon untuk melihat perolehan suara.';
                row.append(cell);
                rows.append(row);
            }
            liveTally.querySelector('[data-tally-rows]').replaceChildren(rows);
            liveTally.querySelector('[data-tally-total]').textContent = numbers.format(data.total_votes);
            liveTally.querySelector('[data-tally-title]').textContent = data.status === 'closed' ? 'Perolehan suara akhir' : 'Perolehan suara sementara';
            liveTally.querySelector('[data-tally-badge]').textContent = { preparation: 'Belum dimulai', open: 'Sementara · khusus guru', closed: 'Hasil akhir' }[data.status];
            message.textContent = `Diperbarui ${updatedTime} WIB · setiap 10 detik.`;
            message.classList.remove('update-delayed');
        } catch {
            message.textContent = 'Pembaruan perolehan tertunda. Menampilkan data terakhir; akan mencoba kembali.';
            message.classList.add('update-delayed');
        } finally {
            updating = false;
        }
    };
    window.setInterval(update, 10000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) update(); });
}

const electionWatch = document.querySelector('[data-election-watch]');
if (electionWatch) {
    let updating = false;
    const checkStatus = async () => {
        if (document.hidden || updating || document.querySelector('form[data-submitting="true"]')) return;
        updating = true;
        try {
            const response = await fetch(electionWatch.dataset.statusUrl, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(8000),
            });
            if (!response.ok) return;
            const data = await response.json();
            if (['preparation', 'open', 'closed'].includes(data.status) && data.status !== electionWatch.dataset.currentStatus) window.location.reload();
        } catch { /* The manual status link remains available without a connection. */ }
        finally { updating = false; }
    };
    window.setInterval(checkStatus, 10000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) checkStatus(); });
}

document.querySelectorAll('[data-schedule-countdown]').forEach((element) => {
    const deadline = Date.now() + Number(element.dataset.scheduleCountdown) * 1000;
    const update = () => {
        const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        if (remaining === 0) {
            element.textContent = electionWatch?.dataset.currentStatus === 'preparation' ? 'Menunggu kesiapan panitia.' : 'Memeriksa status pemilihan…';
            return;
        }
        const days = Math.floor(remaining / 86400);
        const hours = Math.floor(remaining % 86400 / 3600);
        const minutes = Math.floor(remaining % 3600 / 60);
        const seconds = remaining % 60;
        element.textContent = `${days ? `${days} hari ` : ''}${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    };
    update();
    window.setInterval(update, 1000);
});
