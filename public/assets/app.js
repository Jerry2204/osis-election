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
