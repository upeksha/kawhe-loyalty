import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const blade = readFileSync(new URL('../../resources/views/merchant/onboarding/wizard/card-design.blade.php', import.meta.url), 'utf8');
const init = blade.match(/x-init="([\s\S]*?)"\s*>/)[1];
class Input extends EventTarget { constructor(value) { super(); this.value = value; } }

for (const [picker, text] of [['bgColor', 'bgColorText'], ['brandColor', 'brandColorText']]) {
  test(`${text} updates the actual colour field and notifies the Wallet preview`, () => {
    const refs = Object.fromEntries(['bgColor', 'bgColorText', 'brandColor', 'brandColorText'].map(key => [key, new Input('#263e30')]));
    let previewColour = refs[picker].value;
    refs[picker].addEventListener('input', () => { previewColour = refs[picker].value; });
    new Function('$refs', 'brandColor', 'bgColor', init)(refs, '#263e30', '#263e30');
    refs[text].value = '#faedd7';
    refs[text].dispatchEvent(new Event('input'));
    assert.equal(refs[picker].value, '#faedd7');
    assert.equal(previewColour, '#faedd7');
    refs[text].value = '#fa';
    refs[text].dispatchEvent(new Event('input'));
    assert.equal(previewColour, '#faedd7', 'Incomplete input must not replace the valid preview');
    refs[picker].value = '#123456';
    refs[picker].dispatchEvent(new Event('input'));
    assert.equal(refs[text].value, '#123456');
  });
}
