import { test } from 'node:test';
import assert from 'node:assert/strict';
import { realtimeConfig } from '../resources/js/realtime-config.js';
test('runtime environment key connects securely to the current origin without build variables',()=>{
 for(const hostname of ['testing.kawhe.shop','app.kawhe.shop']) {
  const c=realtimeConfig({key:hostname+'-public'}, {}, {hostname,protocol:'https:',port:''});
  assert.equal(c.wsHost,hostname);assert.equal(c.key,hostname+'-public');assert.equal(c.wssPort,443);assert.equal(c.forceTLS,true);assert.deepEqual(c.enabledTransports,['wss']);
 }
});
test('runtime settings override stale testing build configuration',()=>{
 const c=realtimeConfig({key:'live-public'}, {VITE_REVERB_APP_KEY:'testing',VITE_REVERB_HOST:'127.0.0.1',VITE_REVERB_PORT:'8081'}, {hostname:'app.kawhe.shop',protocol:'https:',port:''});
 assert.equal(c.key,'live-public');assert.equal(c.wsHost,'app.kawhe.shop');assert.equal(c.wssPort,443);
});
test('missing configuration disables connection; local build configuration still works',()=>{
 assert.equal(realtimeConfig(null,{},{}),null);
 assert.equal(realtimeConfig(null,{VITE_REVERB_APP_KEY:'local',VITE_REVERB_HOST:'localhost',VITE_REVERB_SCHEME:'http',VITE_REVERB_PORT:'8080'},{hostname:'localhost'}).wsPort,8080);
});
