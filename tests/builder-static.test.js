const fs=require('fs');const assert=require('assert');const js=fs.readFileSync('assets/js/builder.js','utf8');
assert(!/(^|[^$])\$\(['"][^\n]*?['"]\)\.forEach\(/m.test(js),'single-node query helper used as a collection');
assert(js.includes("$('[data-delete]').forEach")&&js.includes("document.querySelectorAll('[data-add-block]')"),'selector collection fixes');
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
assert(js.includes("'pricing-table'") && js.includes("'faq-section'") && js.includes("'career-timeline'"),'expanded section template catalog');
assert(js.includes("'full-developer-portfolio'") && js.includes("'full-saas-landing'") && js.includes("'full-cyber-tech'") && js.includes("'full-creative-agency'") && js.includes("'full-editorial-blog'"),'full-page template catalog');
assert(js.includes('Array.isArray(template.blocks)') && js.includes('const createdIds = []'),'multi-section template insertion and rollback');
['pricing','accordion','testimonial','gallery','timeline','skill','logo_cloud','team','tabs','newsletter','icon_box'].forEach(type=>{
  assert(api.includes("'" + type + "' =>"),'API default for ' + type);
  assert(renderer.includes("'" + type + "'"),'server renderer registry for ' + type);
});

const previewBuilder = fs.readFileSync('app/page_builder.php','utf8');
const projectTemplate = fs.readFileSync('index.html','utf8');
const projectRenderer = fs.readFileSync('index.php','utf8');
assert(js.includes('function renderProjectInspector') && js.includes('scheduleProjectSave'),'database-backed project inspector and autosave');
assert(js.includes('function saveProjectOrder') && js.includes('function duplicateProject') && js.includes('function deleteProject'),'project ordering, duplicate and delete actions');
assert(js.includes("type:'milad-builder-project-state'") && previewBuilder.includes('function applyProjectState(projects)'),'project card live preview synchronization');
assert(js.includes('function queueUndoProject') && js.includes("snapshot.kind === 'project-order'"),'project content and ordering undo history');
assert(js.includes('function renderBoxModelControl') && js.includes('data-spacing-link'),'four-sided linked margin and padding editor');
assert(js.includes('<details class="builder-layer-node-wrap"') && js.includes('function bindNestedNavigatorDrag'),'collapsible nested Navigator with child drag and drop');
assert(js.includes('function toggleBlockLock') && js.includes('function toggleNestedNodeLock'),'layer lock controls');
assert(js.includes('const acceptedPreviewMessages = new Set') && js.includes('event.source !== frame.contentWindow'),'validated iframe message allow-list and source check');
assert(previewBuilder.includes('data-resize-handle="tl"') && (previewBuilder.match(/data-resize-handle="/g)||[]).length === 8,'eight canvas resize handles');
assert(previewBuilder.includes('milad-builder-context-menu') && previewBuilder.includes('document.addEventListener(\'contextmenu\''),'canvas-only context menu routing');
assert((projectTemplate.match(/<!-- MILAD_PROJECT_CARDS -->/g)||[]).length === 1,'unique structured Selected Work render marker');
assert(projectRenderer.includes('function render_project_card') && projectRenderer.includes('data-project-card="1"') && projectRenderer.includes("str_replace($projectMarker"),'escaped server-side project renderer');
assert(!projectRenderer.includes('$projectStart') && !projectRenderer.includes('$projectEnd'),'fragile range-based Selected Work injection removed');

assert(js.indexOf('function setNestedNodeVisibility') < js.indexOf('function move(id,delta)'), 'nested Navigator actions are in builder scope');
assert(js.indexOf('function bindNestedNavigatorDrag') < js.indexOf('function move(id,delta)'), 'nested drag binding is in builder scope');
assert(js.indexOf('function toggleLockSelectedElement') < js.indexOf('function moveSelectedElement(delta)'), 'context lock handler is in builder scope');
console.log('Milad Studio Pro static invariants passed');