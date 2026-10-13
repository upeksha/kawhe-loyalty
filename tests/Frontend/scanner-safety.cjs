const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');
const html = fs.readFileSync('resources/views/scanner/index.blade.php', 'utf8');
const methods = html.slice(html.indexOf('                confirmAction() {'), html.indexOf('                async redeem('));
for (const mode of ['stamp', 'redeem']) {
    const scanner = vm.runInNewContext('({' + methods + '})', { crypto: webcrypto });
    Object.assign(scanner, { isRedeem: mode === 'redeem', activeStoreId: 1, pendingToken: 'test-card', stampCount: 2, redeemQuantity: 2 });
    let requests = 0;
    scanner[mode] = function(token, quantity) {
        this.actionProcessing = true;
        this.requestKey(mode, token, quantity);
        requests++;
    };
    scanner.confirmAction();
    const firstKey = scanner.actionRequest.key;
    scanner.confirmAction();
    assert.equal(requests, 1, 'Double confirmation sends only one request');
    assert.equal(scanner.actionRequest.key, firstKey, 'Double tap preserves the in-flight retry key');
    scanner.actionProcessing = false;
    assert.equal(scanner.requestKey(mode, 'test-card', 2), firstKey, 'Network retry reuses its key');
    scanner.confirmAction();
    assert.notEqual(scanner.actionRequest.key, firstKey, 'New deliberate action gets a new key');
}
console.log('PASS: stamp/redeem double taps, stable network retries and new-action keys');
