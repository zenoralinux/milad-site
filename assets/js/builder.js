(() => {
  'use strict';
  const root = document.getElementById('view-builder');
  if (!root) return;

  const body = document.body;
  const basePath = body.dataset.basePath || '';
  const api = basePath + '/api/admin.php';
  const csrf = body.dataset.csrf || '';
  const frame = document.getElementById('builderPreviewFrame');
  const previewShell = document.getElementById('builderPreviewShell');
  const $ = (selector, scope = root) => scope.querySelector(selector);
  const $$ = (selector, scope = root) => Array.from(scope.querySelectorAll(selector));
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[ch]));

  const names = {
    hero:'معرفی اصلی',intro:'نوار معرفی',projects:'پروژه‌ها',
    about:'درباره من و اصول کاری',now:'اکنون و تمرکز فعلی',
    contact:'راه‌های ارتباطی',footer:'پاورقی',
    text:'متن ساده',cta:'دعوت به اقدام',stats:'آمار و اعداد',spacer:'فاصله عمودی',
    container:'کانتینر تو در تو',grid:'گرید واکنش‌گرا',heading:'عنوان',paragraph:'متن',
    button:'دکمه',image:'تصویر',card:'کارت',divider:'خط جداکننده',icon:'آیکون',
    counter:'شمارنده',list:'فهرست',video:'ویدیو',principle_item:'کارت اصل کاری',stat_item:'کارت آماری'
  };
  const fields = {
    hero:[
      ['eyebrow','برچسب بالای عنوان'],['title_line1','خط اول عنوان'],['title_line2','خط دوم عنوان'],
      ['description','توضیحات'],['primary_cta_text','متن دکمه اصلی'],['primary_cta_url','لینک دکمه اصلی'],
      ['secondary_cta_text','متن دکمه دوم'],['secondary_cta_url','لینک دکمه دوم'],['footnote','متن پایانی معرفی']
    ],
    intro:[['text_before','متن اصلی'],['text_emphasis','متن تأکیدی']],
    projects:[['heading_before','بخش اول عنوان'],['heading_emphasis','بخش دوم عنوان'],['description','توضیحات']],
    about:[
      ['section_index','شناسه نمایشی بخش'],['title_line1','خط اول عنوان'],['title_line2','خط دوم عنوان'],
      ['description','توضیحات'],['link_text','متن لینک'],['link_url','آدرس لینک'],
      ['show_principles','نمایش اصول کاری'],['principles_items','کارت‌های اصول کاری (JSON)']
    ],
    now:[
      ['section_index','شناسه نمایشی بخش'],['title_line1','خط اول عنوان'],['title_line2','خط دوم عنوان'],
      ['description','توضیحات'],['status_title','عنوان وضعیت'],['status_subtitle','زیرعنوان وضعیت']
    ],
    contact:[
      ['section_index','شناسه نمایشی بخش'],['title_line1','خط اول عنوان'],['title_line2','خط دوم عنوان'],['description','توضیحات']
    ],
    footer:[['tagline','متن برند پاورقی'],['copyright','متن حقوق نشر']],
    text:[['title','عنوان'],['body','متن']],
    cta:[['title','عنوان'],['body','توضیحات'],['button_text','متن دکمه'],['button_url','آدرس دکمه']],
    stats:[['title','عنوان بخش'],['items','کارت‌های آماری (JSON)']],
    spacer:[['height','ارتفاع (px)']],
    container:[['direction','جهت چیدمان (row/column)'],['children','عناصر داخلی (JSON)']],
    grid:[['columns','تعداد ستون‌ها'],['children','عناصر گرید (JSON)']],
    heading:[['text','متن عنوان'],['level','سطح عنوان (۱ تا ۶)']],
    paragraph:[['text','متن پاراگراف']],
    button:[['text','متن دکمه'],['url','لینک دکمه']],
    image:[['src','آدرس تصویر'],['alt','متن جایگزین']],
    card:[['title','عنوان کارت'],['body','توضیحات کارت'],['children','عناصر داخلی (JSON)']],
    divider:[],
    icon:[['symbol','نماد یا کاراکتر']],
    counter:[['value','مقدار'],['label','عنوان']],
    list:[['items','موارد فهرست (JSON)']],
    video:[['url','لینک Embed ویدیو'],['title','عنوان ویدیو']],
    principle_item:[['number','شماره / نشان'],['title','عنوان'],['description','توضیحات']],
    stat_item:[['value','مقدار / نشان'],['label','عنوان'],['description','توضیحات']]
  };

  let blocks = [];
  let design = {};
  let selectedId = null;
  let selectedNodeId = null;
  let selectedLegacyField = null;
  let dragId = null;
  let activeInspectorTab = 'content';
  let activeLibraryTab = 'elements';
  let loaded = false;
  let previewReady = false;
  const saveStates = new Map();
  let designSaveTimer = null;
  let designSaving = false;
  let designSaveAgain = false;

  async function request(action, method = 'GET', payload = null, id = null) {
    const query = new URLSearchParams({action});
    if (id !== null) query.set('id', String(id));
    const headers = {'Accept':'application/json'};
    if (method !== 'GET') {
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-Token'] = csrf;
    }
    const response = await fetch(api + '?' + query.toString(), {
      method,headers,credentials:'same-origin',
      body:method === 'GET' ? undefined : JSON.stringify(payload || {})
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok === false) throw new Error(result.error?.message || 'درخواست ناموفق بود.');
    return result.data;
  }

  function status(message, kind = '') {
    const node = $('#builderStatus');
    if (!node) return;
    node.textContent = message;
    node.className = 'builder-status' + (kind ? ' is-' + kind : '');
  }

  function blockKey(block) { return String(block.block_key || block.id); }
  function makeNodeId() {
    return 'node-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2,9);
  }
  function normalizeNestedIds(block,usedIds) {
    let changed = false;
    usedIds = usedIds || new Set();
    const visited = [];
    function walk(items,depth) {
      if (!Array.isArray(items) || depth > 8 || visited.indexOf(items) !== -1) return;
      visited.push(items);
      items.slice(0,40).forEach(item => {
        if (!item || typeof item !== 'object') return;
        let nodeId = String(item.id || '').trim();
        if (!nodeId || usedIds.has(nodeId)) { nodeId = makeNodeId(); item.id = nodeId; changed = true; }
        usedIds.add(nodeId);
        const lists = [];
        if (Array.isArray(item.children)) lists.push(item.children);
        if (item.content && Array.isArray(item.content.children)) lists.push(item.content.children);
        lists.forEach(list => walk(list,depth + 1));
      });
    }
    const content = block && block.content || {};
    if (Array.isArray(content.children)) walk(content.children,0);
    if (block && block.block_type === 'about' && Array.isArray(content.principles_items)) {
      content.principles_items.forEach(item => { if (item && typeof item === 'object' && !item.type) item.type = 'principle_item'; });
      walk(content.principles_items,0);
    }
    if (block && block.block_type === 'stats' && Array.isArray(content.items)) {
      content.items.forEach(item => { if (item && typeof item === 'object' && !item.type) item.type = 'stat_item'; });
      walk(content.items,0);
    }
    return changed;
  }
  function nodeContent(node) {
    if (node.content && typeof node.content === 'object' && !Array.isArray(node.content)) return node.content;
    const content = {};
    Object.keys(node || {}).forEach(key => {
      if (!['id','type','styles','children','content'].includes(key)) content[key] = node[key];
    });
    node.content = content;
    return node.content;
  }
  function findNestedNode(block,nodeId) {
    if (!block || !nodeId) return null;
    const visited = [];
    function walk(items,parent,depth) {
      if (!Array.isArray(items) || depth > 8 || visited.indexOf(items) !== -1) return null;
      visited.push(items);
      for (let index = 0; index < Math.min(items.length,40); index++) {
        const node = items[index];
        if (!node || typeof node !== 'object') continue;
        if (String(node.id || '') === String(nodeId)) return {node,parent,collection:items,index};
        const lists = [];
        if (Array.isArray(node.children)) lists.push(node.children);
        if (node.content && Array.isArray(node.content.children)) lists.push(node.content.children);
        for (const list of lists) {
          const found = walk(list,node,depth + 1);
          if (found) return found;
        }
      }
      return null;
    }
    const content = block.content || {};
    const collections = [];
    if (Array.isArray(content.children)) collections.push(content.children);
    if (block.block_type === 'about' && Array.isArray(content.principles_items)) collections.push(content.principles_items);
    if (block.block_type === 'stats' && Array.isArray(content.items)) collections.push(content.items);
    for (const collection of collections) {
      const found = walk(collection,block,0);
      if (found) return found;
    }
    return null;
  }
  function renderResponsiveControls(settings,prefix) {
    settings = settings || {};
    const fields = [
      ['font_size','اندازه فونت','24px'],['width','عرض','100%'],['max_width','حداکثر عرض','640px'],
      ['min_height','حداقل ارتفاع','120px'],['padding','فاصله داخلی','16px'],
      ['margin','حاشیه بیرونی','0 0 16px'],['gap','فاصله عناصر','12px']
    ];
    return '<details class="builder-responsive-controls"><summary>تنظیمات واکنش‌گرا</summary>' +
      ['mobile','tablet'].map(device => '<div class="builder-responsive-device"><strong>' +
        (device === 'mobile' ? 'موبایل' : 'تبلت') + '</strong><div class="builder-responsive-fields">' +
        fields.map(([key,label,placeholder]) => '<label>' + label + '<input name="' + prefix + device + '_' + key +
          '" value="' + escapeHtml(settings[device] && settings[device][key] !== undefined ? settings[device][key] : '') +
          '" placeholder="' + placeholder + '"></label>').join('') +
        '<label>تراز متن<select name="' + prefix + device + '_text_align"><option value="">پیش‌فرض</option>' +
        ['right','center','left','justify'].map(v => '<option value="' + v + '"' +
          (settings[device] && settings[device].text_align === v ? ' selected' : '') + '>' + v + '</option>').join('') +
        '</select></label><label>نوع نمایش<select name="' + prefix + device + '_display"><option value="">پیش‌فرض</option>' +
        ['block','flex','grid','inline-flex','none'].map(v => '<option value="' + v + '"' +
          (settings[device] && settings[device].display === v ? ' selected' : '') + '>' + v + '</option>').join('') +
        '</select></label></div></div>').join('') + '</details>';
  }
  function readResponsiveControls(form,initial,prefix) {
    const result = {
      mobile:Object.assign({},initial && initial.mobile || {}),
      tablet:Object.assign({},initial && initial.tablet || {})
    };
    const keys = ['font_size','width','max_width','min_height','padding','margin','gap','text_align','display'];
    ['mobile','tablet'].forEach(device => keys.forEach(key => {
      const field = form.elements[prefix + device + '_' + key];
      if (!field) return;
      const value = String(field.value || '').trim();
      if (value) result[device][key] = value;
      else delete result[device][key];
    }));
    Object.keys(result).forEach(device => { if (!Object.keys(result[device]).length) delete result[device]; });
    return result;
  }
  function renderElementStyleControls(settings) {
    settings = settings || {};
    return '<div class="builder-element-style-grid">' +
      '<label class="color-field">رنگ پس‌زمینه<input type="color" name="style_background" value="' + escapeHtml(settings.background || '#080b12') + '"></label>' +
      '<label class="builder-check builder-style-default"><input type="checkbox" name="style_background_default" ' + (!settings.background ? 'checked' : '') + '> استفاده از رنگ پیش‌فرض</label>' +
      '<label class="color-field">رنگ متن<input type="color" name="style_text_color" value="' + escapeHtml(settings.text_color || '#f3f5fb') + '"></label>' +
      '<label class="builder-check builder-style-default"><input type="checkbox" name="style_text_color_default" ' + (!settings.text_color ? 'checked' : '') + '> استفاده از رنگ پیش‌فرض</label>' +
      '<label>اندازه فونت<input name="style_font_size" value="' + escapeHtml(settings.font_size ?? '') + '" placeholder="مثلاً 24px"></label>' +
      '<label>وزن فونت<select name="style_font_weight"><option value="">پیش‌فرض</option>' + ['400','500','600','700','800','900'].map(v => '<option value="' + v + '"' + (String(settings.font_weight || '') === v ? ' selected' : '') + '>' + v + '</option>').join('') + '</select></label>' +
      '<label>ارتفاع خط<input name="style_line_height" value="' + escapeHtml(settings.line_height ?? '') + '" placeholder="مثلاً 1.8"></label>' +
      '<label>تراز متن<select name="style_text_align"><option value="">پیش‌فرض</option>' + ['right','center','left','justify'].map(v => '<option value="' + v + '"' + (String(settings.text_align || '') === v ? ' selected' : '') + '>' + v + '</option>').join('') + '</select></label>' +
      '<label>عرض<input name="style_width" value="' + escapeHtml(settings.width ?? '') + '" placeholder="100%"></label>' +
      '<label>حداکثر عرض<input name="style_max_width" value="' + escapeHtml(settings.max_width ?? '') + '" placeholder="640px"></label>' +
      '<label>حداقل ارتفاع<input name="style_min_height" value="' + escapeHtml(settings.min_height ?? '') + '" placeholder="120px"></label>' +
      '<label>فاصله بین عناصر<input name="style_gap" value="' + escapeHtml(settings.gap ?? '') + '" placeholder="16px"></label>' +
      '<label>فاصله داخلی<input name="style_padding" value="' + escapeHtml(settings.padding ?? '') + '" placeholder="16px"></label>' +
      '<label>حاشیه بیرونی<input name="style_margin" value="' + escapeHtml(settings.margin ?? '') + '" placeholder="0 0 16px"></label>' +
      '<label>گردی گوشه‌ها<input name="style_radius" value="' + escapeHtml(settings.radius ?? '') + '" placeholder="12px"></label>' +
      '<label>حاشیه<input name="style_border" value="' + escapeHtml(settings.border ?? '') + '" placeholder="1px solid #333333"></label>' +
      '<label>سایه<input name="style_shadow" value="' + escapeHtml(settings.shadow ?? '') + '" placeholder="0 8px 24px #000000"></label>' +
      '<label>نوع نمایش<select name="style_display"><option value="">پیش‌فرض</option>' + ['block','flex','grid','inline-flex','inline-block'].map(v => '<option value="' + v + '"' + (String(settings.display || '') === v ? ' selected' : '') + '>' + v + '</option>').join('') + '</select></label>' +
      renderResponsiveControls(settings.responsive || {},'style_responsive_') +
      '</div>';
  }
  function readElementStyleControls(form,initial) {
    const next = Object.assign({},initial || {});
    ['background','text_color','font_weight','text_align','border','shadow','display'].forEach(key => {
      const field = form.elements['style_' + key];
      const useDefault = form.elements['style_' + key + '_default'];
      if (useDefault && useDefault.checked) delete next[key];
      else if (field && field.value !== '') next[key] = field.value;
      else if (field) delete next[key];
    });
    ['font_size','line_height','width','max_width','min_height','gap','padding','margin','radius'].forEach(key => {
      const field = form.elements['style_' + key];
      if (field && field.value !== '') next[key] = field.value.trim();
      else if (field) delete next[key];
    });
    next.responsive = readResponsiveControls(form,initial && initial.responsive,'style_responsive_');
    return next;
  }
  function bindElementStyleDefaults(form,sync) {
    [['style_background_default','style_background'],['style_text_color_default','style_text_color']].forEach(([flagName,inputName]) => {
      const flag = form.elements[flagName], input = form.elements[inputName];
      if (!flag || !input) return;
      const update = () => { input.disabled = flag.checked; };
      flag.addEventListener('change',update);
      update();
    });
  }
  function legacyFieldGroup(field) {
    if (['title_line1','title_line2'].includes(field)) return ['title_line1','title_line2'];
    if (['text_before','text_emphasis'].includes(field)) return ['text_before','text_emphasis'];
    if (['heading_before','heading_emphasis'].includes(field)) return ['heading_before','heading_emphasis'];
    return [field];
  }
  function summarize(block) {
    const content = block.content || {};
    return content.description || content.body || content.title || content.title_line1 ||
      content.heading_before || content.text_before || names[block.block_type] || 'برای ویرایش انتخاب کن';
  }
  function titleOf(block) {
    const content = block.content || {};
    return content.title || content.title_line1 || content.heading_before || content.text_before ||
      content.eyebrow || names[block.block_type] || block.label;
  }

  function postPreviewState() {
    if (!previewReady || !frame || !frame.contentWindow) return;
    frame.contentWindow.postMessage({
      type:'milad-builder-state',
      blocks:blocks,
      design:design
    },'*');
    if (selectedId !== null) {
      const selected = blocks.find(item => Number(item.id) === Number(selectedId));
      if (selected) frame.contentWindow.postMessage({
        type:'milad-builder-select-outline',key:blockKey(selected),nodeId:selectedNodeId || '',legacyField:selectedLegacyField || ''
      },'*');
    }
  }

  function renderContextToolbar() {
    const toolbar = $('#builderContextToolbar');
    if (!toolbar) return;
    const block = blocks.find(item => Number(item.id) === Number(selectedId));
    toolbar.hidden = !block;
    if (!block) return;
    const label = $('#builderContextLabel');
    if (label) label.textContent = block.label || names[block.block_type] || block.block_type;
    const clone = toolbar.querySelector('[data-context-action="clone"]');
    if (clone) clone.hidden = ['hero','intro','projects','about','now','contact','footer'].includes(block.block_type);
    const toggle = toolbar.querySelector('[data-context-action="toggle"]');
    if (toggle) toggle.textContent = block.is_visible ? '◉' : '○';
  }

  function renderNavigatorNodes(items,block,depth=0) {
    if (!Array.isArray(items) || depth > 8) return '';
    return items.slice(0,40).map(node => {
      if (!node || typeof node !== 'object') return '';
      const content = node.content || node;
      const type = String(node.type || 'paragraph');
      const label = content.text || content.title || content.label || content.value || names[type] || type;
      const children = Array.isArray(node.children) ? node.children : (Array.isArray(content.children) ? content.children : []);
      return '<div class="builder-layer-node-wrap"><button type="button" class="builder-layer-node' +
        (String(node.id || '') === String(selectedNodeId || '') ? ' is-selected' : '') +
        '" data-node-select="' + escapeHtml(node.id || '') + '" data-block-id="' + Number(block.id) + '">' +
        '<span>' + escapeHtml(names[type] || type) + '</span><small>' + escapeHtml(String(label).slice(0,38)) +
        '</small></button>' + (children.length ? '<div class="builder-layer-children">' +
        renderNavigatorNodes(children,block,depth+1) + '</div>' : '') + '</div>';
    }).join('');
  }
  function renderCanvas() {
    const canvas = $('#builderCanvas');
    if (!blocks.length) {
      canvas.innerHTML = '<div class="builder-empty">صفحه خالی است.<br>از کتابخانه بلوک‌ها اولین بخش را اضافه کن.</div>';
      return;
    }
    canvas.innerHTML = blocks.map(block => {
      const selected = Number(block.id) === Number(selectedId);
      const hidden = !Number(block.is_visible);
      const cloneable = !['hero','intro','projects','about','now','contact','footer'].includes(block.block_type);
      return '<article class="builder-block' + (selected ? ' is-selected' : '') + (hidden ? ' is-hidden' : '') +
        '" draggable="true" data-block-id="' + Number(block.id) + '">' +
        '<span class="builder-drag-handle" title="برای جابه‌جایی بکش">⠿</span>' +
        '<div class="builder-block-copy"><strong>' + escapeHtml(block.label || names[block.block_type] || block.block_type) +
        '</strong><small>' + escapeHtml(names[block.block_type] || block.block_type) + ' · ' +
        (hidden ? 'مخفی' : 'نمایش در سایت') + '</small><div class="builder-block-preview"><b>' +
        escapeHtml(titleOf(block)) + '</b><br>' + escapeHtml(summarize(block)).slice(0,100) + '</div>' +
        renderNavigatorNodes((block.content && (block.content.children || block.content.principles_items || block.content.items)) || [],block) +
        '</div>' +
        '<div class="builder-block-actions"><button type="button" data-up="' + Number(block.id) + '" title="بالا">↑</button>' +
        '<button type="button" data-down="' + Number(block.id) + '" title="پایین">↓</button>' +
        (cloneable ? '<button type="button" data-clone="' + Number(block.id) + '" title="تکثیر">＋</button>' : '') +
        '<button type="button" data-delete="' + Number(block.id) + '" title="حذف">×</button></div></article>';
    }).join('');

    $$('[data-node-select]').forEach(button => button.addEventListener('click',event => {
      event.stopPropagation();
      selectNestedNode(Number(button.dataset.blockId),String(button.dataset.nodeSelect));
    }));
    $$('.builder-block').forEach(node => {
      node.addEventListener('click',event => {
        if (event.target.closest('button')) return;
        selectBlock(Number(node.dataset.blockId));
      });
      node.addEventListener('dragstart',event => {
        dragId = Number(node.dataset.blockId);
        node.setAttribute('aria-grabbed','true');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain',String(dragId));
      });
      node.addEventListener('dragover',event => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        node.classList.add('is-drop-target');
      });
      node.addEventListener('dragleave',() => node.classList.remove('is-drop-target'));
      node.addEventListener('drop',async event => {
        event.preventDefault();
        node.classList.remove('is-drop-target');
        const targetId = Number(node.dataset.blockId);
        if (!dragId || !targetId || dragId === targetId) return;
        const from = blocks.findIndex(item => Number(item.id) === dragId);
        const to = blocks.findIndex(item => Number(item.id) === targetId);
        if (from < 0 || to < 0) return;
        queueUndoOrder();
        const moved = blocks.splice(from,1)[0];
        const targetAfterRemoval = blocks.findIndex(item => Number(item.id) === targetId);
        blocks.splice(targetAfterRemoval < 0 ? blocks.length : targetAfterRemoval,0,moved);
        await saveOrder();
      });
      node.addEventListener('dragend',() => {
        dragId = null;
        node.setAttribute('aria-grabbed','false');
        $$('.builder-block').forEach(item => item.classList.remove('is-drop-target'));
      });
    });
    $$('[data-up]').forEach(button => button.addEventListener('click',() => move(Number(button.dataset.up),-1)));
    $$('[data-down]').forEach(button => button.addEventListener('click',() => move(Number(button.dataset.down),1)));
    $$('[data-clone]').forEach(button => button.addEventListener('click',() => cloneBlock(Number(button.dataset.clone))));
    $$('[data-delete]').forEach(button => button.addEventListener('click',() => deleteBlock(Number(button.dataset.delete))));
    renderContextToolbar();
  }

  function move(id,delta) {
    const from = blocks.findIndex(item => Number(item.id) === id);
    const to = from + delta;
    if (from < 0 || to < 0 || to >= blocks.length) return;
    queueUndoOrder();
    const moved = blocks.splice(from,1)[0];
    blocks.splice(to > from ? to - 1 : to,0,moved);
    saveOrder();
  }

  async function saveOrder() {
    renderCanvas();
    postPreviewState();
    try {
      await request('builder-block-reorder','POST',{ids:blocks.map(item => Number(item.id))});
      status('ترتیب جدید ذخیره شد.','success');
    } catch (error) {
      status(error.message,'error');
      await load();
    }
  }

  function selectBlock(id) {
    selectedId = id;
    selectedNodeId = null;
    selectedLegacyField = null;
    activeInspectorTab = 'content';
    renderCanvas();
    renderInspector();
    postPreviewState();
  }

  function selectNestedNode(blockId,nodeId) {
    selectedId = Number(blockId);
    selectedNodeId = String(nodeId || '');
    selectedLegacyField = null;
    activeInspectorTab = 'content';
    renderCanvas();
    renderInspector();
    postPreviewState();
  }
  function selectLegacyElement(blockId,field) {
    selectedId = Number(blockId);
    selectedNodeId = null;
    selectedLegacyField = String(field || '');
    activeInspectorTab = 'content';
    renderCanvas();
    renderInspector();
    postPreviewState();
  }

  function renderNodeInspector(block,host) {
    const record = findNestedNode(block,selectedNodeId);
    if (!record) {
      selectedNodeId = null;
      renderInspector();
      return;
    }
    const node = record.node, type = String(node.type || 'paragraph');
    const isArrayItem = type === 'principle_item' || type === 'stat_item';
    const content = isArrayItem ? node : nodeContent(node);
    const specs = (fields[type] || []).filter(([key]) => key !== 'children');
    const controls = specs.map(([key,label]) => fieldControl(key,label,content[key],type === 'video' && key === 'url' ? 'video' : '')).join('');
    const nested = ['container','grid','card'].includes(type);
    const children = Array.isArray(node.children) ? node.children : (Array.isArray(content.children) ? content.children : []);
    const nestedTools = nested
      ? '<div class="builder-nested-editor"><div class="builder-panel-heading"><div><strong>فرزندان این المان</strong><small>برای ویرایش هر فرزند روی آن در پیش‌نمایش کلیک کن</small></div><span>' + children.length + '</span></div>' +
        '<div class="builder-nested-add"><select id="builderNodeChildType"><option value="heading">عنوان</option><option value="paragraph">متن</option><option value="button">دکمه</option><option value="card">کارت</option><option value="image">تصویر</option><option value="icon">آیکون</option><option value="divider">جداکننده</option><option value="counter">شمارنده</option><option value="list">فهرست</option><option value="video">ویدیو</option><option value="container">کانتینر</option><option value="grid">گرید</option></select><button type="button" class="secondary-button compact" data-node-child-add>＋ افزودن فرزند</button></div>' +
        '<div class="builder-nested-list">' + children.map((child,index) => {
          const c = child && (child.content || child) || {};
          const childType = String(child.type || 'paragraph');
          const childTitle = c.text || c.title || c.label || names[childType] || childType;
          return '<div class="builder-nested-item" draggable="true" data-nested-index="' + index + '"><span class="builder-nested-grip">⠿</span><span><strong>' + escapeHtml(names[childType] || childType) + '</strong><small>' + escapeHtml(String(childTitle).slice(0,70)) + '</small></span>' +
            '<button type="button" data-node-child-up="' + index + '" title="بالا">↑</button><button type="button" data-node-child-down="' + index + '" title="پایین">↓</button><button type="button" data-node-child-clone="' + index + '" title="تکثیر">＋</button><button type="button" data-node-child-delete="' + index + '" title="حذف">×</button></div>';
        }).join('') + '</div></div>'
      : '';
    host.innerHTML = '<form id="builderNodeForm" class="builder-form">' +
      '<section data-inspector-panel="content"><div class="builder-panel-heading"><div><strong>محتوای ' + escapeHtml(names[type] || type) + '</strong><small>فقط همین المان تغییر می‌کند</small></div></div>' +
      controls + nestedTools + '</section>' +
      '<section data-inspector-panel="style" hidden><div class="builder-panel-heading"><div><strong>استایل همین المان</strong><small>رنگ، تایپوگرافی، ابعاد و فاصله‌ها</small></div></div>' +
      renderElementStyleControls(node.styles || {}) + '</section>' +
      '<section data-inspector-panel="advanced" hidden><div class="builder-panel-heading"><div><strong>مشخصات المان</strong><small>شناسه پایدار و جایگاه در ساختار</small></div></div>' +
      '<div class="builder-note">نوع: <code>' + escapeHtml(type) + '</code><br>شناسه: <code>' + escapeHtml(node.id || '') + '</code><br>بلوک والد: ' + escapeHtml(block.label || names[block.block_type]) + '</div>' +
      '<div class="builder-form-actions"><button type="button" class="secondary-button compact" data-node-delete>حذف این المان</button><button class="primary-button compact" type="button" id="builderNodeSaveNow">ذخیره همین المان</button></div></section></form>';
    const form = $('#builderNodeForm');
    const sync = () => {
      const nextContent = Object.assign({},nodeContent(node));
      let invalid = false;
      specs.forEach(([key]) => {
        const field = form.elements[key];
        if (!field) return;
        if (['items','principles_items'].includes(key)) {
          try {
            const parsed = JSON.parse(field.value || '[]');
            if (!Array.isArray(parsed)) throw new Error('array required');
            nextContent[key] = parsed;
          } catch (error) { invalid = true; }
        } else if (key === 'show_principles') nextContent[key] = field.checked;
        else nextContent[key] = field.value;
      });
      const nextStyles = readElementStyleControls(form,node.styles || {});
      if (!nextStyles) invalid = true;
      if (invalid) { status('مقدار JSON یا تنظیمات واکنش‌گرا معتبر نیست.','error'); return; }
      queueUndo(block);
      if (isArrayItem) {
        specs.forEach(([key]) => { if (Object.prototype.hasOwnProperty.call(nextContent,key)) node[key] = nextContent[key]; });
      } else {
        node.content = nextContent;
      }
      node.styles = nextStyles;
      renderCanvas();
      postPreviewState();
      scheduleSave(block,{label:block.label,content:block.content || {},settings:block.settings || {},is_visible:!!block.is_visible});
    };
    bindElementStyleDefaults(form,sync);
    form.addEventListener('input',sync);
    form.addEventListener('change',sync);
    form.addEventListener('submit',event => event.preventDefault());
    $('#builderNodeSaveNow').addEventListener('click',() => flushSave(Number(block.id),true));
    setInspectorTab(activeInspectorTab);
  }

  function renderLegacyFieldInspector(block,host) {
    const field = selectedLegacyField;
    const keys = legacyFieldGroup(field);
    const content = block.content || {};
    const labels = {title_line1:'خط اول عنوان',title_line2:'خط دوم عنوان',text_before:'متن اصلی',text_emphasis:'متن تأکیدی',heading_before:'بخش اول عنوان',heading_emphasis:'بخش دوم عنوان'};
    const values = keys.map(key => {
      const spec = (fields[block.block_type] || []).find(item => item[0] === key);
      return fieldControl(key,labels[key] || (spec ? spec[1] : key),content[key]);
    }).join('');
    const elementStyles = content.element_styles && content.element_styles[field] || {};
    host.innerHTML = '<form id="builderLegacyForm" class="builder-form">' +
      '<section data-inspector-panel="content"><div class="builder-panel-heading"><div><strong>محتوای المان انتخاب‌شده</strong><small>تغییر فقط روی همین قسمت از ' + escapeHtml(names[block.block_type] || block.block_type) + ' اعمال می‌شود</small></div></div>' + values + '</section>' +
      '<section data-inspector-panel="style" hidden><div class="builder-panel-heading"><div><strong>استایل مستقل المان</strong><small>تنظیمات این قسمت از سایر عناصر جداست</small></div></div>' + renderElementStyleControls(elementStyles) + '</section>' +
      '<section data-inspector-panel="advanced" hidden><div class="builder-note">کلید محتوا: <code>' + escapeHtml(field) + '</code><br>استایل این المان در کنار محتوای بلوک ذخیره می‌شود.</div><div class="builder-form-actions"><button class="primary-button compact" type="button" id="builderLegacySaveNow">ذخیره همین المان</button></div></section></form>';
    const form = $('#builderLegacyForm');
    const sync = () => {
      queueUndo(block);
      block.content = Object.assign({},block.content || {});
      keys.forEach(key => {
        const input = form.elements[key];
        if (input) block.content[key] = input.value;
      });
      block.content.element_styles = Object.assign({},block.content.element_styles || {});
      const nextStyles = readElementStyleControls(form,elementStyles);
      if (!nextStyles) { status('تنظیمات واکنش‌گرا JSON معتبر نیست.','error'); return; }
      block.content.element_styles[field] = nextStyles;
      renderCanvas();
      postPreviewState();
      scheduleSave(block,{label:block.label,content:block.content,settings:block.settings || {},is_visible:!!block.is_visible});
    };
    form.addEventListener('input',sync);
    form.addEventListener('change',sync);
    form.addEventListener('submit',event => event.preventDefault());
    $('#builderLegacySaveNow').addEventListener('click',() => flushSave(Number(block.id),true));
    setInspectorTab(activeInspectorTab);
  }

  function fieldControl(key,label,value,mediaKind='') {
    if (key === 'show_principles') {
      return '<label class="builder-check"><input type="checkbox" name="' + key + '" ' +
        (value === false || value === 0 || value === '0' ? '' : 'checked') + '> ' + escapeHtml(label) + '</label>';
    }
    if (key === 'height') {
      return '<label>' + escapeHtml(label) + '<input type="number" name="' + key + '" min="8" max="240" value="' + escapeHtml(value ?? 48) + '"></label>';
    }
    if (key === 'src') {
      return '<label>' + escapeHtml(label) + '<input name="src" value="' + escapeHtml(value || '') + '" dir="ltr" placeholder="https://…">' +
        '<button type="button" class="secondary-button compact builder-open-media" data-open-media="image">انتخاب تصویر از کتابخانه رسانه</button></label>';
    }
    if (key === 'url' && mediaKind === 'video') {
      return '<label>' + escapeHtml(label) + '<input name="url" value="' + escapeHtml(value || '') + '" dir="ltr" placeholder="https://…">' +
        '<button type="button" class="secondary-button compact builder-open-media" data-open-media="video">انتخاب ویدیو از کتابخانه رسانه</button></label>';
    }
    if (key === 'items' || key === 'principles_items' || key === 'children') {
      return '<label>' + escapeHtml(label) + '<textarea name="' + key + '" dir="ltr" spellcheck="false">' +
        escapeHtml(JSON.stringify(value || [],null,2)) + '</textarea><small>آیتم‌ها را به صورت آرایه JSON ویرایش کن.</small></label>';
    }
    const long = ['description','body'].includes(key) || String(value ?? '').length > 90;
    return '<label>' + escapeHtml(label) + (long
      ? '<textarea name="' + key + '">' + escapeHtml(value || '') + '</textarea>'
      : '<input name="' + key + '" value="' + escapeHtml(value || '') + '"' +
        (key.endsWith('_url') ? ' dir="ltr"' : '') + '>') + '</label>';
  }

  function currentBlockPayload(block,form) {
    const content = Object.assign({},block.content || {});
    const specs = fields[block.block_type] || [];
    let validationFailed = false;
    specs.forEach(([key]) => {
      const field = form.elements[key];
      if (!field) return;
      if (key === 'show_principles') {
        content[key] = field.checked;
      } else if (key === 'items' || key === 'principles_items' || key === 'children') {
        try {
          const parsed = JSON.parse(field.value || '[]');
          if (!Array.isArray(parsed)) throw new Error('array required');
          content[key] = parsed;
        } catch (error) {
          validationFailed = true;
        }
      } else {
        content[key] = field.value;
      }
    });
    const nextSettings = Object.assign({},block.settings || {});
    ['background','text_color','font_weight','text_align','border','shadow','display'].forEach(key => {
      if (form.elements[key] && form.elements[key].value !== '') nextSettings[key] = form.elements[key].value;
      else if (form.elements[key]) delete nextSettings[key];
    });
    ['padding','radius','font_size'].forEach(key => {
      if (form.elements[key] && form.elements[key].value !== '') nextSettings[key] = Number(form.elements[key].value);
      else if (form.elements[key]) delete nextSettings[key];
    });
    ['line_height','width','max_width','min_height','gap'].forEach(key => {
      if (form.elements[key] && form.elements[key].value !== '') nextSettings[key] = form.elements[key].value.trim();
      else if (form.elements[key]) delete nextSettings[key];
    });
    nextSettings.responsive = readResponsiveControls(form,nextSettings.responsive,'responsive_');
    if (validationFailed) return null;
    return {
      label:form.elements.label.value,
      content,
      settings:nextSettings,
      is_visible:form.elements.visible.checked
    };
  }

  async function loadRevisionHistory(blockId) {
    const list = $('#builderRevisionList');
    if (!list) return;
    list.innerHTML = '<small>در حال دریافت نسخه‌ها…</small>';
    try {
      const data = await request('builder-revisions','GET',null,blockId);
      const revisions = data.revisions || [];
      list.innerHTML = revisions.length ? revisions.map(revision => {
        const date = new Date(String(revision.created_at).replace(' ','T') + 'Z');
        const label = Number.isNaN(date.getTime()) ? revision.created_at : date.toLocaleString('fa-IR');
        return '<div class="builder-revision-item"><span>' + escapeHtml(label) + '</span><button type="button" data-restore-revision="' + Number(revision.id) + '">بازیابی</button></div>';
      }).join('') : '<small>هنوز نسخه‌ای ثبت نشده است.</small>';
    } catch (error) { list.innerHTML = '<small>' + escapeHtml(error.message) + '</small>'; }
  }
  async function restoreRevision(revisionId) {
    const block = blocks.find(item => Number(item.id) === Number(selectedId));
    if (!block || !window.confirm('نسخه انتخاب‌شده جایگزین محتوای فعلی این بلوک شود؟')) return;
    try {
      await request('builder-revision-restore','POST',{block_id:Number(block.id),revision_id:Number(revisionId)});
      await load(Number(block.id));
      status('نسخه انتخاب‌شده بازیابی شد.','success');
    } catch (error) { status(error.message,'error'); }
  }

  function setInspectorTab(tab) {
    activeInspectorTab = tab;
    $$('[data-inspector-tab]').forEach(button => {
      button.classList.toggle('is-active',button.dataset.inspectorTab === tab);
    });
    $$('[data-inspector-panel]').forEach(panel => {
      panel.hidden = panel.dataset.inspectorPanel !== tab;
    });
  }

  function setLibraryTab(tab) {
    activeLibraryTab = tab;
    $$('[data-library-tab]').forEach(button => {
      button.classList.toggle('is-active',button.dataset.libraryTab === tab);
    });
    $$('[data-library-panel]').forEach(panel => {
      panel.hidden = panel.dataset.libraryPanel !== tab;
    });
  }

  function renderInspector() {
    const block = blocks.find(item => Number(item.id) === Number(selectedId));
    const host = $('#builderInspectorForm');
    if (!block) {
      host.innerHTML = '<div class="builder-empty">برای ویرایش، روی یک بخش در پیش‌نمایش کلیک کن یا از ساختار صفحه انتخابش کن.</div>';
      setInspectorTab(activeInspectorTab);
      return;
    }
    if (selectedNodeId) { renderNodeInspector(block,host); return; }
    if (selectedLegacyField) { renderLegacyFieldInspector(block,host); return; }
    const specs = fields[block.block_type] || [];
    const controls = specs.map(([key,label]) => fieldControl(key,label,(block.content || {})[key],block.block_type === 'video' && key === 'url' ? 'video' : '')).join('');
    const settings = block.settings || {};
    const nestedChildren = Array.isArray(block.content?.children) ? block.content.children : [];
    const supportsChildren = ['container','grid','card'].includes(block.block_type);
    const nestedTools = supportsChildren
      ? '<div class="builder-nested-editor"><div class="builder-panel-heading"><div><strong>عناصر داخلی</strong><small>ساختار تو در تو را همین‌جا مدیریت کن</small></div><span>' + nestedChildren.length + '</span></div>' +
        '<div class="builder-nested-add"><select id="builderChildType"><option value="heading">عنوان</option><option value="paragraph">متن</option><option value="button">دکمه</option><option value="card">کارت</option><option value="image">تصویر</option><option value="icon">آیکون</option><option value="divider">جداکننده</option><option value="counter">شمارنده</option><option value="list">فهرست</option><option value="container">کانتینر</option><option value="grid">گرید</option></select><button type="button" class="secondary-button compact" data-child-add>＋ افزودن</button></div>' +
        '<div class="builder-nested-list">' + nestedChildren.map((child,index) => {
          const childType = String(child.type || 'paragraph');
          const childContent = child.content || child;
          const childTitle = childContent.text || childContent.title || childContent.label || names[childType] || childType;
          return '<div class="builder-nested-item"><span class="builder-nested-grip">⠿</span><span><strong>' + escapeHtml(names[childType] || childType) + '</strong><small>' + escapeHtml(String(childTitle).slice(0,70)) + '</small></span>' +
            '<button type="button" data-child-up="' + index + '" title="بالا">↑</button><button type="button" data-child-down="' + index + '" title="پایین">↓</button><button type="button" data-child-clone="' + index + '" title="تکثیر">＋</button><button type="button" data-child-delete="' + index + '" title="حذف">×</button></div>';
        }).join('') + '</div><small class="builder-nested-hint">برای ویرایش دقیق متن و داده‌های هر فرزند، ساختار JSON در بخش محتوا نیز قابل ویرایش است.</small></div>'
      : '';

    host.innerHTML = '<form id="builderBlockForm" class="builder-form">' +
      '<section data-inspector-panel="content"><div class="builder-panel-heading"><div><strong>محتوای بلوک</strong><small>متن‌ها و داده‌های این بخش</small></div></div>' +
      controls + nestedTools + '</section>' +
      '<section data-inspector-panel="style" hidden><div class="builder-panel-heading"><div><strong>استایل بلوک</strong><small>ظاهر مستقل این بخش</small></div></div>' +
      '<label class="color-field">رنگ پس‌زمینه<input type="color" name="background" value="' + escapeHtml(settings.background || '#080b12') + '"></label>' +
      '<label class="color-field">رنگ متن<input type="color" name="text_color" value="' + escapeHtml(settings.text_color || '#f3f5fb') + '"></label>' +
      '<label>فاصله داخلی<input type="range" name="padding" min="0" max="180" value="' + escapeHtml(settings.padding ?? 0) + '"></label>' +
      '<label>گردی گوشه‌ها<input type="range" name="radius" min="0" max="48" value="' + escapeHtml(settings.radius ?? 0) + '"></label>' +
      '<label>اندازه فونت (px)<input type="number" name="font_size" min="8" max="180" value="' + escapeHtml(settings.font_size ?? '') + '"></label>' +
      '<label>وزن فونت<select name="font_weight"><option value="">پیش‌فرض</option>' + ['400','500','600','700','800','900'].map(v => '<option value="' + v + '"' + (String(settings.font_weight || '') === v ? ' selected' : '') + '>' + v + '</option>').join('') + '</select></label>' +
      '<label>تراز متن<select name="text_align"><option value="">پیش‌فرض</option>' + ['right','center','left','justify'].map(v => '<option value="' + v + '"' + (String(settings.text_align || '') === v ? ' selected' : '') + '>' + v + '</option>').join('') + '</select></label>' +
      '<label>عرض عنصر<input name="width" placeholder="مثلاً 100% یا 640px" value="' + escapeHtml(settings.width ?? '') + '"></label>' +
      '<label>حداکثر عرض<input name="max_width" placeholder="مثلاً 900px" value="' + escapeHtml(settings.max_width ?? '') + '"></label>' +
      '<label>حداقل ارتفاع<input name="min_height" placeholder="مثلاً 280px" value="' + escapeHtml(settings.min_height ?? '') + '"></label>' +
      '<label>فاصله بین فرزندان<input name="gap" placeholder="مثلاً 24px" value="' + escapeHtml(settings.gap ?? '') + '"></label>' +
      '<label>حاشیه<input name="border" placeholder="1px solid #333333" value="' + escapeHtml(settings.border ?? '') + '"></label>' +
      '<label>سایه<input name="shadow" placeholder="0 12px 30px #000000" value="' + escapeHtml(settings.shadow ?? '') + '"></label>' +
      '<label>نمایش<select name="display"><option value="">پیش‌فرض</option>' + ['block','flex','grid','inline-flex'].map(v => '<option value="' + v + '"' + (String(settings.display || '') === v ? ' selected' : '') + '>' + v + '</option>').join('') + '</select></label>' +
      renderResponsiveControls(settings.responsive || {},'responsive_') + '</section>' +
      '<section data-inspector-panel="advanced" hidden><div class="builder-panel-heading"><div><strong>تنظیمات پیشرفته</strong><small>شناسه و وضعیت نمایش بلوک</small></div></div>' +
      '<label>نام در Navigator<input name="label" maxlength="140" value="' + escapeHtml(block.label || names[block.block_type]) + '"></label>' +
      '<label class="builder-check"><input type="checkbox" name="visible" ' + (block.is_visible ? 'checked' : '') + '> نمایش این بخش در سایت</label>' +
      '<div class="builder-note">کلید داخلی: <code>' + escapeHtml(block.block_key || '') + '</code><br>برای ویرایش مستقیم متن، روی متن در پیش‌نمایش دوبار کلیک کن.</div>' +
      '<div class="builder-revision-panel"><div class="builder-panel-heading"><div><strong>تاریخچه نسخه‌ها</strong><small>۳۰ نسخه اخیر این بلوک</small></div><button type="button" class="secondary-button compact" data-load-revisions>بارگذاری</button></div><div class="builder-revision-list" id="builderRevisionList"><small>برای مشاهده نسخه‌ها بارگذاری کن.</small></div></div>' +
      '<div class="builder-form-actions"><button class="primary-button compact" type="button" id="builderSaveNow">ذخیره همین حالا</button></div></section>' +
      '</form>';

    const form = $('#builderBlockForm');
    const syncFromForm = () => {
      const payload = currentBlockPayload(block,form);
      if (!payload) {
        status('ساختار JSON معتبر نیست؛ تغییرات این فیلد پیش‌نمایش داده نشد.','error');
        return;
      }
      queueUndo(block);
      block.label = payload.label;
      block.content = payload.content;
      block.settings = payload.settings;
      block.is_visible = payload.is_visible;
      renderCanvas();
      postPreviewState();
      scheduleSave(block,payload);
    };
    form.addEventListener('input',syncFromForm);
    form.addEventListener('change',syncFromForm);
    form.addEventListener('submit',event => event.preventDefault());
    $('#builderSaveNow').addEventListener('click',() => {
      const payload = currentBlockPayload(block,form);
      if (!payload) { status('ساختار JSON معتبر نیست.','error'); return; }
      queueUndo(block);
      block.label = payload.label;
      block.content = payload.content;
      block.settings = payload.settings;
      block.is_visible = payload.is_visible;
      postPreviewState();
      flushSave(Number(block.id),true);
    });
    setInspectorTab(activeInspectorTab);
    loadRevisionHistory(Number(block.id));
  }

  const undoStack = [];
  const redoStack = [];
  let pendingUndo = null;
  let pendingUndoTimer = null;

  function cloneData(value) {
    return JSON.parse(JSON.stringify(value));
  }
  function queueUndo(block) {
    if (!pendingUndo || Number(pendingUndo.id) !== Number(block.id)) {
      if (pendingUndo) undoStack.push(pendingUndo);
      pendingUndo = {
        id:Number(block.id),
        payload:{
          label:block.label,
          content:cloneData(block.content || {}),
          settings:cloneData(block.settings || {}),
          is_visible:!!block.is_visible
        }
      };
    }
    if (pendingUndoTimer) window.clearTimeout(pendingUndoTimer);
    pendingUndoTimer = window.setTimeout(() => {
      if (pendingUndo) undoStack.push(pendingUndo);
      pendingUndo = null;
      pendingUndoTimer = null;
      updateHistoryButtons();
    },700);
    redoStack.length = 0;
    updateHistoryButtons();
  }
  function queueUndoOrder() {
    if (pendingUndoTimer) window.clearTimeout(pendingUndoTimer);
    if (pendingUndo) { undoStack.push(pendingUndo); pendingUndo = null; pendingUndoTimer = null; }
    undoStack.push({kind:'order',order:blocks.map(item => Number(item.id))});
    redoStack.length = 0;
    updateHistoryButtons();
  }
  function updateHistoryButtons() {
    const undo = document.getElementById('builderUndo');
    const redo = document.getElementById('builderRedo');
    if (undo) undo.disabled = !undoStack.length && !pendingUndo;
    if (redo) redo.disabled = !redoStack.length;
  }
  async function applyHistorySnapshot(snapshot, destination) {
    if (!snapshot) return;
    if (snapshot.kind === 'order') {
      destination.push({kind:'order',order:blocks.map(item => Number(item.id))});
      const positions = new Map(snapshot.order.map((id,index) => [Number(id),index]));
      blocks.sort((a,b) => (positions.get(Number(a.id)) ?? 999999) - (positions.get(Number(b.id)) ?? 999999));
      await request('builder-block-reorder','POST',{ids:blocks.map(item => Number(item.id))});
      renderCanvas(); postPreviewState(); updateHistoryButtons();
      status('ترتیب قبلی بازیابی شد.','success'); return;
    }
    const current = blocks.find(item => Number(item.id) === Number(snapshot.id));
    if (!current) return;
    if (await flushSave(Number(current.id),true) === false) throw new Error('ذخیره تغییرات معلق ناموفق بود.');
    destination.push({
      id:Number(current.id),
      payload:{
        label:current.label,
        content:cloneData(current.content || {}),
        settings:cloneData(current.settings || {}),
        is_visible:!!current.is_visible
      }
    });
    await request('builder-block-save','PUT',snapshot.payload,snapshot.id);
    current.label = snapshot.payload.label;
    current.content = cloneData(snapshot.payload.content);
    current.settings = cloneData(snapshot.payload.settings);
    current.is_visible = !!snapshot.payload.is_visible;
    selectedId = Number(current.id);
    renderCanvas();
    renderInspector();
    postPreviewState();
    updateHistoryButtons();
    status('تغییر قبلی بازیابی شد.','success');
  }
  async function undoEdit() {
    if (pendingUndoTimer) window.clearTimeout(pendingUndoTimer);
    if (pendingUndo) { undoStack.push(pendingUndo); pendingUndo = null; pendingUndoTimer = null; }
    const snapshot = undoStack.pop();
    if (!snapshot) return;
    try { await applyHistorySnapshot(snapshot,redoStack); }
    catch (error) { undoStack.push(snapshot); status(error.message,'error'); }
  }
  async function redoEdit() {
    const snapshot = redoStack.pop();
    if (!snapshot) return;
    try { await applyHistorySnapshot(snapshot,undoStack); }
    catch (error) { redoStack.push(snapshot); status(error.message,'error'); }
  }

  function scheduleSave(block,payload) {
    const id = Number(block.id);
    let state = saveStates.get(id);
    if (!state) {
      state = {timer:null,payload:null,saving:false,again:false};
      saveStates.set(id,state);
    }
    state.payload = payload;
    if (state.timer) window.clearTimeout(state.timer);
    state.timer = window.setTimeout(() => flushSave(id,false),650);
    status('تغییرات در پیش‌نمایش اعمال شد؛ ذخیره خودکار در صف است.','');
  }

  async function flushSave(id,immediate) {
    const state = saveStates.get(id);
    if (!state || !state.payload) return true;
    if (state.timer) { window.clearTimeout(state.timer); state.timer = null; }
    if (state.inFlight) {
      const prior = await state.inFlight;
      if (prior === false) return false;
      return state.payload ? flushSave(id,true) : true;
    }
    const payload = state.payload;
    state.saving = true;
    state.inFlight = (async () => {
      try {
        await request('builder-block-save','PUT',payload,id);
        if (state.payload === payload) state.payload = null;
        status('تغییرات ذخیره شدند.','success');
        return true;
      } catch (error) { status(error.message,'error'); return false; }
      finally { state.saving = false; state.inFlight = null; }
    })();
    const result = await state.inFlight;
    if (result === false) return false;
    if (state.payload && state.payload !== payload) return flushSave(id,true);
    return true;
  }

  async function flushAllPending() {
    const results = await Promise.all(Array.from(saveStates.keys()).map(id => flushSave(id,true)));
    return results.every(value => value !== false);
  }

  async function addBlock(type) {
    try {
      const result = await request('builder-block-add','POST',{type});
      await load(result.id);
      status('بلوک جدید اضافه شد.','success');
    } catch (error) { status(error.message,'error'); }
  }
  async function cloneBlock(id) {
    try {
      const result = await request('builder-block-duplicate','POST',{},id);
      await load(result.id);
      status('بلوک تکثیر شد.','success');
    } catch (error) { status(error.message,'error'); }
  }
  async function deleteBlock(id) {
    const block = blocks.find(item => Number(item.id) === id);
    if (!block || !window.confirm('بخش «' + (block.label || names[block.block_type]) + '» حذف شود؟')) return;
    if (await flushSave(id,true) === false) return;
    const state = saveStates.get(id);
    if (state && state.timer) window.clearTimeout(state.timer);
    try {
      await request('builder-block-delete','DELETE',{},id);
      if (selectedId === id) selectedId = null;
      await load();
      status('بخش حذف شد.','success');
    } catch (error) { status(error.message,'error'); }
  }

  function renderDesign() {
    const form = $('#builderDesignForm');
    const values = Object.assign({
      accent_color:'#a58aff',page_background:'#080b12',
      content_width:'1160',corner_radius:'22',font_scale:'1',section_spacing:'72'
    },design);
    Object.entries(values).forEach(([key,value]) => {
      if (form.elements[key]) form.elements[key].value = value;
    });
  }
  function readDesignForm() {
    const form = $('#builderDesignForm');
    const payload = {};
    ['accent_color','page_background','content_width','corner_radius','font_scale','section_spacing'].forEach(key => {
      payload[key] = form.elements[key].value;
    });
    return payload;
  }

  async function persistDesign() {
    if (designSaving) {
      designSaveAgain = true;
      return;
    }
    designSaving = true;
    designSaveAgain = false;
    const payload = readDesignForm();
    try {
      design = await request('builder-design','PUT',payload);
      postPreviewState();
      status('ظاهر سایت ذخیره شد.','success');
      return true;
    } catch (error) {
      status(error.message,'error');
      return false;
    } finally {
      designSaving = false;
      if (designSaveAgain) persistDesign();
    }
  }

  function onDesignInput() {
    design = readDesignForm();
    postPreviewState();
    if (designSaveTimer) window.clearTimeout(designSaveTimer);
    designSaveTimer = window.setTimeout(() => {
      designSaveTimer = null;
      persistDesign();
    },650);
    status('ظاهر سایت در پیش‌نمایش تغییر کرد؛ ذخیره خودکار در صف است.','');
  }

  async function saveDesign(event) {
    event.preventDefault();
    if (designSaveTimer) window.clearTimeout(designSaveTimer);
    designSaveTimer = null;
    await persistDesign();
  }

  async function saveAll() {
    const button = $('#builderSaveAll');
    if (button) button.disabled = true;
    try {
      const pending = Array.from(saveStates.keys());
      const results = await Promise.all(pending.map(id => flushSave(id,true)));
      if (designSaveTimer) window.clearTimeout(designSaveTimer);
      designSaveTimer = null;
      const designResult = await persistDesign();
      if (results.every(result => result !== false) && designResult !== false) {
        status('همه تغییرات ذخیره شدند.','success');
      }
    } finally {
      if (button) button.disabled = false;
    }
  }

  function setDevice(device) {
    if (!previewShell || !frame) return;
    previewShell.dataset.device = device;
    const widths = {desktop:'100%',tablet:'768px',mobile:'390px'};
    frame.style.width = widths[device] || '100%';
    $$('.builder-device-tabs button').forEach(button => button.classList.toggle('is-active',button.dataset.device === device));
  }

  async function load(selectId = null) {
    status('در حال دریافت ساختار و محتوای صفحه…');
    try {
      if (loaded && !(await flushAllPending())) {
        status('ذخیره برخی تغییرات ناموفق بود؛ تازه‌سازی لغو شد.','error');
        return;
      }
      const data = await request('builder');
      blocks = data.blocks || [];
      const normalizedBlocks = [];
      const usedNodeIds = new Set();
      blocks.forEach(block => { if (normalizeNestedIds(block,usedNodeIds)) normalizedBlocks.push(block); });
      design = data.design || {};
      if (selectId !== null) { selectedId = Number(selectId); selectedNodeId = null; selectedLegacyField = null; }
      if (!blocks.some(item => Number(item.id) === Number(selectedId))) {
        selectedId = blocks.length ? Number(blocks[0].id) : null;
        selectedNodeId = null; selectedLegacyField = null;
      }
      renderCanvas();
      normalizedBlocks.forEach(block => scheduleSave(block,{label:block.label,content:block.content || {},settings:block.settings || {},is_visible:!!block.is_visible}));
      renderInspector();
      renderDesign();
      loaded = true;
      postPreviewState();
      status('صفحه و پیش‌نمایش آماده‌اند.','success');
    } catch (error) { status(error.message,'error'); }
  }

  const mediaModal = document.getElementById('builderMediaModal');
  const mediaGrid = document.getElementById('builderMediaGrid');
  const mediaStatus = document.getElementById('builderMediaStatus');
  const templateModal = document.getElementById('builderTemplateModal');

  async function mediaRequest(method='GET',formData=null) {
    const options = {method,credentials:'same-origin',headers:{'Accept':'application/json'}};
    if (method !== 'GET') options.headers['X-CSRF-Token'] = csrf;
    if (formData) options.body = formData;
    const response = await fetch(basePath + '/api/media.php',options);
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok === false) throw new Error(result.error?.message || 'عملیات رسانه ناموفق بود.');
    return result.data || {};
  }
  let mediaItems = [];
  let mediaPickerKind = 'image';
  function renderMediaItems() {
    if (!mediaGrid) return;
    const term = String(document.getElementById('builderMediaSearch')?.value || '').trim().toLowerCase();
    const filter = String(document.getElementById('builderMediaFilter')?.value || 'all');
    const visible = mediaItems.filter(file => {
      const kind = String(file.mime_type || '').startsWith('image/') ? 'image' : 'video';
      return (filter === 'all' || filter === kind) &&
        (!term || String(file.filename || '').toLowerCase().includes(term) || String(file.alt_text || '').toLowerCase().includes(term));
    });
    mediaGrid.innerHTML = visible.length ? visible.map(file => {
      const image = String(file.mime_type || '').startsWith('image/');
      const preview = image
        ? '<img src="' + escapeHtml(file.url) + '" alt="' + escapeHtml(file.alt_text || '') + '" loading="lazy">'
        : '<video src="' + escapeHtml(file.url) + '" muted preload="metadata"></video>';
      return '<article class="builder-media-item" data-media-id="' + Number(file.id) + '">' + preview +
        '<div><strong>' + escapeHtml(file.filename) + '</strong><small>' + escapeHtml(file.mime_type) + '</small></div>' +
        '<button type="button" class="secondary-button compact" data-media-select="' + Number(file.id) +
        '" data-media-kind="' + (image ? 'image' : 'video') + '" data-media-url="' + escapeHtml(file.url) +
        '" data-media-alt="' + escapeHtml(file.alt_text || '') + '">انتخاب</button></article>';
    }).join('') : '<p class="builder-empty">فایلی با این فیلتر پیدا نشد.</p>';
  }
  async function loadMedia() {
    if (!mediaGrid) return;
    mediaGrid.innerHTML = '<p class="builder-empty">در حال دریافت فایل‌ها…</p>';
    try {
      const data = await mediaRequest();
      mediaItems = data.files || [];
      renderMediaItems();
    } catch (error) { mediaGrid.innerHTML = '<p class="builder-empty">' + escapeHtml(error.message) + '</p>'; }
  }
  function openMediaPicker(kind='image') {
    if (!mediaModal) return;
    mediaPickerKind = kind;
    const filter = document.getElementById('builderMediaFilter');
    if (filter) filter.value = kind === 'video' ? 'video' : 'all';
    mediaModal.hidden = false;
    loadMedia();
  }
  document.getElementById('builderMediaSearch')?.addEventListener('input',renderMediaItems);
  document.getElementById('builderMediaFilter')?.addEventListener('change',renderMediaItems);
  if (mediaModal) {
    mediaModal.addEventListener('click',event => {
      if (event.target === mediaModal || event.target.closest('[data-media-close]')) mediaModal.hidden = true;
      const select = event.target.closest('[data-media-select]');
      if (select) {
        const form = $('#builderNodeForm') || $('#builderLegacyForm') || $('#builderBlockForm');
        const kind = select.dataset.mediaKind || 'image';
        const input = form && form.elements[kind === 'video' ? 'url' : 'src'];
        if (input && kind === mediaPickerKind) {
          input.value = select.dataset.mediaUrl || '';
          input.dispatchEvent(new Event('input',{bubbles:true}));
          if (kind === 'image' && form.elements.alt && !form.elements.alt.value) {
            form.elements.alt.value = select.dataset.mediaAlt || '';
            form.elements.alt.dispatchEvent(new Event('input',{bubbles:true}));
          }
        }
        mediaModal.hidden = true;
      }
    });
  }
  let nestedDragSource = null;
  root.addEventListener('dragstart',event => {
    const item = event.target.closest('.builder-nested-item[data-nested-index]');
    if (!item) return;
    nestedDragSource = {list:item.parentElement,index:Number(item.dataset.nestedIndex)};
    item.classList.add('is-dragging'); event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain',String(item.dataset.nestedIndex));
  });
  root.addEventListener('dragover',event => {
    const item = event.target.closest('.builder-nested-item[data-nested-index]');
    if (item && nestedDragSource && nestedDragSource.list === item.parentElement) { event.preventDefault(); item.classList.add('is-drop-target'); }
  });
  root.addEventListener('drop',event => {
    const item = event.target.closest('.builder-nested-item[data-nested-index]');
    if (!item || !nestedDragSource || nestedDragSource.list !== item.parentElement) return;
    event.preventDefault(); updateNestedChildren('move',nestedDragSource.index,Number(item.dataset.nestedIndex)); nestedDragSource = null;
  });
  root.addEventListener('dragend',() => { nestedDragSource = null; });
  root.addEventListener('click',event => {
    const mediaTrigger = event.target.closest('[data-open-media]');
    if (mediaTrigger) openMediaPicker(mediaTrigger.dataset.openMedia || 'image');
    const addChild = event.target.closest('[data-child-add]');
    if (addChild) updateNestedChildren('add',-1);
    const removeChild = event.target.closest('[data-child-delete]');
    if (removeChild) updateNestedChildren('delete',Number(removeChild.dataset.childDelete));
    const upChild = event.target.closest('[data-child-up]');
    if (upChild) updateNestedChildren('up',Number(upChild.dataset.childUp));
    const downChild = event.target.closest('[data-child-down]');
    if (downChild) updateNestedChildren('down',Number(downChild.dataset.childDown));
    const cloneChild = event.target.closest('[data-child-clone]');
    if (cloneChild) updateNestedChildren('clone',Number(cloneChild.dataset.childClone));
    const nodeAdd = event.target.closest('[data-node-child-add]');
    if (nodeAdd) updateNestedChildren('add',-1);
    const nodeRemove = event.target.closest('[data-node-child-delete]');
    if (nodeRemove) updateNestedChildren('delete',Number(nodeRemove.dataset.nodeChildDelete));
    const nodeUp = event.target.closest('[data-node-child-up]');
    if (nodeUp) updateNestedChildren('up',Number(nodeUp.dataset.nodeChildUp));
    const nodeDown = event.target.closest('[data-node-child-down]');
    if (nodeDown) updateNestedChildren('down',Number(nodeDown.dataset.nodeChildDown));
    const cloneNodeChild = event.target.closest('[data-node-child-clone]');
    if (cloneNodeChild) updateNestedChildren('clone',Number(cloneNodeChild.dataset.nodeChildClone));
    const nodeDelete = event.target.closest('[data-node-delete]');
    if (nodeDelete) deleteSelectedNode();
    const loadHistory = event.target.closest('[data-load-revisions]');
    if (loadHistory && selectedId !== null) loadRevisionHistory(Number(selectedId));
    const restore = event.target.closest('[data-restore-revision]');
    if (restore) restoreRevision(Number(restore.dataset.restoreRevision));
    const contextAction = event.target.closest('[data-context-action]');
    if (contextAction && selectedId !== null) {
      const action = contextAction.dataset.contextAction;
      if (action === 'up') move(Number(selectedId),-1);
      else if (action === 'down') move(Number(selectedId),1);
      else if (action === 'clone') cloneBlock(Number(selectedId));
      else if (action === 'delete') deleteBlock(Number(selectedId));
      else if (action === 'toggle') {
        const block = blocks.find(item => Number(item.id) === Number(selectedId));
        if (block) {
          block.is_visible = !block.is_visible;
          const payload = {label:block.label,content:block.content || {},settings:block.settings || {},is_visible:block.is_visible};
          postPreviewState();
          renderCanvas();
          scheduleSave(block,payload);
        }
      }
    }
  });
  document.getElementById('builderMediaUpload')?.addEventListener('submit',async event => {
    event.preventDefault();
    if (mediaStatus) mediaStatus.textContent = 'در حال بارگذاری…';
    try {
      const formData = new FormData(event.currentTarget);
      await mediaRequest('POST',formData);
      event.currentTarget.reset();
      if (mediaStatus) mediaStatus.textContent = 'فایل بارگذاری شد.';
      await loadMedia();
    } catch (error) {
      if (mediaStatus) mediaStatus.textContent = error.message;
    }
  });
  const templates = {
    'feature-grid':{type:'grid',label:'گرید ویژگی‌ها',content:{columns:3,children:[
      {id:'node-feature-1',type:'card',content:{title:'طراحی دقیق',body:'توضیح کوتاه درباره ویژگی اول'}},
      {id:'node-feature-2',type:'card',content:{title:'توسعه سریع',body:'توضیح کوتاه درباره ویژگی دوم'}},
      {id:'node-feature-3',type:'card',content:{title:'واکنش‌گرا',body:'توضیح کوتاه درباره ویژگی سوم'}}
    ]}},
    callout:{type:'cta',label:'دعوت به اقدام',content:{title:'آماده شروع هستی؟',body:'برای شروع یک گفت‌وگو با من در ارتباط باش.',button_text:'تماس با من',button_url:'#contact'}},
    'profile-intro':{type:'container',label:'معرفی شخصی',content:{direction:'column',children:[
      {id:'node-profile-heading',type:'heading',content:{text:'سلام، من میلاد هستم',level:2}},
      {id:'node-profile-copy',type:'paragraph',content:{text:'اینجا می‌توانی معرفی کوتاهی درباره خودت بنویسی.'}},
      {id:'node-profile-button',type:'button',content:{text:'مشاهده پروژه‌ها',url:'#work'}}
    ]}}
  };
  async function applyTemplate(key) {
    const template = templates[key];
    if (!template) return;
    try {
      const created = await request('builder-block-add','POST',{type:template.type});
      await request('builder-block-save','PUT',{
        label:template.label,content:template.content,settings:{},is_visible:true
      },created.id);
      if (templateModal) templateModal.hidden = true;
      await load(created.id);
      status('قالب آماده به صفحه اضافه شد.','success');
    } catch (error) { status(error.message,'error'); }
  }
  $('#builderTemplates')?.addEventListener('click',() => { if (templateModal) templateModal.hidden = false; });
  if (templateModal) templateModal.addEventListener('click',event => {
    if (event.target === templateModal || event.target.closest('[data-template-close]')) templateModal.hidden = true;
    const button = event.target.closest('[data-template]');
    if (button) applyTemplate(button.dataset.template);
  });


  $$('[data-add-block]').forEach(button => button.addEventListener('click',() => addBlock(button.dataset.addBlock)));
  $$('[data-library-tab]').forEach(button => button.addEventListener('click',() => setLibraryTab(button.dataset.libraryTab)));
  $$('[data-inspector-tab]').forEach(button => button.addEventListener('click',() => setInspectorTab(button.dataset.inspectorTab)));
  $('#builderSaveAll')?.addEventListener('click',saveAll);
  function regenerateNodeIds(node) {
    if (!node || typeof node !== 'object') return;
    node.id = makeNodeId();
    const content = node.content && typeof node.content === 'object' ? node.content : {};
    (Array.isArray(node.children) ? node.children : (Array.isArray(content.children) ? content.children : [])).forEach(regenerateNodeIds);
  }
  function newChildNode(type) {
    const id = makeNodeId();
    const defaults = {
      heading:{content:{text:'عنوان جدید',level:2}},
      paragraph:{content:{text:'متن جدید را وارد کن.'}},
      button:{content:{text:'دکمه جدید',url:'#contact'}},
      card:{content:{title:'عنوان کارت',body:'توضیحات کارت'}},
      image:{content:{src:'',alt:''}},
      icon:{content:{symbol:'✦'}},
      divider:{content:{}},
      counter:{content:{value:'۱۲+',label:'عنوان آمار'}},
      list:{content:{items:[{text:'مورد اول'},{text:'مورد دوم'}]}},
      video:{content:{url:'',title:'ویدیو'}},
      container:{content:{direction:'column'},children:[]},
      grid:{content:{columns:2},children:[
        {id:id+'-a',type:'card',content:{title:'کارت اول',body:'توضیحات'}},
        {id:id+'-b',type:'card',content:{title:'کارت دوم',body:'توضیحات'}}
      ]}
    };
    const item = defaults[type] || defaults.paragraph;
    return Object.assign({id,type},cloneData(item));
  }
  function deleteSelectedNode() {
    const block = blocks.find(item => Number(item.id) === Number(selectedId));
    const record = block && findNestedNode(block,selectedNodeId);
    if (!record) return;
    if (!window.confirm('این المان و تمام فرزندان آن حذف شوند؟')) return;
    queueUndo(block);
    record.collection.splice(record.index,1);
    selectedNodeId = null;
    renderCanvas();
    renderInspector();
    postPreviewState();
    scheduleSave(block,{label:block.label,content:block.content || {},settings:block.settings || {},is_visible:!!block.is_visible});
  }

  function updateNestedChildren(action,index,targetIndex) {
    const block = blocks.find(item => Number(item.id) === Number(selectedId));
    if (!block) return;
    const selectedRecord = selectedNodeId ? findNestedNode(block,selectedNodeId) : null;
    const parentNode = selectedRecord && selectedRecord.node;
    if (!selectedRecord && !['container','grid','card'].includes(block.block_type)) return;
    const content = parentNode ? nodeContent(parentNode) : (block.content = Object.assign({},block.content || {}));
    const children = parentNode
      ? (Array.isArray(parentNode.children) ? parentNode.children.slice() : (Array.isArray(content.children) ? content.children.slice() : []))
      : (Array.isArray(content.children) ? content.children.slice() : []);
    if (action === 'add') {
      const select = $('#builderNodeChildType') || $('#builderChildType');
      children.push(newChildNode(select ? select.value : 'paragraph'));
    } else if (action === 'clone') {
      if (index < 0 || index >= children.length) return;
      const copy = cloneData(children[index]); regenerateNodeIds(copy);
      children.splice(index + 1,0,copy);
    } else if (action === 'move') {
      if (index < 0 || index >= children.length || targetIndex < 0 || targetIndex >= children.length) return;
      const moved = children.splice(index,1)[0];
      children.splice(targetIndex > index ? targetIndex - 1 : targetIndex,0,moved);
    } else if (action === 'delete') {
      if (index < 0 || index >= children.length) return;
      children.splice(index,1);
    } else if (action === 'up' || action === 'down') {
      const target = index + (action === 'up' ? -1 : 1);
      if (index < 0 || index >= children.length || target < 0 || target >= children.length) return;
      const moved = children.splice(index,1)[0];
      children.splice(target,0,moved);
    }
    queueUndo(block);
    if (parentNode) parentNode.children = children;
    else block.content.children = children;
    const payload = {label:block.label,content:block.content,settings:block.settings || {},is_visible:!!block.is_visible};
    renderCanvas();
    renderInspector();
    postPreviewState();
    scheduleSave(block,payload);
  }

  $('#builderUndo')?.addEventListener('click',undoEdit);
  $('#builderRedo')?.addEventListener('click',redoEdit);
  document.addEventListener('keydown',event => {
    if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 'z') return;
    const tag = event.target && event.target.tagName ? event.target.tagName.toLowerCase() : '';
    if (['input','textarea','select'].includes(tag)) return;
    event.preventDefault();
    if (event.shiftKey) redoEdit(); else undoEdit();
  });
  $('#builderDesignForm')?.addEventListener('submit',saveDesign);
  $('#builderDesignForm')?.addEventListener('input',onDesignInput);
  $('#builderReload')?.addEventListener('click',() => {
    if (frame) {
      const internalDocument = frame.getAttribute('srcdoc');
      if (internalDocument) frame.srcdoc = internalDocument;
    }
    load();
  });
  $$('.builder-device-tabs button').forEach(button => button.addEventListener('click',() => setDevice(button.dataset.device)));

  let previewHandshakeAttempts = 0;
  function pingPreview() {
    if (previewReady || !frame || !frame.contentWindow) return;
    frame.contentWindow.postMessage({type:'milad-builder-ping'},'*');
    previewHandshakeAttempts += 1;
    if (previewHandshakeAttempts < 12) window.setTimeout(pingPreview,250);
  }
  if (frame) {
    frame.addEventListener('load',() => {
      previewReady = false;
      previewHandshakeAttempts = 0;
      pingPreview();
    });
  }
  window.addEventListener('message',event => {
    if (!frame || event.source !== frame.contentWindow || (event.origin !== window.location.origin && event.origin !== 'null')) return;
    const message = event.data || {};
    if (message.type === 'milad-builder-ready') {
      previewReady = true;
      postPreviewState();
    } else if (message.type === 'milad-builder-select') {
      const block = blocks.find(item => blockKey(item) === String(message.key));
      if (block) selectBlock(Number(block.id));
    } else if (message.type === 'milad-builder-node-select') {
      const block = blocks.find(item => blockKey(item) === String(message.key));
      if (!block) return;
      if (message.legacyField) selectLegacyElement(Number(block.id),String(message.legacyField));
      else if (message.nodeId) selectNestedNode(Number(block.id),String(message.nodeId));
      else selectBlock(Number(block.id));
    } else if (message.type === 'milad-builder-inline-edit') {
      const block = blocks.find(item => blockKey(item) === String(message.key));
      const field = String(message.field || '');
      const allowed = block && (fields[block.block_type] || []).some(([key]) => key === field);
      if (!allowed) return;
      block.content = Object.assign({},block.content || {});
      block.content[field] = String(message.value ?? '');
      const form = $('#builderLegacyForm') || $('#builderBlockForm');
      if (form && form.elements[field]) form.elements[field].value = block.content[field];
      renderCanvas();
      scheduleSave(block,{
        label:form && form.elements.label ? form.elements.label.value : block.label,
        content:block.content,
        settings:block.settings || {},
        is_visible:!!block.is_visible
      });
    }
  });

  document.querySelectorAll('[data-view="builder"]').forEach(button => button.addEventListener('click',() => {
    if (!loaded) load();
  }));
  setDevice('desktop');
  setLibraryTab('elements');
  setInspectorTab('content');
  load();
  pingPreview();
  window.setTimeout(() => {
    if (!previewReady) status('اتصال پیش‌نمایش برقرار نشد؛ index.php و نشست ورود مدیر را بررسی کن.','error');
  },5000);
})();