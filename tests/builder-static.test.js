const fs=require('fs');const assert=require('assert');const js=fs.readFileSync('assets/js/builder.js','utf8');
assert(!/(^|[^$])\$\(['"][^\n]*?['"]\)\.forEach\(/m.test(js),'single-node query helper used as a collection');
assert(js.includes("$$('[data-delete]').forEach")&&js.includes("$$('[data-add-block]').forEach"),'selector collection fixes');
assert(js.includes('function queueUndoOrder()')&&js.includes("snapshot.kind === 'order'"),'order undo/redo');
assert(js.includes('flushAllPending()')&&js.includes('state.inFlight'),'autosave serialization');
assert(js.includes("updateNestedChildren('move'")&&js.includes("updateNestedChildren('clone'"),'nested operations');
console.log('Milad Studio interaction invariants passed');