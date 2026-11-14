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
    text:'متن ساده',cta:'دعوت به اقدام',stats:'آمار و اعداد',spacer:'فاصله عمودی'
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
    spacer:[['height','ارتفاع (px)']]
  };

  let blocks = [];
  let design = {};
  let selectedId = null;
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
    },window.location.origin);
    if (selectedId !== null) {
      const selected = blocks.find(item => Number(item.id) === Number(selectedId));
      if (selected) frame.contentWindow.postMessage({
        type:'milad-builder-select-outline',key:blockKey(selected)
      },window.location.origin);
    }
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
        escapeHtml(titleOf(block)) + '</b><br>' + escapeHtml(summarize(block)).slice(0,100) + '</div></div>' +
        '<div class="builder-block-actions"><button type="button" data-up="' + Number(block.id) + '" title="بالا">↑</button>' +
        '<button type="button" data-down="' + Number(block.id) + '" title="پایین">↓</button>' +
        (cloneable ? '<button type="button" data-clone="' + Number(block.id) + '" title="تکثیر">＋</button>' : '') +
        '<button type="button" data-delete="' + Number(block.id) + '" title="حذف">×</button></div></article>';
    }).join('');

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
        const moved = blocks.splice(from,1)[0];
        blocks.splice(to,0,moved);
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
  }

  function move(id,delta) {
    const from = blocks.findIndex(item => Number(item.id) === id);
    const to = from + delta;
    if (from < 0 || to < 0 || to >= blocks.length) return;
    const moved = blocks.splice(from,1)[0];
    blocks.splice(to,0,moved);
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
    activeInspectorTab = 'content';
    renderCanvas();
    renderInspector();
    postPreviewState();
  }

  function fieldControl(key,label,value) {
    if (key === 'show_principles') {
      return '<label class="builder-check"><input type="checkbox" name="' + key + '" ' +
        (value === false || value === 0 || value === '0' ? '' : 'checked') + '> ' + escapeHtml(label) + '</label>';
    }
    if (key === 'height') {
      return '<label>' + escapeHtml(label) + '<input type="number" name="' + key + '" min="8" max="240" value="' + escapeHtml(value ?? 48) + '"></label>';
    }
    if (key === 'items' || key === 'principles_items') {
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
      } else if (key === 'items' || key === 'principles_items') {
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
    if (validationFailed) return null;
    return {
      label:form.elements.label.value,
      content,
      settings:{
        background:form.elements.background.value,
        text_color:form.elements.text_color.value,
        padding:Number(form.elements.padding.value),
        radius:Number(form.elements.radius.value)
      },
      is_visible:form.elements.visible.checked
    };
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
    const specs = fields[block.block_type] || [];
    const controls = specs.map(([key,label]) => fieldControl(key,label,(block.content || {})[key])).join('');
    const settings = block.settings || {};
    host.innerHTML = '<form id="builderBlockForm" class="builder-form">' +
      '<section data-inspector-panel="content"><div class="builder-panel-heading"><div><strong>محتوای بلوک</strong><small>متن‌ها و داده‌های این بخش</small></div></div>' +
      controls + '</section>' +
      '<section data-inspector-panel="style" hidden><div class="builder-panel-heading"><div><strong>استایل بلوک</strong><small>ظاهر مستقل این بخش</small></div></div>' +
      '<label class="color-field">رنگ پس‌زمینه<input type="color" name="background" value="' + escapeHtml(settings.background || '#080b12') + '"></label>' +
      '<label class="color-field">رنگ متن<input type="color" name="text_color" value="' + escapeHtml(settings.text_color || '#f3f5fb') + '"></label>' +
      '<label>فاصله عمودی<input type="range" name="padding" min="0" max="180" value="' + escapeHtml(settings.padding ?? 0) + '"></label>' +
      '<label>گردی گوشه‌ها<input type="range" name="radius" min="0" max="48" value="' + escapeHtml(settings.radius ?? 0) + '"></label></section>' +
      '<section data-inspector-panel="advanced" hidden><div class="builder-panel-heading"><div><strong>تنظیمات پیشرفته</strong><small>شناسه و وضعیت نمایش بلوک</small></div></div>' +
      '<label>نام در Navigator<input name="label" maxlength="140" value="' + escapeHtml(block.label || names[block.block_type]) + '"></label>' +
      '<label class="builder-check"><input type="checkbox" name="visible" ' + (block.is_visible ? 'checked' : '') + '> نمایش این بخش در سایت</label>' +
      '<div class="builder-note">کلید داخلی: <code>' + escapeHtml(block.block_key || '') + '</code><br>برای ویرایش مستقیم متن، روی متن در پیش‌نمایش دوبار کلیک کن.</div>' +
      '<div class="builder-form-actions"><button class="primary-button compact" type="button" id="builderSaveNow">ذخیره همین حالا</button></div></section>' +
      '</form>';

    const form = $('#builderBlockForm');
    const syncFromForm = () => {
      const payload = currentBlockPayload(block,form);
      if (!payload) {
        status('ساختار JSON معتبر نیست؛ تغییرات این فیلد پیش‌نمایش داده نشد.','error');
        return;
      }
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
      block.label = payload.label;
      block.content = payload.content;
      block.settings = payload.settings;
      block.is_visible = payload.is_visible;
      postPreviewState();
      flushSave(Number(block.id),true);
    });
    setInspectorTab(activeInspectorTab);
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
    if (!state || !state.payload) return;
    if (state.timer) {
      window.clearTimeout(state.timer);
      state.timer = null;
    }
    if (state.saving) {
      state.again = true;
      return;
    }
    state.saving = true;
    state.again = false;
    const payload = state.payload;
    try {
      await request('builder-block-save','PUT',payload,id);
      status('تغییرات ذخیره شدند.','success');
      return true;
    } catch (error) {
      status(error.message,'error');
      return false;
    } finally {
      state.saving = false;
      if (state.again) flushSave(id,true);
    }
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
      const data = await request('builder');
      blocks = data.blocks || [];
      design = data.design || {};
      if (selectId !== null) selectedId = Number(selectId);
      if (!blocks.some(item => Number(item.id) === Number(selectedId))) {
        selectedId = blocks.length ? Number(blocks[0].id) : null;
      }
      renderCanvas();
      renderInspector();
      renderDesign();
      loaded = true;
      postPreviewState();
      status('صفحه و پیش‌نمایش آماده‌اند.','success');
    } catch (error) { status(error.message,'error'); }
  }

  $$('[data-add-block]').forEach(button => button.addEventListener('click',() => addBlock(button.dataset.addBlock)));
  $$('[data-library-tab]').forEach(button => button.addEventListener('click',() => setLibraryTab(button.dataset.libraryTab)));
  $$('[data-inspector-tab]').forEach(button => button.addEventListener('click',() => setInspectorTab(button.dataset.inspectorTab)));
  $('#builderSaveAll')?.addEventListener('click',saveAll);
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
    frame.contentWindow.postMessage({type:'milad-builder-ping'},window.location.origin);
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
    if (event.origin !== window.location.origin || !frame || event.source !== frame.contentWindow) return;
    const message = event.data || {};
    if (message.type === 'milad-builder-ready') {
      previewReady = true;
      postPreviewState();
    } else if (message.type === 'milad-builder-select') {
      const block = blocks.find(item => blockKey(item) === String(message.key));
      if (block) selectBlock(Number(block.id));
    } else if (message.type === 'milad-builder-inline-edit') {
      const block = blocks.find(item => blockKey(item) === String(message.key));
      const field = String(message.field || '');
      const allowed = block && (fields[block.block_type] || []).some(([key]) => key === field);
      if (!allowed) return;
      block.content = Object.assign({},block.content || {});
      block.content[field] = String(message.value ?? '');
      const form = $('#builderBlockForm');
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