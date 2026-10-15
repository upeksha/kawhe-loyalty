const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const html = fs.readFileSync('resources/views/scanner/index.blade.php', 'utf8');
const start = html.slice(html.indexOf('async startScanner() {'), html.indexOf('async loadCameras()'));
const state = html.slice(html.indexOf('get scannerState() {'), html.indexOf('setFailureContext(' , html.indexOf('get scannerState() {')));
(async () => {
    for (const error of [null, {name: 'NotAllowedError'}, {name: 'NotFoundError'}, {name: 'NotReadableError', message: 'Camera busy'}]) {
        let resolveStart, rejectStart, starts = 0;
        const pending = new Promise((resolve, reject) => { resolveStart = resolve; rejectStart = reject; });
        const scanner = vm.runInNewContext('({' + state + start + '})', {
            document: {getElementById: () => ({innerHTML: ''})}, navigator: {userAgent: 'Desktop'},
            setTimeout: fn => { fn(); }, console: {error() {}, warn() {}},
            Html5Qrcode: class { start() { starts++; return pending; } },
        });
        Object.assign(scanner, {
            activeStoreId: '24', isScanning: false, cameraStarting: false,
            failureContext: {type: ''}, stopScanner: async () => {}, loadCameras: async () => {},
            clearFailureContext() { this.failureContext = {type: ''}; },
            setFailureContext(type, title, detail) { this.failureContext = {type, title, detail}; },
        });
        const running = scanner.startScanner();
        await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
        assert.equal(scanner.isScanning, false);
        assert.equal(scanner.cameraStarting, true);
        assert.notEqual(scanner.scannerState, 'Ready to scan');
        await scanner.startScanner();
        assert.equal(starts, 1, 'Repeated start cannot open a second camera');
        if (error) rejectStart(error); else resolveStart();
        await running;
        assert.equal(scanner.cameraStarting, false);
        assert.equal(scanner.isScanning, !error);
        if (!error) assert.equal(scanner.scannerState, 'Ready to scan');
        else assert.equal(scanner.failureContext.type, error.name === 'NotAllowedError' ? 'permission' : error.name === 'NotFoundError' ? 'no_camera' : 'camera_unavailable');
    }
    console.log('PASS: pending camera, successful startup, permission denied, missing camera, busy camera and repeated starts');
})().catch(error => { console.error(error); process.exitCode = 1; });
