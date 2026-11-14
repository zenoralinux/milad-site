const fs=require('fs');const assert=require('assert');const js=fs.readFileSync('assets/js/builder.js','utf8');
assert(!/(^|[^$])\$\([^)]*\)\.forEach\s*\(/m.test(js),'single-node query helper used as a collection');
assert(js.includes("$$('[data-delete]').forEach")&&js.includes("document.querySelectorAll('[data-add-block]')"),'selector collection fixes');
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
const index=fs.readFileSync('index.php','utf8');
const preview=fs.readFileSync('app/page_builder.php','utf8');
assert(js.includes('function directStyleUpdate(message)') && js.includes("message.type === 'milad-builder-direct-style'"),'direct canvas sizing and style persistence');
assert(js.includes('function moveNestedNode(') && js.includes('data-layer-node-wrap'),'tree navigator and nested drag reorder');
assert(js.includes('function copySelectedNode()') && js.includes('function pasteSelectedNode()') && js.includes('function duplicateSelectedNode()'),'nested copy/paste/duplicate');
assert(js.includes('function showBuilderContextMenu(') && js.includes('data-context-cmd="delete"'),'canvas context menu');
assert(js.includes('builderLayerSearch') && js.includes('builderLayerExpandAll') && js.includes('builderLayerCollapseAll'),'layer search and tree controls');
assert(js.includes("activeDevice==='desktop'") && js.includes('target.responsive[activeDevice]'),'device-specific direct manipulation');
assert(preview.includes('milad-direct-overlay') && preview.includes('data-md-axis="both"'),'in-preview resize handles');
assert(preview.includes('studioPlainText(c.heading_before)') && preview.includes('studioPlainText(c.description)'),'Selected Work plain-text sanitization');
assert((studio.match(/id="builderTemplateModal"/g)||[]).length===1,'single template modal id');
assert(index.includes('function public_plain_text($value)') && index.includes("public_plain_text($project['excerpt'] ?? '')"),'Selected Work project content is sanitized on the public page');
assert(index.includes("public_plain_text($projectIntro['description'] ??"),'Selected Work heading and description are sanitized on the public page');

const advancedTypes=['feature','pricing','accordion','testimonial','gallery','timeline','skill','logo_cloud','team','tabs','newsletter','icon_box'];
advancedTypes.forEach(type=>{
  assert(preview.includes("'" + type + "'"),'preview supports advanced widget ' + type);
});
assert(preview.includes('function markExistingWidgetTextFields()') && preview.includes('data-widget-type'),'server-rendered nested widgets expose inline-edit metadata');
assert(preview.includes("event.type")===false || preview.includes("event.target.closest('[data-builder-edit-field]')"),'preview click handler resolves editable text fields');
assert(preview.includes("document.addEventListener('click',function(event)") && preview.includes("fieldNode.setAttribute('contenteditable','true')"),'single-click text editing is enabled');
assert(preview.includes("item.children) ? item.children : (Array.isArray(c.children) ? c.children : [])"),'nested preview renders content.children fallback');
assert(preview.includes("$children = is_array($widget['children'] ?? null) ? $widget['children'] : (is_array($content['children'] ?? null) ? $content['children'] : []);"),'PHP renderer renders content.children fallback');
assert(js.includes('const studioPalettes =') && js.includes('function applyPaletteToBlock(') && js.includes('function applyPaletteSelection('),'global palette presets recolor saved blocks');
assert(js.includes('function setObjectPath(') && js.includes('message.nodeId') && js.includes('message.legacyField'),'inline editor persists nested and legacy content paths');
assert(js.includes('const frameRect=frame?frame.getBoundingClientRect()') && js.includes('function showBuilderContextMenu('),'canvas context menu uses iframe-to-editor coordinates');
assert(js.slice(js.lastIndexOf('function deleteSelectedNode()')).includes('deleteBlock(Number(block.id))'),'context delete falls back to selected root block');
assert(js.includes('const missing=createdIds.filter') && js.includes('await flushAllPending()'),'template application verifies every saved section');
const paletteApi=fs.readFileSync('api/admin.php','utf8');
const paletteStudio=fs.readFileSync('admin/studio.php','utf8');
assert(paletteApi.includes("'color_palette' => ['palette']") && paletteApi.includes("['violet','ocean','emerald','sunset','rose','graphite']"),'server validates global color palettes');
assert(paletteStudio.includes('name="color_palette"'),'Studio exposes a global palette selector');


assert(preview.includes("if(widgetId.indexOf('legacy-')===0)selection.legacyField") && preview.includes("else if(widgetId)selection.nodeId"),'context menu selects nested nodes instead of their containing block');
assert(js.includes("record.collection.splice(record.index+1,0,clone)") && js.includes("freshenNodeIds(clone)"),'duplicate nested nodes as siblings with fresh IDs');
const inlineClickStart=preview.indexOf("var selection={type:'milad-builder-node-select',key:block.dataset.builderKey,inline:true}");
const inlineClickEnd=preview.indexOf("var widgetNode = event.target.closest('[data-widget-node]')",inlineClickStart);
const inlineClickHandler=preview.slice(inlineClickStart,inlineClickEnd);
assert(inlineClickHandler.includes("if(event.target.closest('a'))event.preventDefault();") && !inlineClickHandler.includes("event.preventDefault();event.stopPropagation();"),'inline editor preserves click caret placement');
assert(preview.includes("tile.setAttribute('data-widget-type','principle_item')") && preview.includes("tile.setAttribute('data-widget-type','stat_item')"),'About principle and Stats cards support inline editing');


assert(js.includes("target=(type==='principle_item'||type==='stat_item')?record.node:nodeContent(record.node)"),'About and Stats inline edits update their actual array records');
const siteCss=fs.readFileSync('assets/css/site.css','utf8');
assert(siteCss.includes('background: linear-gradient(105deg, var(--accent), var(--accent-strong))') && siteCss.includes('background: var(--accent);'),'major brand and button accents use palette tokens');
assert(renderer.includes("html[data-theme=\"light\"]") && renderer.includes("'--surface-raised'"),'palette tokens also apply to light-theme root and site surfaces');

console.log('Milad Studio Pro static invariants passed');