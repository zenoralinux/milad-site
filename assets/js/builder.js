(() => {
  'use strict';
  const root = document.getElementById('view-builder');
  if (!root) return;

  const body = document.body;
  const basePath = body.dataset.basePath || '';
  const api = basePath + '/api/admin.php';
  const csrf = body.dataset.csrf || '';
  const $ = (selector, scope = root) => scope.querySelector(selector);
  const $$ = (selector, scope = root) => Array.from(scope.querySelectorAll(selector));
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[ch]));

  const names = {
    hero:'معرفی اصلی',intro:'نوار معرفی',projects:'پروژه‌ها',
    about:'درباره من',now:'اکنون',contact:'ارتباط',
    text:'متن ساده',cta:'دعوت به اقدام',stats:'آمار و اعداد',spacer:'فاصله'
  };
  const typeIcons = {hero:'✦',intro:'≋',projects:'▦',about:'◎',now:'◷',contact:'↗',text:'¶',cta:'↗',stats:'▥',spacer:'↕'};
  const fields = {
    hero:[['title_line1','خط اول عنوان'],['title_line2','خط دوم عنوان'],['description','توضیحات']],
    intro:[['text_before','متن اصلی'],['text_emphasis','متن تأکیدی']],
    projects:[['heading_before','بخش اول عنوان'],['heading_emphasis','بخش دوم عنوان'],['description','توضیحات']],
    about:[['title_line1','خط اول عنوان'],['title_line2','خط دوم عنوان'],['description','توضیحات'],['link_text','متن لینک'],['link_url','آدرس لینک']],
    now:[['title_line1','خط اول عنوان'],['title_line2','خط دوم عنوان'],['description','توضیحات'],['status_title','وضعیت'],['status_subtitle','زیرعنوان وضعیت']],
    contact:[['title_line1','خط اول عنوان'],['title_line2','خط دوم عنوان'],['description','توضیحات']],
    text:[['title','عنوان'],['body','متن']],
    cta:[['title','عنوان'],['body','توضیحات'],['button_text','متن دکمه'],['button_url','آدرس دکمه']],
    stats:[['title','عنوان بخش'],['items','آیتم‌ها (JSON)']],
    spacer:[['height','ارتفاع (px)']]
  };

  let blocks = [];
  let design = {};
  let selectedId = null;
  let dragId = null;
  let loaded = false;

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

  function summarize(block) {
    const content = block.content || {};
    return content.description || content.body || content.title || content.title_line1 ||
      content.heading_before || content.text_before || 'برای ویرایش انتخاب کن';
  }

  function titleOf(block) {
    const content = block.content || {};
    return content.title || content.title_line1 || content.heading_before || content.text_before ||
      names[block.block_type] || block.label;
  }

  function renderCanvas() {
    const canvas = $('#builderCanvas');
    if (!blocks.length) {
      canvas.innerHTML = '<div class="builder-empty">صفحه هنوز بخشی ندارد.<br>از کتابخانه بلوک‌ها، اولین بخش را اضافه کن.</div>';
      return;
    }
    canvas.innerHTML = blocks.map((block, index) => {
      const selected = Number(block.id) === Number(selectedId);
      const hidden = !Number(block.is_visible);
      return '<article class="builder-block' + (selected ? ' is-selected' : '') + (hidden ? ' is-hidden' : '') +
        '" draggable="true" data-block-id="' + Number(block.id) + '" aria-grabbed="false">' +
        '<span class="builder-drag-handle" title="برای جابه‌جایی بکش">⠿</span>' +
        '<div class="builder-block-copy"><strong>' + escapeHtml(block.label || names[block.block_type] || block.block_type) +
        '</strong><small>' + escapeHtml(names[block.block_type] || block.block_type) + ' · ' +
        (hidden ? 'مخفی' : 'نمایش در سایت') + '</small><div class="builder-block-preview"><b>' +
        escapeHtml(titleOf(block)) + '</b><br>' + escapeHtml(summarize(block)).slice(0,180) + '</div></div>' +
        '<div class="builder-block-actions"><button type="button" data-up="' + Number(block.id) + '" title="بالا">↑</button>' +
        '<button type="button" data-down="' + Number(block.id) + '" title="پایین">↓</button>' +
        (['hero','intro','projects','about','now','contact'].includes(block.block_type) ? '' : '<button type="button" data-clone="' + Number(block.id) + '" title="تکثیر">＋</button>') +
        '<button type="button" data-delete="' + Number(block.id) + '" title="حذف">×</button></div></article>';
    }).join('');
    $$('.builder-block').forEach(node => {
      node.addEventListener('click', event => {
        if (event.target.closest('button')) return;
        selectBlock(Number(node.dataset.blockId));
      });
      node.addEventListener('dragstart', event => {
        dragId = Number(node.dataset.blockId);
        node.setAttribute('aria-grabbed','true');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain',String(dragId));
      });
      node.addEventListener('dragover', event => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        node.classList.add('is-drop-target');
      });
      node.addEventListener('dragleave', () => node.classList.remove('is-drop-target'));
      node.addEventListener('drop', async event => {
        event.preventDefault();
        node.classList.remove('is-drop-target');
        const targetId = Number(node.dataset.blockId);
        if (dragId && targetId && dragId !== targetId) {
          const from = blocks.findIndex(item => Number(item.id) === dragId);
          const to = blocks.findIndex(item => Number(item.id) === targetId);
          if (from >= 0 && to >= 0) {
            const moved = blocks.splice(from,1)[0];
            blocks.splice(to,0,moved);
            await saveOrder();
          }
        }
      });
      node.addEventListener('dragend', () => {
        dragId = null;
        node.setAttribute('aria-grabbed','false');
        $$('.builder-block').forEach(item => item.classList.remove('is-drop-target'));
      });
    });
    $$('[data-up]').forEach(button => button.addEventListener('click', () => move(Number(button.dataset.up),-1)));
    $$('[data-down]').forEach(button => button.addEventListener('click', () => move(Number(button.dataset.down),1)));
    $$('[data-clone]').forEach(button => button.addEventListener('click', () => cloneBlock(Number(button.dataset.clone))));
    $$('[data-delete]').forEach(button => button.addEventListener('click', () => deleteBlock(Number(button.dataset.delete))));
  }

  function move(id, delta) {
    const from = blocks.findIndex(item => Number(item.id) === id);
    const to = from + delta;
    if (from < 0 || to < 0 || to >= blocks.length) return;
    const moved = blocks.splice(from,1)[0];
    blocks.splice(to,0,moved);
    saveOrder();
  }

  async function saveOrder() {
    renderCanvas();
    try {
      await request('builder-block-reorder','POST',{ids:blocks.map(item => Number(item.id))});
      status('ترتیب بلوک‌ها ذخیره شد.','success');
    } catch (error) {
      status(error.message,'error');
      await load();
    }
  }

  function selectBlock(id) {
    selectedId = id;
    renderCanvas();
    renderInspector();
  }

  function fieldControl(key, label, value) {
    const isLong = ['description','body','items'].includes(key) || String(value ?? '').length > 90;
    if (key === 'height') return '<label>' + escapeHtml(label) + '<input type="number" name="' + key + '" min="8" max="240" value="' + escapeHtml(value ?? 48) + '"></label>';
    if (key === 'items') return '<label>' + escapeHtml(label) + '<textarea name="' + key + '" dir="ltr" spellcheck="false">' + escapeHtml(JSON.stringify(value || [],null,2)) + '</textarea></label>';
    return '<label>' + escapeHtml(label) + (isLong
      ? '<textarea name="' + key + '">' + escapeHtml(value || '') + '</textarea>'
      : '<input name="' + key + '" value="' + escapeHtml(value || '') + '">') + '</label>';
  }

  function renderInspector() {
    const block = blocks.find(item => Number(item.id) === Number(selectedId));
    const host = $('#builderInspectorForm');
    if (!block) {
      host.innerHTML = '<div class="builder-empty">برای ویرایش، یک بلوک را در بوم انتخاب کن.</div>';
      return;
    }
    const contentFields = fields[block.block_type] || fields.text;
    const controls = contentFields.map(([key,label]) => fieldControl(key,label,(block.content || {})[key])).join('');
    const settings = block.settings || {};
    host.innerHTML = '<form id="builderBlockForm" class="builder-form">' +
      '<label>نام در ساختار<input name="label" maxlength="140" value="' + escapeHtml(block.label || names[block.block_type]) + '"></label>' +
      controls +
      '<label class="builder-check"><input type="checkbox" name="visible" ' + (block.is_visible ? 'checked' : '') + '> نمایش این بلوک در سایت</label>' +
      '<div class="builder-separator"></div><strong>ظاهر این بلوک</strong>' +
      '<label class="color-field">رنگ پس‌زمینه<input type="color" name="background" value="' + escapeHtml(settings.background || '#080b12') + '"></label>' +
      '<label class="color-field">رنگ متن<input type="color" name="text_color" value="' + escapeHtml(settings.text_color || '#f3f5fb') + '"></label>' +
      '<label>فاصله عمودی<input type="range" name="padding" min="0" max="180" value="' + escapeHtml(settings.padding ?? 0) + '"></label>' +
      '<label>گردی گوشه‌ها<input type="range" name="radius" min="0" max="48" value="' + escapeHtml(settings.radius ?? 0) + '"></label>' +
      '<div class="builder-form-actions"><button class="primary-button compact" type="submit">ذخیره بلوک</button></div></form>';

    $('#builderBlockForm').addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const content = Object.assign({},block.content || {});
      let validationFailed = false;
      contentFields.forEach(([key]) => {
        const field = form.elements[key];
        if (!field) return;
        if (key === 'items') {
          try { content[key] = JSON.parse(field.value || '[]'); }
          catch { validationFailed = true; }
        } else content[key] = field.value;
      });
      if (validationFailed) { status('ساختار JSON آیتم‌ها معتبر نیست.','error'); return; }
      const payload = {
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
      try {
        const saved = await request('builder-block-save','PUT',payload,block.id);
        Object.assign(block,saved);
        status('تغییرات بلوک ذخیره شد.','success');
        renderCanvas();
        renderInspector();
      } catch (error) { status(error.message,'error'); }
    });
  }

  function statusText() { return $('#builderStatus')?.textContent || ''; }

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
    if (!block || !window.confirm('بلوک «' + (block.label || names[block.block_type]) + '» حذف شود؟')) return;
    try {
      await request('builder-block-delete','DELETE',{},id);
      if (selectedId === id) selectedId = null;
      await load();
      status('بلوک حذف شد.','success');
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

  async function saveDesign(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const payload = {};
    ['accent_color','page_background','content_width','corner_radius','font_scale','section_spacing'].forEach(key => {
      payload[key] = form.elements[key].value;
    });
    try {
      design = await request('builder-design','PUT',payload);
      renderDesign();
      status('تنظیمات ظاهری ذخیره شد.','success');
    } catch (error) { status(error.message,'error'); }
  }

  async function load(selectId = null) {
    status('در حال دریافت ساختار صفحه…');
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
      status('همه تغییرات ذخیره‌شده بارگذاری شدند.','success');
    } catch (error) { status(error.message,'error'); }
  }

  $$('[data-add-block]').forEach(button => button.addEventListener('click',() => addBlock(button.dataset.addBlock)));
  $('#builderDesignForm')?.addEventListener('submit',saveDesign);
  $('#builderReload')?.addEventListener('click',() => load());
  $$('.builder-device-tabs button').forEach(button => button.addEventListener('click',() => {
    $$('.builder-device-tabs button').forEach(item => item.classList.toggle('is-active',item === button));
    $('#builderCanvas').dataset.device = button.dataset.device;
  }));

  document.querySelectorAll('[data-view="builder"]').forEach(button => button.addEventListener('click',() => {
    if (!loaded) load();
  }));
  load();
})();