const screen = document.querySelector('[data-announcement]');
const fullscreenButton = document.querySelector('[data-fullscreen]');
if (fullscreenButton && document.fullscreenEnabled) {
    fullscreenButton.hidden = false;
    const updateButton = () => {
        fullscreenButton.textContent = document.fullscreenElement ? 'Keluar layar penuh ⛶' : 'Layar penuh ⛶';
        fullscreenButton.setAttribute('aria-pressed', document.fullscreenElement ? 'true' : 'false');
    };
    fullscreenButton.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement) await document.exitFullscreen();
            else await document.documentElement.requestFullscreen();
        } catch {
            document.querySelector('[data-connection-status]').textContent = 'Gunakan F11 pada browser untuk layar penuh. Hasil tetap dapat dilihat pada halaman ini.';
        }
    });
    document.addEventListener('fullscreenchange', updateButton);
    updateButton();
}

if (screen && screen.dataset.preview !== 'true') {
    let checking = false;
    const check = async () => {
        if (document.hidden || checking) return;
        checking = true;
        const message = document.querySelector('[data-connection-status]');
        try {
            const response = await fetch(screen.dataset.statusUrl, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(4000),
            });
            if (!response.ok) throw new Error('Status unavailable');
            const data = await response.json();
            if (typeof data.available !== 'boolean') throw new Error('Invalid status');
            if (String(data.available) !== screen.dataset.available) {
                // On withdrawal, remove results immediately before loading the waiting screen.
                if (!data.available) document.getElementById('announcement-main').replaceChildren();
                window.location.reload();
                return;
            }
            message.textContent = 'Pilihan individu siswa tetap rahasia.';
        } catch {
            message.textContent = 'Pembaruan status tertunda. Memeriksa koneksi kembali secara otomatis.';
        } finally {
            checking = false;
        }
    };
    window.setInterval(check, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
}
