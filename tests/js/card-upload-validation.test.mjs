import test from 'node:test';
import assert from 'node:assert/strict';
import { validateCardUpload } from '../../resources/js/card-upload-validation.js';
const file = {type: 'image/png', size: 1000};
test('rejects unsupported and oversized files before decoding', async () => {
    const fail = () => { throw new Error('must not decode'); };
    assert.match(await validateCardUpload({...file, type:'application/pdf'}, 'logo', fail), /PNG/);
    assert.match(await validateCardUpload({...file, size:2097153}, 'logo', fail), /2 MB/);
});
test('rejects unreadable and oversized photos but accepts supported sizes', async () => {
    assert.match(await validateCardUpload(file, 'card_photo', async()=>[3001,400]), /3000/);
    assert.match(await validateCardUpload(file, 'stamp_icon_upload', async()=>[400,3001]), /3000/);
    assert.match(await validateCardUpload(file, 'logo', async()=>{throw Error();}), /cannot be read/);
    assert.equal(await validateCardUpload(file, 'card_photo', async()=>[3000,3000]), '');
    assert.equal(await validateCardUpload(file, 'logo', async()=>[3200,1000]), '');
});
