const fs=require('fs');const assert=require('assert');const js=fs.readFileSync('assets/js/builder.js','utf8');
assert(!/(^|[^$])\$\(['"][^\n]*?['"]\)\.forEach\(/m.test(js),'single-node query helper used as a collection');
assert(js.includes("$$('[data-delete]').forEach")&&js.includes("$$('[data-add-block]').forEach"),'selector collection fixes');
assert(!js.includes("$$$("),'no malformed triple-dollar selector');
assert(js.includes("event.origin !== 'null'"),'opaque srcdoc origins are accepted only from the preview frame');
assert(js.split("},'*');").length - 1 >= 3,'parent-to-srcdoc messages use wildcard target origin for opaque frames');
assert(js.includes('function addBlockAtPosition(type,targetId)') && js.includes('dragLibraryType'),'library drag-drop insertion');
assert(js.includes("document.querySelectorAll('[data-template-filter]')"),'template filter uses collection selector');
assert(js.includes('function queueUndoOrder()')&&js.includes("snapshot.kind === 'order'"),'order undo/redo');
assert(js.includes('flushAllPending()')&&js.includes('state.inFlight'),'autosave serialization');
assert(js.includes("updateNestedChildren('move'")&&js.includes("updateNestedChildren('clone'"),'nested operations');

const studio=fs.readFileSync('admin/studio.php','utf8');
const admin=fs.readFileSync('admin/index.php','utf8');
const renderer=fs.readFileSync('app/page_builder.php','utf8');
const api=fs.readFileSync('api/admin.php','utf8');
assert(studio.includes('class="studio-editor"') && studio.includes('builderPreviewDocument'),'standalone Studio route and internal preview');
assert(!admin.includes('id="view-builder"') && !admin.includes("assets/js/builder.js"),'dashboard does not mount Studio');
assert(js.includes('function renderRepeaterControl') && js.includes('data-repeater-add'),'visual repeater controls');
assert(js.includes("'pricing-table'") && js.includes("'faq-section'") && js.includes("'career-timeline'"),'expanded template catalog');
['pricing','accordion','testimonial','gallery','timeline','skill','logo_cloud','team','tabs','newsletter','icon_box'].forEach(type=>{
  assert(api.includes("'" + type + "' =>"),'API default for ' + type);
  assert(renderer.includes("if ($type === '" + type + "'"),'server renderer for ' + type);
});
console.log('Milad Studio Pro static invariants passed');