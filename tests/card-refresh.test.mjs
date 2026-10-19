import { test } from 'node:test';
import assert from 'node:assert/strict';
import { watchCard } from '../public/js/card-refresh.js';
function setup(fetcher) {
    const doc = new EventTarget(); doc.hidden = false;
    const win = new EventTarget(); let next = 0; const timers = new Map();
    win.setTimeout = (fn) => { timers.set(++next, fn); return next; };
    win.clearTimeout = id => timers.delete(id);
    let reloads = 0;
    const stop = watchCard({url:'/api/card/test',version:2,verified:false,fetcher,doc,win,reload:()=>reloads++});
    return {doc,win,stop,timers,get reloads(){return reloads;},async tick(){const [id,fn]=timers.entries().next().value;timers.delete(id);await fn();}};
}
test('unchanged balance revision never reloads, changed revision reloads once', async()=>{
    let version=2; const s=setup(async()=>({ok:true,json:async()=>({version,verified:false})}));
    await s.tick();assert.equal(s.reloads,0); version=3;await s.tick();assert.equal(s.reloads,1);assert.equal(s.timers.size,0);s.stop();
});
test('hidden cards do not poll and resume checks immediately',async()=>{
    let calls=0;const s=setup(async()=>{calls++;return {ok:true,json:async()=>({version:2,verified:false})};});
    s.doc.hidden=true;await s.tick();assert.equal(calls,0);s.doc.hidden=false;s.doc.dispatchEvent(new Event('visibilitychange'));await new Promise(setImmediate);assert.equal(calls,1);s.stop();
});
test('network failure retries and email confirmation refreshes',async()=>{
    let fail=true;const s=setup(async()=>{if(fail)throw Error();return {ok:true,json:async()=>({version:2,verified:true})};});await s.tick();assert.equal(s.reloads,0);fail=false;await s.tick();assert.equal(s.reloads,1);s.stop();
});
