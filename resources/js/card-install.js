export function cardInstall(env = window) {
    const nav = env.navigator;
    const ios = /iPad|iPhone|iPod/.test(nav.userAgent) || (nav.platform === 'MacIntel' && nav.maxTouchPoints > 1);
    const android = /Android/.test(nav.userAgent);
    const displayMode = env.matchMedia('(display-mode: standalone)');
    let deferredPrompt = null;
    let bound = false;

    return {
        ios,
        android,
        installed: displayMode.matches || nav.standalone === true,
        available: false,
        installing: false,
        showHelp: false,
        message: '',
        get visible() { return !this.installed && (this.ios || this.android || this.available || this.installing); },
        get label() { return this.installing ? 'Opening…' : (this.ios ? 'Add to Home Screen' : 'Install card'); },
        init() {
            if (bound) return;
            bound = true;
            env.addEventListener('beforeinstallprompt', event => {
                event.preventDefault();
                deferredPrompt = event;
                this.available = true;
            });
            env.addEventListener('appinstalled', () => {
                deferredPrompt = null;
                this.available = false;
                this.installed = true;
                this.showHelp = false;
                this.message = '';
            });
            displayMode.addEventListener?.('change', event => {
                this.installed = event.matches || nav.standalone === true;
                if (this.installed) this.showHelp = false;
            });
        },
        async install() {
            if (this.installed || this.installing) return;
            this.message = '';
            if (!deferredPrompt) {
                this.showHelp = !this.showHelp;
                return;
            }
            const prompt = deferredPrompt;
            deferredPrompt = null;
            this.available = false;
            this.installing = true;
            this.showHelp = false;
            try {
                await prompt.prompt();
                const choice = await prompt.userChoice;
                if (choice.outcome === 'accepted') {
                    this.message = 'Installation requested. You can keep using your card here.';
                } else {
                    this.message = 'Installation cancelled. Your card still works here.';
                }
            } catch {
                this.message = 'Installation could not open. Your card still works here.';
                this.showHelp = this.ios || this.android;
            } finally {
                this.installing = false;
            }
        },
    };
}
