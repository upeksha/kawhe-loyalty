import test from 'node:test';
import assert from 'node:assert/strict';
import { cardInstall } from '../../resources/js/card-install.js';

function setup(userAgent, options = {}) {
    const listeners = {};
    const media = { matches: !!options.standalone, addEventListener: (name, fn) => { listeners.mode = fn; } };
    const state = cardInstall({
        navigator: { userAgent, platform: options.platform || '', maxTouchPoints: options.touch || 0, standalone: options.navigatorStandalone },
        matchMedia: () => media,
        addEventListener: (name, fn) => { listeners[name] = fn; },
    });
    state.init();
    return { state, listeners };
}

test('iPhone and desktop-style iPad expose only relevant instructions', async () => {
    for (const [ua, options] of [['iPhone', {}], ['Macintosh', { platform: 'MacIntel', touch: 5 }]]) {
        const { state } = setup(ua, options);
        assert.equal(state.visible, true);
        assert.equal(state.label, 'Add to Home Screen');
        await state.install();
        assert.equal(state.showHelp, true);
        assert.equal(state.android, false);
    }
});
test('Android falls back to instructions while unsupported desktop stays hidden', async () => {
    const { state } = setup('Android Chrome');
    await state.install();
    assert.equal(state.visible, true);
    assert.equal(state.showHelp, true);
    assert.equal(setup('Windows Firefox').state.visible, false);
});
test('standalone and appinstalled hide the control and help', () => {
    assert.equal(setup('iPhone', { navigatorStandalone: true }).state.visible, false);
    assert.equal(setup('Android', { standalone: true }).state.visible, false);
    const { state, listeners } = setup('Android');
    state.showHelp = true;
    listeners.appinstalled();
    assert.equal(state.visible, false);
    assert.equal(state.showHelp, false);
});
test('native prompt is used once and cancellation does not permanently suppress future offers', async () => {
    const { state, listeners } = setup('Windows Chrome');
    let prompted = 0;
    let resolve;
    const userChoice = new Promise(r => { resolve = r; });
    listeners.beforeinstallprompt({ preventDefault() {}, prompt: async () => { prompted++; }, userChoice });
    assert.equal(state.visible, true);
    const first = state.install();
    await state.install();
    assert.equal(prompted, 1);
    resolve({ outcome: 'dismissed' });
    await first;
    assert.equal(state.visible, false);
    assert.match(state.message, /cancelled/);
    listeners.beforeinstallprompt({ preventDefault() {}, prompt: async () => {}, userChoice: Promise.resolve({ outcome: 'accepted' }) });
    assert.equal(state.visible, true);
    await state.install();
    assert.match(state.message, /requested/);
    assert.equal(state.installed, false);
    listeners.appinstalled();
    assert.equal(state.installed, true);
});
test('failed native prompt restores mobile instructions and clears busy state', async () => {
    const { state, listeners } = setup('Android');
    listeners.beforeinstallprompt({ preventDefault() {}, prompt: async () => { throw new Error('Unavailable'); } });
    await state.install();
    assert.equal(state.installing, false);
    assert.equal(state.showHelp, true);
    assert.match(state.message, /could not open/);
    listeners.mode({ matches: true });
    assert.equal(state.visible, false);
});
