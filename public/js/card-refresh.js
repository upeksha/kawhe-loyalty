export function watchCard({ url, version, verified, fetcher = fetch, doc = document, win = window, reload = () => win.location.reload(), interval = 10000 }) {
    let stopped = false, busy = false, timer;
    const schedule = () => { if (!stopped) timer = win.setTimeout(check, interval); };
    async function check() {
        win.clearTimeout(timer);
        if (stopped || busy) return;
        if (doc.hidden) { schedule(); return; }
        busy = true;
        const controller = new AbortController();
        const timeout = win.setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetcher(url, { cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal });
            if (response.ok) {
                const data = await response.json();
                if (!stopped && (Number(data.version) !== Number(version) || Boolean(data.verified) !== Boolean(verified))) {
                    stopped = true;
                    reload();
                }
            }
        } catch (_) {
            // A temporary connection failure must not hide the existing card.
        } finally {
            win.clearTimeout(timeout);
            busy = false;
            schedule();
        }
    }
    const wake = () => { if (!doc.hidden) void check(); };
    doc.addEventListener('visibilitychange', wake);
    win.addEventListener('online', wake);
    schedule();
    return () => {
        stopped = true;
        win.clearTimeout(timer);
        doc.removeEventListener('visibilitychange', wake);
        win.removeEventListener('online', wake);
    };
}
