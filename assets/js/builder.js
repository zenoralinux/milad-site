(() => {
  'use strict';
  const root = document.querySelector('.studio-editor') || document.getElementById('view-builder');
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
    counter:'شمارنده',list:'فهرست',video:'ویدیو',principle_item:'کارت اصل کاری',stat_item:'کارت آماری',feature:'ویژگی',pricing:'جدول قیمت‌گذاری',accordion:'سؤالات متداول',testimonial:'نظر مشتری',gallery:'گالری تصاویر',timeline:'خط زمانی',skill:'مهارت',logo_cloud:'لوگوها',team:'اعضای تیم',tabs:'تب‌های محتوا',newsletter:'خبرنامه',icon_box:'کارت آیکون‌دار'
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
    feature:[['symbol','آیکون'],['title','عنوان'],['description','توضیحات']],
    pricing:[['title','نام پلن'],['price','قیمت'],['period','دوره'],['description','توضیحات'],['items','امکانات پلن'],['button_text','متن دکمه'],['button_url','لینک دکمه']],
    accordion:[['title','عنوان بخش'],['items','پرسش‌ها و پاسخ‌ها']],
    testimonial:[['quote','متن نظر'],['author','نام مشتری'],['role','سمت یا توضیح']],
    gallery:[['title','عنوان گالری'],['items','تصاویر گالری']],
    timeline:[['title','عنوان خط زمانی'],['items','مراحل و رویدادها']],
    skill:[['title','عنوان مهارت'],['value','درصد پیشرفت']],
    logo_cloud:[['title','عنوان بخش'],['items','لوگوها']],
    team:[['title','عنوان تیم'],['items','اعضای تیم']],
    tabs:[['title','عنوان بخش'],['items','تب‌ها']],
    newsletter:[['title','عنوان'],['description','توضیحات'],['placeholder','متن فیلد ایمیل'],['button_text','متن دکمه']],
    icon_box:[['symbol','آیکون'],['title','عنوان'],['description','توضیحات']],
    principle_item:[['number','شماره / نشان'],['title','عنوان'],['description','توضیحات']],
    stat_item:[['value','مقدار / نشان'],['label','عنوان'],['description','توضیحات']]
  };

  let projects = [];
  let selectedProjectId = null;
  let projectDragId = null;
  const projectSaveStates = new Map();
  let blocks = [];
  let design = {};
  let selectedId = null;
  let selectedNodeId = null;
  let selectedLegacyField = null;
  let dragId = null;
  let dragLibraryType = null;
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
      ['font_size','اندازه فونت','24px'],['line_height','ارتفاع خط','1.5'],['width','عرض','100%'],
      ['height','ارتفاع','280px'],['min_width','حداقل عرض','160px'],['max_width','حداکثر عرض','640px'],
      ['min_height','حداقل ارتفاع','120px'],['max_height','حداکثر ارتفاع','720px'],
      ['padding','فاصله داخلی','16px'],['margin','حاشیه بیرونی','0 0 16px'],['gap','فاصله عناصر','12px'],
      ['flex_direction','جهت Flex','row'],['grid_columns','ستون‌های Grid','3'],
      ['align_items','تراز عمودی','stretch'],['justify_content','توزیع محتوا','space-between'],['visibility','نمایش','visible']
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
    const keys = ['font_size','line_height','width','height','min_width','max_width','min_height','max_height','padding','margin','gap','flex_direction','grid_columns','align_items','justify_content','visibility','text_align','display'];
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
  function spacingParts(value) {
    const values=String(value||'0').trim().split(/\s+/).filter(Boolean).slice(0,4);
    if(!values.length)return ['0','0','0','0'];
    if(values.length===1)return [values[0],values[0],values[0],values[0]];
    if(values.length===2)return [values[0],values[1],values[0],values[1]];
    if(values.length===3)return [values[0],values[1],values[2],values[1]];
    return values;
  }
  function renderBoxModelControl(key,label,value) {
    const sides=spacingParts(value);
    const names=['top','right','bottom','left'];
    return '<fieldset class="builder-box-model" data-spacing-group="' + key + '"><legend>' + label + '</legend>' +
      '<div class="builder-box-model-grid">' + names.map((side,index) =>
        '<label>' + ({top:'بالا',right:'راست',bottom:'پایین',left:'چپ'}[side]) +
        '<input type="text" inputmode="decimal" name="style_' + key + '_' + side + '" data-spacing-side="' + side + '" value="' + escapeHtml(sides[index]) + '" placeholder="0"></label>'
      ).join('') + '</div><label class="builder-check builder-spacing-link"><input type="checkbox" data-spacing-link="' + key + '"> پیوند چهار ضلع</label></fieldset>';
  }


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
      '<label>ارتفاع<input name="style_height" value="' + escapeHtml(settings.height ?? '') + '" placeholder="auto / 280px"></label>' +
      '<label>حداقل عرض<input name="style_min_width" value="' + escapeHtml(settings.min_width ?? '') + '" placeholder="160px"></label>' +
      '<label>حداقل ارتفاع<input name="style_min_height" value="' + escapeHtml(settings.min_height ?? '') + '" placeholder="120px"></label>' +
      '<label>حداکثر ارتفاع<input name="style_max_height" value="' + escapeHtml(settings.max_height ?? '') + '" placeholder="720px"></label>' +
      '<label>فاصله بین عناصر<input name="style_gap" value="' + escapeHtml(settings.gap ?? '') + '" placeholder="16px"></label>' +
      renderBoxModelControl('padding','فاصله داخلی',settings.padding) +
      renderBoxModelControl('margin','حاشیه بیرونی',settings.margin) +
      '<label>جهت Flexbox<select name="style_flex_direction"><option value="">پیش‌فرض</option>' + ['row','row-reverse','column','column-reverse'].map(v => '<option value="' + v + '"' + (String(settings.flex_direction || '') === v ? ' selected' : '') + '>' + v + '</option>').join('') + '</select></label>' +
      '<label>تعداد ستون‌های Grid<input name="style_grid_columns" type="number" min="1" max="6" value="' + escapeHtml(settings.grid_columns ?? '') + '"></label>' +
      '<label>تراز عمودی<select name="style_align_items"><option value="">پیش‌فرض</option>' + ['stretch','center','flex-start','flex-end','baseline'].map(v => '<option value="' + v + '"' + (String(settings.align_items || '') === v ? ' selected' : '') + '>' + v + '</option>').join('') + '</select></label>' +
      '<label>توزیع محتوا<select name="style_justify_content"><option value="">پیش‌فرض</option>' + ['flex-start','center','flex-end','space-between','space-around','space-evenly'].map(v => '<option value="' + v + '"' + (String(settings.justify_content || '') === v ? ' selected' : '') + '>' + v + '</option>').join('') + '</select></label>' +
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
    ['font_size','line_height','width','height','min_width','max_width','min_height','max_height','gap','radius','flex_direction','grid_columns','align_items','justify_content','visibility'].forEach(key => {
    ['padding','margin'].forEach(key => {
      const values=['top','right','bottom','left'].map(side => String(form.elements['style_'+key+'_'+side]?.value || '').trim());
      if (values.some(Boolean)) next[key]=values.map(value=>value||'0').join(' ');
      else delete next[key];
    });

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
    form.addEventListener('input',event => {
      const field=event.target.closest('[data-spacing-side]');
      if(!field)return;
      const group=field.closest('[data-spacing-group]');
      const linked=group&&group.querySelector('[data-spacing-link]');
      if(linked&&linked.checked)group.querySelectorAll('[data-spacing-side]').forEach(other=>{if(other!==field)other.value=field.value;});
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
    frame.contentWindow.postMessage({type:'milad-builder-project-state',projects:projects},'*');
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
    if (!canvas) return;
    const projectRows = projects.map(project => {
      const id = Number(project.id);
      const active = Number(selectedProjectId) === id;
      const hidden = !project.is_published;
      return '<div class="builder-project-layer' + (active ? ' is-selected' : '') + (hidden ? ' is-hidden' : '') + '" draggable="true" data-project-layer="' + id + '">' +
        '<button type="button" class="builder-layer-node" data-project-select="' + id + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
        '<span class="builder-layer-icon" aria-hidden="true">▣</span><span class="builder-project-layer-copy"><strong>' + escapeHtml(project.title || 'پروژه بدون عنوان') +
        '</strong><small>' + escapeHtml(project.category || 'tools') + ' · ' + escapeHtml(project.status || 'project') + '</small></span>' +
        '<span class="builder-layer-state" title="' + (hidden ? 'پیش‌نویس' : 'منتشر شده') + '">' + (hidden ? '◌' : '●') + '</span></button>' +
        '<div class="builder-project-layer-actions"><button type="button" data-project-clone="' + id + '" title="تکثیر">＋</button><button type="button" data-project-delete="' + id + '" title="حذف">×</button></div></div>';
    }).join('');
    const projectTree = projects.length
      ? '<section class="builder-project-tree"><div class="builder-project-tree-heading"><strong>01 — SELECTED WORK</strong><small>' + projects.length + ' کارت مستقل · کشیدن برای جابه‌جایی</small></div>' + projectRows + '</section>'
      : '';
    const blockMarkup = blocks.length ? blocks.map(block => {
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
    }).join('') : '<div class="builder-empty">بخشی برای ویرایش وجود ندارد. از کتابخانه بلوک‌ها استفاده کن.</div>';
    canvas.innerHTML = projectTree + blockMarkup;

    $$('[data-project-select]').forEach(button => button.addEventListener('click',event => {
      event.stopPropagation();
      selectProject(Number(button.dataset.projectSelect));
    }));
    $$('[data-project-clone]').forEach(button => button.addEventListener('click',event => {
      event.stopPropagation();
      duplicateProject(Number(button.dataset.projectClone));
    }));
    $$('[data-project-delete]').forEach(button => button.addEventListener('click',event => {
      event.stopPropagation();
      deleteProject(Number(button.dataset.projectDelete));
    }));
    $$('.builder-project-layer').forEach(node => {
      node.addEventListener('dragstart',event => {
        projectDragId = Number(node.dataset.projectLayer);
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain','milad-studio-project:' + projectDragId);
      });
      node.addEventListener('dragover',event => {
        if (!projectDragId || projectDragId === Number(node.dataset.projectLayer)) return;
        event.preventDefault();
        node.classList.add('is-drop-target');
      });
      node.addEventListener('dragleave',() => node.classList.remove('is-drop-target'));
      node.addEventListener('drop',async event => {
        event.preventDefault();
        node.classList.remove('is-drop-target');
        const targetId = Number(node.dataset.projectLayer);
        if (!projectDragId || !targetId || projectDragId === targetId) return;
        const from = projects.findIndex(item => Number(item.id) === projectDragId);
        if (from < 0) return;
        queueUndoProjectOrder();
        const targetAfterRemoval = projects.findIndex(item => Number(item.id) === targetId);
        if (targetAfterRemoval < 0) return;
        const moved = projects.splice(from,1)[0];
        const destination = projects.findIndex(item => Number(item.id) === targetId);
        projects.splice(destination,0,moved);
        await saveProjectOrder();
      });
      node.addEventListener('dragend',() => {
        projectDragId = null;
        $$('.builder-project-layer').forEach(item => item.classList.remove('is-drop-target'));
      });
    });

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
        if (from < 0) return;
        const moved = blocks.splice(from,1)[0];
        const targetAfterRemoval = blocks.findIndex(item => Number(item.id) === targetId);
        if (targetAfterRemoval < 0) return;
        blocks.splice(targetAfterRemoval,0,moved);
        queueUndoOrder();
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
    selectedProjectId = null;
    selectedId = id;
    selectedNodeId = null;
    selectedLegacyField = null;
    activeInspectorTab = 'content';
    renderCanvas();
    renderInspector();
    postPreviewState();
  }

  function selectNestedNode(blockId,nodeId) {
    selectedProjectId = null;
    selectedId = Number(blockId);
    selectedNodeId = String(nodeId || '');
    selectedLegacyField = null;
    activeInspectorTab = 'content';
    renderCanvas();
    renderInspector();
    postPreviewState();
  }
  function selectLegacyElement(blockId,field) {
    selectedProjectId = null;
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
        '<div class="builder-nested-add"><select id="builderNodeChildType"><option value="heading">عنوان</option><option value="paragraph">متن</option><option value="button">دکمه</option><option value="card">کارت</option><option value="image">تصویر</option><option value="icon">آیکون</option><option value="divider">جداکننده</option><option value="counter">شمارنده</option><option value="list">فهرست</option><option value="video">ویدیو</option><option value="feature">ویژگی</option><option value="pricing">قیمت‌گذاری</option><option value="accordion">سؤالات متداول</option><option value="testimonial">نظر مشتری</option><option value="gallery">گالری تصاویر</option><option value="timeline">خط زمانی</option><option value="skill">مهارت</option><option value="logo_cloud">لوگوها</option><option value="team">اعضای تیم</option><option value="tabs">تب‌های محتوا</option><option value="newsletter">خبرنامه</option><option value="icon_box">کارت آیکون‌دار</option><option value="container">کانتینر</option><option value="grid">گرید</option></select><button type="button" class="secondary-button compact" data-node-child-add>＋ افزودن فرزند</button></div>' +
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
        const hasRepeater = key === 'items' && !!form.querySelector('[data-repeater="items"]');
        if (!field && !hasRepeater) return;
        if (hasRepeater) {
          nextContent[key] = readRepeater(form,key) || [];
        } else if (['items','principles_items'].includes(key)) {
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


  function selectProject(id) {
    const project = projects.find(item => Number(item.id) === Number(id));
    if (!project) return;
    selectedProjectId = Number(id);
    selectedId = null;
    selectedNodeId = null;
    selectedLegacyField = null;
    activeInspectorTab = 'content';
    renderCanvas();
    renderInspector();
    postPreviewState();
  }

  function projectFormPayload(project,form) {
    let links;
    try { links = JSON.parse(form.elements.links.value || '[]'); }
    catch (error) { return null; }
    if (!Array.isArray(links)) return null;
    const technologies = String(form.elements.technologies.value || '').split(/[\\n,]/).map(value => value.trim()).filter(Boolean);
    return {
      slug:String(form.elements.slug.value || '').trim(),
      title:String(form.elements.title.value || '').trim(),
      category:form.elements.category.value,
      kind_label:String(form.elements.kind_label.value || '').trim(),
      excerpt:String(form.elements.excerpt.value || '').trim(),
      description:String(form.elements.description.value || '').trim(),
      status:form.elements.status.value,
      visual_variant:form.elements.visual_variant.value,
      is_featured:form.elements.is_featured.checked,
      is_published:form.elements.is_published.checked,
      sort_order:Number(form.elements.sort_order.value || 0),
      technologies:technologies,
      links:links
    };
  }

  function renderProjectInspector(project,host) {
    const statusOptions = [['active','فعال'],['in_progress','در توسعه'],['paused','متوقف'],['project','پروژه'],['archived','بایگانی']];
    const categoryOptions = [['os','سیستم‌عامل'],['apps','اپلیکیشن'],['tools','ابزارها']];
    const variantOptions = [['zenora','Zenora'],['chat','Chat'],['android','Android'],['arm','ARM'],['wsl','WSL'],['generic','Generic']];
    const optionMarkup = (list,current) => list.map(([value,label]) => '<option value="' + value + '"' + (String(current) === value ? ' selected' : '') + '>' + label + '</option>').join('');
    host.innerHTML = '<form id="builderProjectForm" class="builder-form builder-project-form">' +
      '<section data-inspector-panel="content"><div class="builder-panel-heading"><div><strong>کارت پروژه</strong><small>ویرایش مستقل محتوای رکورد دیتابیس · #' + Number(project.id) + '</small></div></div>' +
      '<label>عنوان پروژه<input name="title" maxlength="180" value="' + escapeHtml(project.title || '') + '" required></label>' +
      '<label>Slug<input name="slug" maxlength="160" dir="ltr" value="' + escapeHtml(project.slug || '') + '" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></label>' +
      '<label>برچسب نوع پروژه<input name="kind_label" maxlength="100" value="' + escapeHtml(project.kind_label || '') + '"></label>' +
      '<label>توضیح کوتاه<textarea name="excerpt" maxlength="500">' + escapeHtml(project.excerpt || '') + '</textarea></label>' +
      '<label>توضیحات کامل<textarea name="description" maxlength="20000">' + escapeHtml(project.description || '') + '</textarea></label>' +
      '<label>فناوری‌ها <small>هر خط یا ویرگول یک مورد</small><textarea name="technologies">' + escapeHtml((project.technologies || []).join('\\n')) + '</textarea></label>' +
      '<label>لینک‌ها (JSON)<textarea name="links" dir="ltr" spellcheck="false">' + escapeHtml(JSON.stringify(project.links || [],null,2)) + '</textarea></label></section>' +
      '<section data-inspector-panel="style" hidden><div class="builder-panel-heading"><div><strong>ظاهر کارت</strong><small>دسته‌بندی، وضعیت و طرح تصویر</small></div></div>' +
      '<label>دسته‌بندی<select name="category">' + optionMarkup(categoryOptions,project.category) + '</select></label>' +
      '<label>وضعیت<select name="status">' + optionMarkup(statusOptions,project.status) + '</select></label>' +
      '<label>طرح گرافیکی<select name="visual_variant">' + optionMarkup(variantOptions,project.visual_variant) + '</select></label>' +
      '<label>ترتیب نمایش<input type="number" min="0" max="100000" name="sort_order" value="' + escapeHtml(project.sort_order || 0) + '"></label></section>' +
      '<section data-inspector-panel="advanced" hidden><div class="builder-panel-heading"><div><strong>وضعیت انتشار</strong><small>مدیریت نمایش در سایت عمومی</small></div></div>' +
      '<label class="builder-check"><input type="checkbox" name="is_featured" ' + (project.is_featured ? 'checked' : '') + '> کارت برجسته</label>' +
      '<label class="builder-check"><input type="checkbox" name="is_published" ' + (project.is_published ? 'checked' : '') + '> انتشار کارت در سایت</label>' +
      '<div class="builder-note">ویرایش کارت مستقیماً روی جدول projects و جداول وابسته لینک‌ها و فناوری‌ها اعمال می‌شود. ورودی‌های متنی به‌عنوان HTML اجرا نمی‌شوند.</div>' +
      '<div class="builder-form-actions"><button type="button" class="secondary-button compact" id="builderProjectDuplicate">تکثیر کارت</button><button type="button" class="secondary-button compact" id="builderProjectDelete">حذف کارت</button><button type="button" class="primary-button compact" id="builderProjectSaveNow">ذخیره همین حالا</button></div></section></form>';
    const form = $('#builderProjectForm');
    const sync = () => {
      const payload = projectFormPayload(project,form);
      if (!payload || !payload.title || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(payload.slug)) {
        status('عنوان، Slug یا ساختار JSON لینک‌ها معتبر نیست.','error');
        return;
      }
      queueUndoProject(project);
      Object.assign(project,payload);
      renderCanvas();
      postPreviewState();
      scheduleProjectSave(project,payload);
    };
    form.addEventListener('input',sync);
    form.addEventListener('change',sync);
    form.addEventListener('submit',event => event.preventDefault());
    $('#builderProjectSaveNow').addEventListener('click',() => flushProjectSave(Number(project.id)));
    $('#builderProjectDuplicate').addEventListener('click',() => duplicateProject(Number(project.id)));
    $('#builderProjectDelete').addEventListener('click',() => deleteProject(Number(project.id)));
    setInspectorTab(activeInspectorTab);
  }

  function scheduleProjectSave(project,payload) {
    const id = Number(project.id);
    let state = projectSaveStates.get(id);
    if (!state) {
      state = {timer:null,payload:null,inFlight:null};
      projectSaveStates.set(id,state);
    }
    state.payload = payload;
    if (state.timer) window.clearTimeout(state.timer);
    state.timer = window.setTimeout(() => flushProjectSave(id),700);
    status('کارت پروژه در پیش‌نمایش تغییر کرد؛ ذخیره خودکار در صف است.','');
  }
  async function flushProjectSave(id) {
    const state = projectSaveStates.get(Number(id));
    if (!state || !state.payload) return true;
    if (state.timer) { window.clearTimeout(state.timer); state.timer = null; }
    if (state.inFlight) {
      const prior = await state.inFlight;
      if (prior === false) return false;
      return state.payload ? flushProjectSave(id) : true;
    }
    const payload = state.payload;
    state.inFlight = (async () => {
      try {
        await request('project-save','PUT',payload,Number(id));
        if (state.payload === payload) state.payload = null;
        status('کارت پروژه ذخیره شد.','success');
        return true;
      } catch (error) {
        status(error.message,'error');
        return false;
      } finally {
        state.inFlight = null;
      }
    })();
    const result = await state.inFlight;
    if (result === false) return false;
    if (state.payload && state.payload !== payload) return flushProjectSave(id);
    return true;
  }
  async function flushAllProjectSaves() {
    const results = await Promise.all(Array.from(projectSaveStates.keys()).map(id => flushProjectSave(id)));
    return results.every(result => result !== false);
  }
  async function saveProjectOrder() {
    renderCanvas();
    postPreviewState();
    try {
      await request('project-reorder','POST',{ids:projects.map(item => Number(item.id))});
      status('ترتیب کارت‌های پروژه ذخیره شد.','success');
    } catch (error) {
      status(error.message,'error');
      await load();
    }
  }
  async function duplicateProject(id) {
    const source = projects.find(item => Number(item.id) === Number(id));
    if (!source || !(await flushProjectSave(id))) return;
    const copy = cloneData(source);
    copy.slug = String(source.slug || 'project') + '-copy-' + Date.now().toString(36);
    copy.title = String(source.title || 'پروژه') + ' (کپی)';
    copy.sort_order = projects.length + 1;
    try {
      const created = await request('projects','POST',copy);
      await load();
      selectProject(Number(created.id));
      status('کارت پروژه تکثیر شد.','success');
    } catch (error) { status(error.message,'error'); }
  }
  async function deleteProject(id) {
    const project = projects.find(item => Number(item.id) === Number(id));
    if (!project || !window.confirm('کارت «' + (project.title || 'پروژه') + '» حذف شود؟')) return;
    if (!(await flushProjectSave(id))) return;
    try {
      await request('project-delete','DELETE',{},Number(id));
      projectSaveStates.delete(Number(id));
      selectedProjectId = null;
      await load();
      status('کارت پروژه حذف شد.','success');
    } catch (error) { status(error.message,'error'); }
  }

  function renderRepeaterControl(key,label,value) {
    const items = Array.isArray(value) ? value : [];
    const keys = items.length ? Array.from(new Set(items.flatMap(item => item && typeof item === 'object' ? Object.keys(item) : ['text']))) : ['title','description'];
    const labels = {title:'عنوان',description:'توضیحات',text:'متن',question:'پرسش',answer:'پاسخ',name:'نام',role:'سمت',date:'تاریخ',value:'مقدار',label:'برچسب',src:'آدرس تصویر',alt:'متن جایگزین',url:'لینک',price:'قیمت'};
    const controls = items.map((item,index) => {
      const record = item && typeof item === 'object' ? item : {text:String(item ?? '')};
      return '<div class="builder-repeater-item" data-repeater-item><div class="builder-repeater-item-head"><strong>آیتم ' + (index+1) + '</strong><div><button type="button" data-repeater-up title="بالا">↑</button><button type="button" data-repeater-down title="پایین">↓</button><button type="button" data-repeater-remove title="حذف">×</button></div></div>' +
      keys.map(field => '<label>' + escapeHtml(labels[field] || field) + (['description','answer','quote','body'].includes(field) ? '<textarea data-repeater-field="' + escapeHtml(field) + '">' + escapeHtml(record[field] ?? '') + '</textarea>' : '<input data-repeater-field="' + escapeHtml(field) + '" value="' + escapeHtml(record[field] ?? '') + '"' + (['src','url'].includes(field) ? ' dir="ltr"' : '') + '>') + '</label>').join('') + '</div>';
    }).join('');
    return '<div class="builder-repeater" data-repeater="' + escapeHtml(key) + '" data-repeater-fields="' + escapeHtml(keys.join(',')) + '"><div class="builder-repeater-title"><strong>' + escapeHtml(label) + '</strong><button type="button" class="secondary-button compact" data-repeater-add>＋ افزودن آیتم</button></div>' + (controls || '<p class="builder-empty">هنوز آیتمی اضافه نشده است.</p>') + '</div>';
  }
  function readRepeater(form,key) {
    const host = form.querySelector('[data-repeater="' + key + '"]');
    if (!host) return null;
    return Array.from(host.querySelectorAll('[data-repeater-item]')).map(item => {
      const record = {};
      item.querySelectorAll('[data-repeater-field]').forEach(field => record[field.dataset.repeaterField] = field.value);
      return record;
    });
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
    if (key === 'items' && Array.isArray(value)) return renderRepeaterControl(key,label,value);
    if (key === 'items' || key === 'principles_items' || key === 'children') {
      return '<label>' + escapeHtml(label) + '<textarea name="' + key + '" dir="ltr" spellcheck="false">' +
        escapeHtml(JSON.stringify(value || [],null,2)) + '</textarea><small>ساختار پیچیده را به صورت JSON ویرایش کن.</small></label>';
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
      const hasRepeater = key === 'items' && !!form.querySelector('[data-repeater="items"]');
      if (!field && !hasRepeater) return;
      if (key === 'show_principles') {
        content[key] = field.checked;
      } else if (key === 'items' && form.querySelector('[data-repeater="items"]')) {
        content[key] = readRepeater(form,key) || [];
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
    ['padding','radius','font_size','grid_columns'].forEach(key => {
      if (form.elements[key] && form.elements[key].value !== '') nextSettings[key] = Number(form.elements[key].value);
      else if (form.elements[key]) delete nextSettings[key];
    });
    ['line_height','width','height','min_width','max_width','min_height','max_height','gap','flex_direction','align_items','justify_content','visibility'].forEach(key => {
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
    document.querySelectorAll('[data-library-panel]').forEach(panel => {
      panel.hidden = false;
    });
  }

    if (selectedProjectId !== null) {
      const project = projects.find(item => Number(item.id) === Number(selectedProjectId));
      if (project) { renderProjectInspector(project,host); return; }
      selectedProjectId = null;
    }
  function renderInspector() {
    const host = $('#builderInspectorForm');
    if (selectedProjectId !== null) {
      const project = projects.find(item => Number(item.id) === Number(selectedProjectId));
      if (project) { renderProjectInspector(project,host); return; }
      selectedProjectId = null;
    }
    const block = blocks.find(item => Number(item.id) === Number(selectedId));
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
  let pendingProjectUndo = null;
  let pendingProjectUndoTimer = null;

  function cloneData(value) {
    return JSON.parse(JSON.stringify(value));
  }
  function queueUndoProject(project) {
    if (pendingUndoTimer) window.clearTimeout(pendingUndoTimer);
    if (pendingUndo) { undoStack.push(pendingUndo); pendingUndo = null; pendingUndoTimer = null; }
    if (!pendingProjectUndo || Number(pendingProjectUndo.id) !== Number(project.id)) {
      if (pendingProjectUndo) undoStack.push(pendingProjectUndo);
      pendingProjectUndo = {kind:'project',id:Number(project.id),payload:projectFormPayloadForProject(project)};
    }
    if (pendingProjectUndoTimer) window.clearTimeout(pendingProjectUndoTimer);
    pendingProjectUndoTimer = window.setTimeout(() => {
      if (pendingProjectUndo) undoStack.push(pendingProjectUndo);
      pendingProjectUndo = null; pendingProjectUndoTimer = null; updateHistoryButtons();
    },700);
    redoStack.length = 0; updateHistoryButtons();
  }
  function queueUndoProjectOrder() {
    if (pendingProjectUndoTimer) window.clearTimeout(pendingProjectUndoTimer);
    if (pendingProjectUndo) { undoStack.push(pendingProjectUndo); pendingProjectUndo = null; pendingProjectUndoTimer = null; }
    if (pendingUndoTimer) window.clearTimeout(pendingUndoTimer);
    if (pendingUndo) { undoStack.push(pendingUndo); pendingUndo = null; pendingUndoTimer = null; }
    undoStack.push({kind:'project-order',order:projects.map(item => Number(item.id))});
    redoStack.length = 0; updateHistoryButtons();
  }
  function queueUndo(block) {
    if (pendingProjectUndoTimer) window.clearTimeout(pendingProjectUndoTimer);
    if (pendingProjectUndo) { undoStack.push(pendingProjectUndo); pendingProjectUndo = null; pendingProjectUndoTimer = null; }
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
    if (pendingProjectUndoTimer) window.clearTimeout(pendingProjectUndoTimer);
    if (pendingProjectUndo) { undoStack.push(pendingProjectUndo); pendingProjectUndo = null; pendingProjectUndoTimer = null; }
    if (pendingUndoTimer) window.clearTimeout(pendingUndoTimer);
    if (pendingUndo) { undoStack.push(pendingUndo); pendingUndo = null; pendingUndoTimer = null; }
    undoStack.push({kind:'order',order:blocks.map(item => Number(item.id))});
    redoStack.length = 0;
    updateHistoryButtons();
  }
  function updateHistoryButtons() {
    const undo = document.getElementById('builderUndo');
    const redo = document.getElementById('builderRedo');
    if (undo) undo.disabled = !undoStack.length && !pendingUndo && !pendingProjectUndo;
    if (redo) redo.disabled = !redoStack.length;
  }
  async function applyHistorySnapshot(snapshot, destination) {
    if (!snapshot) return;
    if (snapshot.kind === 'project-order') {
      destination.push({kind:'project-order',order:projects.map(item => Number(item.id))});
      const positions = new Map(snapshot.order.map((id,index) => [Number(id),index]));
      projects.sort((a,b) => (positions.get(Number(a.id)) ?? 999999) - (positions.get(Number(b.id)) ?? 999999));
      await request('project-reorder','POST',{ids:projects.map(item => Number(item.id))});
      renderCanvas(); postPreviewState(); updateHistoryButtons();
      status('ترتیب کارت‌های پروژه بازیابی شد.','success'); return;
    }
    if (snapshot.kind === 'project') {
      const currentProject = projects.find(item => Number(item.id) === Number(snapshot.id));
      if (!currentProject) return;
      if (await flushProjectSave(Number(currentProject.id)) === false) throw new Error('ذخیره تغییرات معلق پروژه ناموفق بود.');
      destination.push({kind:'project',id:Number(currentProject.id),payload:projectFormPayloadForProject(currentProject)});
      await request('project-save','PUT',snapshot.payload,Number(snapshot.id));
      Object.assign(currentProject,cloneData(snapshot.payload));
      selectedProjectId = Number(currentProject.id); selectedId = null; selectedNodeId = null; selectedLegacyField = null;
      renderCanvas(); renderInspector(); postPreviewState(); updateHistoryButtons();
      status('ویرایش کارت پروژه بازیابی شد.','success'); return;
    }

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
    if (pendingProjectUndoTimer) window.clearTimeout(pendingProjectUndoTimer);
    if (pendingProjectUndo) { undoStack.push(pendingProjectUndo); pendingProjectUndo = null; pendingProjectUndoTimer = null; }
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
  async function addBlockAtPosition(type,targetId) {
    try {
      const result = await request('builder-block-add','POST',{type});
      await load(result.id);
      const addedId = Number(result.id);
      if (targetId) {
        const from = blocks.findIndex(item => Number(item.id) === addedId);
        const target = blocks.findIndex(item => Number(item.id) === Number(targetId));
        if (from >= 0 && target >= 0) {
          const moved = blocks.splice(from,1)[0];
          const destination = blocks.findIndex(item => Number(item.id) === Number(targetId));
          blocks.splice(destination < 0 ? blocks.length : destination,0,moved);
          await request('builder-block-reorder','POST',{ids:blocks.map(item => Number(item.id))});
          renderCanvas();
          postPreviewState();
        }
      }
      status('بلوک در موقعیت انتخاب‌شده اضافه شد.','success');
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
      const projectResult = await flushAllProjectSaves();
      if (designSaveTimer) window.clearTimeout(designSaveTimer);
      designSaveTimer = null;
      const designResult = await persistDesign();
      if (results.every(result => result !== false) && projectResult !== false && designResult !== false) {
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
      if (loaded && (!(await flushAllPending()) || !(await flushAllProjectSaves()))) {
        status('ذخیره برخی تغییرات ناموفق بود؛ تازه‌سازی لغو شد.','error');
        return;
      }
      const results = await Promise.all([request('builder'),request('projects')]);
      const data = results[0];
      blocks = data.blocks || [];
      projects = Array.isArray(results[1]) ? results[1] : [];
      if (selectedProjectId !== null && !projects.some(item => Number(item.id) === Number(selectedProjectId))) selectedProjectId = null;
      const normalizedBlocks = [];
      const usedNodeIds = new Set();
      blocks.forEach(block => { if (normalizeNestedIds(block,usedNodeIds)) normalizedBlocks.push(block); });
      design = data.design || {};
      if (selectId !== null) { selectedId = Number(selectId); selectedNodeId = null; selectedLegacyField = null; }
      if (selectedProjectId === null && !blocks.some(item => Number(item.id) === Number(selectedId))) {
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
  root.addEventListener('dragover',event => {
    if (!dragLibraryType) return;
    const target = event.target.closest('.builder-block,#builderCanvas,.builder-canvas-wrap');
    if (!target) return;
    event.preventDefault();
    target.classList.add('is-library-drop-target');
  });
  root.addEventListener('dragleave',event => {
    const target = event.target.closest('.is-library-drop-target');
    if (target && !target.contains(event.relatedTarget)) target.classList.remove('is-library-drop-target');
  });
  root.addEventListener('drop',async event => {
    if (!dragLibraryType) return;
    const target = event.target.closest('.builder-block,#builderCanvas,.builder-canvas-wrap');
    if (!target) return;
    event.preventDefault();
    const type = dragLibraryType;
    const targetBlock = target.closest('.builder-block');
    const targetId = targetBlock ? Number(targetBlock.dataset.blockId) : null;
    dragLibraryType = null;
    root.querySelectorAll('.is-library-drop-target').forEach(node => node.classList.remove('is-library-drop-target'));
    await addBlockAtPosition(type,targetId);
  });

  root.addEventListener('dragend',() => { nestedDragSource = null; });
  root.addEventListener('click',event => {
    const repeaterAction = event.target.closest('[data-repeater-add],[data-repeater-remove],[data-repeater-up],[data-repeater-down]');
    if (repeaterAction) {
      const host = repeaterAction.closest('[data-repeater]');
      if (host) {
        const current = repeaterAction.closest('[data-repeater-item]');
        if (repeaterAction.hasAttribute('data-repeater-add')) {
          const keys = String(host.dataset.repeaterFields || 'title,description').split(',').filter(Boolean);
          const labels = {title:'عنوان',description:'توضیحات',text:'متن',question:'پرسش',answer:'پاسخ',name:'نام',role:'سمت',date:'تاریخ',value:'مقدار',label:'برچسب',src:'آدرس تصویر',alt:'متن جایگزین',url:'لینک',price:'قیمت'};
          const next = document.createElement('div'); next.className='builder-repeater-item'; next.dataset.repeaterItem='';
          next.innerHTML = '<div class="builder-repeater-item-head"><strong>آیتم جدید</strong><div><button type="button" data-repeater-up>↑</button><button type="button" data-repeater-down>↓</button><button type="button" data-repeater-remove>×</button></div></div>' +
            keys.map(key => '<label>' + escapeHtml(labels[key] || key) + (['description','answer','quote','body'].includes(key) ? '<textarea data-repeater-field="' + escapeHtml(key) + '"></textarea>' : '<input data-repeater-field="' + escapeHtml(key) + '">') + '</label>').join('');
          host.appendChild(next);
        } else if (current && repeaterAction.hasAttribute('data-repeater-remove')) current.remove();
        else if (current && repeaterAction.hasAttribute('data-repeater-up') && current.previousElementSibling) host.insertBefore(current,current.previousElementSibling);
        else if (current && repeaterAction.hasAttribute('data-repeater-down') && current.nextElementSibling) host.insertBefore(current.nextElementSibling,current);
        const form = repeaterAction.closest('form');
        if (form) host.dispatchEvent(new Event('input',{bubbles:true}));
      }
      return;
    }

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
    'profile-intro':{title:'معرفی شخصی',category:'personal',description:'تیتر، توضیح و دکمه معرفی',type:'container',label:'معرفی شخصی',content:{direction:'column',children:[{id:'node-profile-heading',type:'heading',content:{text:'سلام، من میلاد هستم',level:1}},{id:'node-profile-copy',type:'paragraph',content:{text:'اینجا معرفی کوتاهی درباره خودت بنویس.'}},{id:'node-profile-button',type:'button',content:{text:'مشاهده پروژه‌ها',url:'#work'}}]}},
    'feature-grid':{title:'گرید ویژگی‌ها',category:'business',description:'سه کارت ویژگی با رنگ آبی',type:'grid',label:'گرید ویژگی‌ها',content:{columns:3,children:[{id:'node-f-1',type:'feature',content:{symbol:'✦',title:'طراحی دقیق',description:'تجربه‌ای زیبا و یکپارچه'}},{id:'node-f-2',type:'feature',content:{symbol:'⚡',title:'توسعه سریع',description:'عملکرد روان و سریع'}},{id:'node-f-3',type:'feature',content:{symbol:'◈',title:'واکنش‌گرا',description:'سازگار با تمام دستگاه‌ها'}}]}},
    callout:{title:'دعوت به اقدام',category:'hero',description:'بخش برجسته با دکمه اصلی',type:'cta',label:'دعوت به اقدام',content:{title:'آماده شروع هستی؟',body:'برای شروع یک گفت‌وگو با من در ارتباط باش.',button_text:'تماس با من',button_url:'#contact'},settings:{background:'#172554',text_color:'#ffffff',padding:42,radius:24,text_align:'center'}},
    'developer-hero':{title:'Developer Hero',category:'hero',description:'معرفی توسعه‌دهنده با تم بنفش',type:'container',label:'Developer Hero',content:{direction:'column',children:[{id:'node-dev-1',type:'icon_box',content:{symbol:'⌘',title:'INDEPENDENT DEVELOPER',description:'طراحی و توسعه نرم‌افزار'}},{id:'node-dev-2',type:'heading',content:{text:'ایده‌ها را به محصول تبدیل می‌کنم.',level:1}},{id:'node-dev-3',type:'paragraph',content:{text:'توسعه‌دهنده علاقه‌مند به لینوکس، نرم‌افزارهای متن‌باز و تجربه‌های دیجیتال.'}},{id:'node-dev-4',type:'button',content:{text:'مشاهده پروژه‌ها',url:'#work'}}]},settings:{background:'#17152f',text_color:'#f6f2ff',padding:54,radius:28}},
    'saas-features':{title:'SaaS Features',category:'business',description:'ویژگی‌های محصول در کارت‌های سبزآبی',type:'grid',label:'ویژگی‌های محصول',content:{columns:3,children:[{id:'node-saas-1',type:'feature',content:{symbol:'⚡',title:'سریع و سبک',description:'عملکرد بهینه و پاسخ‌گویی سریع'}},{id:'node-saas-2',type:'feature',content:{symbol:'🔒',title:'امنیت بالا',description:'حفاظت از اطلاعات'}},{id:'node-saas-3',type:'feature',content:{symbol:'☁',title:'همگام‌سازی',description:'دسترسی از هر دستگاه'}}]},settings:{background:'#eff6ff',text_color:'#172554',padding:32,radius:20}},
    'pricing-table':{title:'Pricing Plans',category:'business',description:'سه پلن قیمت‌گذاری قابل ویرایش',type:'grid',label:'پلن‌های قیمت‌گذاری',content:{columns:3,children:[{id:'node-price-1',type:'pricing',content:{title:'شروع',price:'رایگان',period:'همیشگی',description:'برای شروع مسیر',items:[{text:'امکانات پایه'},{text:'پشتیبانی عمومی'}],button_text:'شروع کنید',button_url:'#contact'}},{id:'node-price-2',type:'pricing',content:{title:'حرفه‌ای',price:'۲۹۹٬۰۰۰',period:'ماهانه',description:'برای کاربران حرفه‌ای',items:[{text:'تمام امکانات'},{text:'پشتیبانی سریع'}],button_text:'انتخاب پلن',button_url:'#contact'},styles:{background:'#172554',color:'#ffffff'}},{id:'node-price-3',type:'pricing',content:{title:'سازمانی',price:'توافقی',period:'سفارشی',description:'برای تیم‌ها و شرکت‌ها',items:[{text:'امکانات اختصاصی'},{text:'پشتیبانی ویژه'}],button_text:'تماس بگیرید',button_url:'#contact'}}]}},
    'faq-section':{title:'FAQ Accordion',category:'content',description:'سؤالات متداول بازشونده',type:'accordion',label:'سؤالات متداول',content:{title:'سؤالات متداول',items:[{question:'چطور می‌توانم شروع کنم؟',answer:'از طریق دکمه تماس با ما درخواستت را ارسال کن.'},{question:'آیا طراحی واکنش‌گراست؟',answer:'بله، تمام بخش‌ها برای موبایل و تبلت قابل تنظیم هستند.'},{question:'چطور پشتیبانی بگیرم؟',answer:'از راه‌های ارتباطی سایت با ما در تماس باش.'}]}},
    'testimonial-wall':{title:'Testimonials',category:'business',description:'کارت‌های نقل‌قول مشتریان',type:'grid',label:'نظرات مشتریان',content:{columns:2,children:[{id:'node-q-1',type:'testimonial',content:{quote:'کیفیت اجرا و توجه به جزئیات واقعاً عالی بود.',author:'آرمان رضایی',role:'مدیر محصول'}},{id:'node-q-2',type:'testimonial',content:{quote:'تجربه کار بسیار روان و حرفه‌ای بود.',author:'سارا محمدی',role:'طراح محصول'}}]}},
    'portfolio-gallery':{title:'Portfolio Gallery',category:'personal',description:'گالری پروژه‌ها و تصاویر',type:'gallery',label:'گالری پروژه‌ها',content:{title:'منتخب پروژه‌ها',items:[{src:'',alt:'پروژه اول',title:'پروژه اول'},{src:'',alt:'پروژه دوم',title:'پروژه دوم'},{src:'',alt:'پروژه سوم',title:'پروژه سوم'}]}},
    'career-timeline':{title:'Career Timeline',category:'personal',description:'نمایش تجربه و مسیر توسعه',type:'timeline',label:'خط زمانی مسیر',content:{title:'مسیر من',items:[{date:'۱۴۰۳',title:'شروع توسعه',description:'آغاز فعالیت در حوزه نرم‌افزار'},{date:'۱۴۰۴',title:'پروژه‌های متن‌باز',description:'ساخت و انتشار ابزارهای آزاد'},{date:'۱۴۰۵',title:'توسعه محصولات',description:'تمرکز بر محصول و تجربه کاربری'}]}},
    'skills-panel':{title:'Skills & Progress',category:'personal',description:'مهارت‌ها با نوار پیشرفت',type:'container',label:'مهارت‌ها',content:{direction:'column',children:[{id:'node-sk-1',type:'heading',content:{text:'مهارت‌های من',level:2}},{id:'node-sk-2',type:'skill',content:{title:'JavaScript',value:90}},{id:'node-sk-3',type:'skill',content:{title:'PHP',value:82}},{id:'node-sk-4',type:'skill',content:{title:'Linux',value:95}}]}},
    'team-section':{title:'Team Showcase',category:'business',description:'معرفی اعضا و نقش‌ها',type:'team',label:'اعضای تیم',content:{title:'با تیم ما آشنا شوید',items:[{name:'علی احمدی',role:'توسعه‌دهنده'},{name:'سارا رضایی',role:'طراح رابط کاربری'},{name:'مهدی کریمی',role:'مدیر محصول'}]}},
    'logo-cloud':{title:'Logo Cloud',category:'business',description:'لوگوی همکاران و مشتریان',type:'logo_cloud',label:'همکاران و مشتریان',content:{title:'همراهان ما',items:[{name:'Open Source'},{name:'Zenora'},{name:'Milad Studio'},{name:'Community'}]}},
    'newsletter-box':{title:'Newsletter CTA',category:'content',description:'دعوت به عضویت خبرنامه',type:'newsletter',label:'عضویت در خبرنامه',content:{title:'از خبرهای تازه جا نمان',description:'مقالات و به‌روزرسانی‌های جدید را دریافت کن.',placeholder:'آدرس ایمیل شما',button_text:'عضویت'},settings:{background:'#064e3b',text_color:'#ffffff',padding:36,radius:22,text_align:'center'}},
    'tabs-content':{title:'Content Tabs',category:'content',description:'بخش چندتب محتوایی',type:'tabs',label:'تب‌های محتوایی',content:{title:'بیشتر بدانید',items:[{title:'معرفی',description:'اطلاعات کلی درباره محصول و خدمات.'},{title:'ویژگی‌ها',description:'جزئیات قابلیت‌ها و امکانات.'},{title:'پشتیبانی',description:'راه‌های دریافت راهنمایی.'}]}},
    'minimal-contact':{title:'Minimal Contact',category:'hero',description:'بخش تماس مینیمال',type:'container',label:'بخش ارتباط',content:{direction:'column',children:[{id:'node-c-1',type:'heading',content:{text:'بیایید با هم چیزی بسازیم.',level:2}},{id:'node-c-2',type:'paragraph',content:{text:'برای همکاری، پیشنهاد یا پرسش با من در ارتباط باش.'}},{id:'node-c-3',type:'button',content:{text:'ارسال پیام',url:'#contact'}}]},settings:{background:'#fff7ed',text_color:'#431407',padding:40,radius:24}},
    'full-developer-portfolio':{title:'Developer Portfolio · Full Page',category:'full',description:'معرفی، مهارت‌ها، مسیر حرفه‌ای، پروژه‌ها و تماس',blocks:[
      {type:'container',label:'معرفی توسعه‌دهنده',settings:{background:'#17152f',text_color:'#f6f2ff',padding:52,radius:26},content:{direction:'column',children:[{id:'fp-dev-h',type:'heading',content:{text:'ایده‌ها را به محصول تبدیل می‌کنم.',level:1}},{id:'fp-dev-p',type:'paragraph',content:{text:'توسعه‌دهنده مستقل علاقه‌مند به لینوکس و نرم‌افزارهای متن‌باز.'}},{id:'fp-dev-b',type:'button',content:{text:'مشاهده پروژه‌ها',url:'#work'}}]}},
      {type:'grid',label:'مهارت‌ها',content:{columns:3,children:[{id:'fp-dev-f1',type:'feature',content:{symbol:'⌘',title:'توسعه نرم‌افزار',description:'ساخت محصولات کاربردی'}},{id:'fp-dev-f2',type:'feature',content:{symbol:'🐧',title:'Linux & Open Source',description:'سیستم‌عامل و متن‌باز'}},{id:'fp-dev-f3',type:'feature',content:{symbol:'✦',title:'رابط کاربری',description:'تجربه مدرن و روان'}}]}},
      {type:'timeline',label:'مسیر حرفه‌ای',content:{title:'مسیر من',items:[{date:'۱۴۰۳',title:'شروع توسعه',description:'آغاز ساخت پروژه‌ها'},{date:'۱۴۰۴',title:'متن‌باز',description:'انتشار ابزارهای آزاد'},{date:'۱۴۰۵',title:'توسعه محصولات',description:'تمرکز بر کیفیت'}]}},
      {type:'grid',label:'پروژه‌های منتخب',content:{columns:3,children:[{id:'fp-dev-p1',type:'card',content:{title:'پروژه اول',body:'شرح و فناوری‌ها'}},{id:'fp-dev-p2',type:'card',content:{title:'پروژه دوم',body:'شرح و دستاوردها'}},{id:'fp-dev-p3',type:'card',content:{title:'پروژه سوم',body:'شرح و لینک مشاهده'}}]}},
      {type:'cta',label:'دعوت به همکاری',settings:{background:'#312e81',text_color:'#fff',padding:38,radius:22,text_align:'center'},content:{title:'برای ساخت ایده بعدی آماده‌ای؟',body:'برای همکاری با من در ارتباط باش.',button_text:'ارتباط با من',button_url:'#contact'}}
    ]},
    'full-saas-landing':{title:'SaaS Landing · Full Page',category:'full',description:'معرفی محصول، امکانات، قیمت‌گذاری و FAQ',blocks:[
      {type:'container',label:'Hero محصول SaaS',settings:{background:'#172554',text_color:'#eff6ff',padding:52,radius:26,text_align:'center'},content:{direction:'column',children:[{id:'fp-saas-h',type:'heading',content:{text:'راهکار هوشمند برای رشد کسب‌وکار شما',level:1}},{id:'fp-saas-p',type:'paragraph',content:{text:'مدیریت کارها و همکاری تیمی در یک فضای یکپارچه.'}},{id:'fp-saas-b',type:'button',content:{text:'شروع رایگان',url:'#contact'}}]}},
      {type:'grid',label:'امکانات محصول',content:{columns:3,children:[{id:'fp-saas-f1',type:'feature',content:{symbol:'⚡',title:'سریع و ساده',description:'فرایندهای روزمره را ساده کن.'}},{id:'fp-saas-f2',type:'feature',content:{symbol:'🔒',title:'امن و مطمئن',description:'از داده‌ها محافظت کن.'}},{id:'fp-saas-f3',type:'feature',content:{symbol:'☁',title:'همیشه در دسترس',description:'از هر دستگاهی استفاده کن.'}}]}},
      {type:'grid',label:'پلن‌های قیمت‌گذاری',content:{columns:3,children:[{id:'fp-saas-p1',type:'pricing',content:{title:'رایگان',price:'۰',period:'همیشگی',description:'برای شروع',items:[{text:'امکانات پایه'}],button_text:'شروع کنید',button_url:'#contact'}},{id:'fp-saas-p2',type:'pricing',content:{title:'حرفه‌ای',price:'۲۹۹٬۰۰۰',period:'ماهانه',description:'برای تیم‌ها',items:[{text:'تمام امکانات'},{text:'پشتیبانی سریع'}],button_text:'انتخاب پلن',button_url:'#contact'},styles:{background:'#172554',color:'#fff'}},{id:'fp-saas-p3',type:'pricing',content:{title:'سازمانی',price:'توافقی',period:'سفارشی',description:'برای سازمان‌ها',items:[{text:'امکانات اختصاصی'}],button_text:'مشاوره',button_url:'#contact'}}]}},
      {type:'accordion',label:'پرسش‌های متداول',content:{title:'سؤالات متداول',items:[{question:'آیا اشتراک قابل لغو است؟',answer:'بله، شرایط اشتراک قابل تنظیم است.'},{question:'چطور شروع کنم؟',answer:'از دکمه شروع استفاده کن.'}]}},
      {type:'cta',label:'دعوت به شروع',settings:{background:'#0f766e',text_color:'#fff',padding:38,radius:22,text_align:'center'},content:{title:'همین امروز شروع کن',body:'اولین قدم را بردار.',button_text:'شروع رایگان',button_url:'#contact'}}
    ]},
    'full-cyber-tech':{title:'Cyber Tech · Full Page',category:'full',description:'قالب فناوری تیره با قابلیت‌ها، مشخصات فنی و دانلود',blocks:[
      {type:'container',label:'Hero فناوری',settings:{background:'#071b16',text_color:'#eafff3',padding:54,radius:26},content:{direction:'column',children:[{id:'fp-cyber-h',type:'heading',content:{text:'تکنولوژی بدون محدودیت',level:1}},{id:'fp-cyber-p',type:'paragraph',content:{text:'محصولی مدرن برای توسعه‌دهندگان و کاربران حرفه‌ای.'}},{id:'fp-cyber-b',type:'button',content:{text:'دریافت محصول',url:'#contact'}}]}},
      {type:'grid',label:'قابلیت‌های فنی',content:{columns:3,children:[{id:'fp-cyber-f1',type:'feature',content:{symbol:'⌘',title:'کنترل کامل',description:'تنظیم متناسب با نیاز'}},{id:'fp-cyber-f2',type:'feature',content:{symbol:'⚡',title:'عملکرد سریع',description:'سرعت و پایداری'}},{id:'fp-cyber-f3',type:'feature',content:{symbol:'◈',title:'متن‌باز',description:'شفاف و قابل توسعه'}}]}},
      {type:'stats',label:'مشخصات و آمار',content:{title:'در یک نگاه',items:[{value:'۶۴-bit',label:'معماری',description:'پشتیبانی مدرن'},{value:'۲۴/۷',label:'دسترسی',description:'همیشه آماده'},{value:'۰۱',label:'هسته اصلی',description:'ساختار یکپارچه'}]}},
      {type:'timeline',label:'نقشه راه',content:{title:'نقشه راه توسعه',items:[{date:'مرحله ۱',title:'نسخه اولیه',description:'انتشار نسخه پایه'},{date:'مرحله ۲',title:'قابلیت بیشتر',description:'توسعه ابزارها'},{date:'مرحله ۳',title:'اکوسیستم',description:'گسترش جامعه'}]}},
      {type:'cta',label:'دریافت و شروع',settings:{background:'#052e24',text_color:'#ecfdf5',padding:40,radius:22,text_align:'center'},content:{title:'آماده تجربه نسل بعدی هستی؟',body:'نسخه جدید را بررسی کن.',button_text:'دریافت و شروع',button_url:'#contact'}}
    ]},
    'full-creative-agency':{title:'Creative Agency · Full Page',category:'full',description:'قالب آژانس خلاق با خدمات، نمونه‌کار، تیم و مشتریان',blocks:[
      {type:'container',label:'معرفی آژانس',settings:{background:'#fff1e8',text_color:'#431407',padding:50,radius:26},content:{direction:'column',children:[{id:'fp-agency-h',type:'heading',content:{text:'ایده‌های بزرگ، تجربه‌های ماندگار',level:1}},{id:'fp-agency-p',type:'paragraph',content:{text:'به برندها کمک می‌کنیم تجربه‌های دیجیتال بهتری بسازند.'}},{id:'fp-agency-b',type:'button',content:{text:'شروع پروژه',url:'#contact'}}]}},
      {type:'grid',label:'خدمات آژانس',content:{columns:3,children:[{id:'fp-agency-f1',type:'feature',content:{symbol:'✦',title:'استراتژی برند',description:'هویت و مسیر ارتباطی'}},{id:'fp-agency-f2',type:'feature',content:{symbol:'◈',title:'طراحی محصول',description:'رابط و تجربه کاربری'}},{id:'fp-agency-f3',type:'feature',content:{symbol:'⚡',title:'توسعه وب',description:'محصول سریع و منعطف'}}]}},
      {type:'gallery',label:'نمونه‌کارها',content:{title:'منتخب پروژه‌ها',items:[{src:'',alt:'نمونه اول',title:'پروژه اول'},{src:'',alt:'نمونه دوم',title:'پروژه دوم'},{src:'',alt:'نمونه سوم',title:'پروژه سوم'}]}},
      {type:'team',label:'اعضای تیم',content:{title:'با تیم ما آشنا شوید',items:[{name:'عضو اول',role:'مدیر خلاقیت'},{name:'عضو دوم',role:'طراح محصول'},{name:'عضو سوم',role:'توسعه‌دهنده'}]}},
      {type:'grid',label:'بازخورد مشتریان',content:{columns:2,children:[{id:'fp-agency-q1',type:'testimonial',content:{quote:'تجربه همکاری حرفه‌ای و نتیجه عالی بود.',author:'مشتری اول',role:'مدیر کسب‌وکار'}},{id:'fp-agency-q2',type:'testimonial',content:{quote:'تیم با دقت پروژه را تحویل داد.',author:'مشتری دوم',role:'مدیر محصول'}}]}},
      {type:'cta',label:'شروع همکاری',settings:{background:'#7c2d12',text_color:'#fff7ed',padding:40,radius:22,text_align:'center'},content:{title:'پروژه بعدی را با هم بسازیم',body:'درباره ایده‌ات صحبت کن.',button_text:'درخواست همکاری',button_url:'#contact'}}
    ]},
    'full-editorial-blog':{title:'Editorial Blog · Full Page',category:'full',description:'قالب محتوایی با مطالب منتخب، دسته‌بندی و خبرنامه',blocks:[
      {type:'container',label:'معرفی مجله',settings:{background:'#f1f5f9',text_color:'#0f172a',padding:46,radius:22},content:{direction:'column',children:[{id:'fp-blog-h',type:'heading',content:{text:'ایده‌ها، یادداشت‌ها و روایت‌ها',level:1}},{id:'fp-blog-p',type:'paragraph',content:{text:'مقاله‌ها، تجربه‌ها و بینش‌های تازه در یک فضای مینیمال.'}}]}},
      {type:'grid',label:'مطالب منتخب',content:{columns:3,children:[{id:'fp-blog-a1',type:'card',content:{title:'راهنمای شروع',body:'معرفی کوتاه مقاله اول.'}},{id:'fp-blog-a2',type:'card',content:{title:'نگاه عمیق‌تر',body:'معرفی کوتاه مقاله دوم.'}},{id:'fp-blog-a3',type:'card',content:{title:'یادداشت تازه',body:'معرفی کوتاه مقاله سوم.'}}]}},
      {type:'tabs',label:'دسته‌بندی مطالب',content:{title:'موضوعات',items:[{title:'فناوری',description:'مقاله‌های فنی'},{title:'طراحی',description:'تجربه کاربری و محصول'},{title:'تجربه‌ها',description:'روایت‌ها و آموخته‌ها'}]}},
      {type:'timeline',label:'آخرین انتشارها',content:{title:'تازه‌ترین مطالب',items:[{date:'۱۴۰۵/۰۱',title:'مقاله اول',description:'خلاصه مطلب اول'},{date:'۱۴۰۵/۰۲',title:'مقاله دوم',description:'خلاصه مطلب دوم'},{date:'۱۴۰۵/۰۳',title:'مقاله سوم',description:'خلاصه مطلب سوم'}]}},
      {type:'newsletter',label:'عضویت خبرنامه',content:{title:'مطالب تازه را دریافت کن',description:'برای دریافت مقاله‌ها عضو شو.',placeholder:'آدرس ایمیل',button_text:'عضویت'}},
      {type:'cta',label:'ارتباط با تحریریه',settings:{background:'#0f172a',text_color:'#f8fafc',padding:36,radius:22,text_align:'center'},content:{title:'پیشنهاد یا دیدگاهی داری؟',body:'برای همکاری یا ارسال پیشنهاد با ما تماس بگیر.',button_text:'ارتباط با ما',button_url:'#contact'}}
    ]},

  };
  function renderTemplateCatalog() {
    const host = document.getElementById('builderTemplateGrid');
    if (!host) return;
    const term = String(document.getElementById('builderTemplateSearch')?.value || '').trim().toLocaleLowerCase('fa');
    const active = document.querySelector('[data-template-filter].is-active')?.dataset.templateFilter || 'all';
    const palette = {hero:['#312e81','#818cf8'],business:['#0f766e','#5eead4'],content:['#9a3412','#fdba74'],personal:['#6d28d9','#c4b5fd'],full:['#1f2937','#a3e635']};
    const entries = Object.entries(templates).filter(([key,item]) => (active === 'all' || item.category === active) && (!term || (key + ' ' + item.title + ' ' + item.description).toLocaleLowerCase('fa').includes(term)));
    host.innerHTML = entries.map(([key,item]) => {
      const colors = palette[item.category] || palette.personal;
      const preview = '<div class="studio-template-preview" style="--template-primary:' + colors[0] + ';--template-accent:' + colors[1] + '"><span></span><b></b><i></i><div><em></em><em></em><em></em></div></div>';
      return '<button type="button" class="studio-template-card' + (Array.isArray(item.blocks) ? ' is-full-page-template' : '') + '" data-template="' + escapeHtml(key) + '">' + preview + '<span><strong>' + escapeHtml(item.title) + '</strong><small>' + escapeHtml(item.description) + '</small><em>' + (Array.isArray(item.blocks) ? item.blocks.length + ' سکشن · صفحه کامل' : 'سکشن آماده') + '</em></span><i>＋</i></button>';
    }).join('') || '<div class="builder-empty">قالبی با این مشخصات پیدا نشد.</div>';
  }
  async function applyTemplate(key) {
    const template = templates[key];
    if (!template) return;
    const sections = Array.isArray(template.blocks) ? template.blocks : [template];
    const createdIds = [];
    const templateButton = templateModal && templateModal.querySelector('[data-template="' + key + '"]');
    if (templateButton) templateButton.disabled = true;
    status('در حال افزودن ' + sections.length + ' بخش قالب…');
    try {
      for (const section of sections) {
        const created = await request('builder-block-add','POST',{type:section.type});
        createdIds.push(Number(created.id));
        await request('builder-block-save','PUT',{
          label:section.label,content:section.content || {},settings:section.settings || {},is_visible:true
        },created.id);
      }
      if (templateModal) templateModal.hidden = true;
      await load(createdIds[createdIds.length - 1] || null);
      status(Array.isArray(template.blocks) ? 'قالب کامل به صفحه اضافه شد؛ همه بخش‌ها قابل ویرایش هستند.' : 'سکشن آماده به صفحه اضافه شد.','success');
    } catch (error) {
      await Promise.all(createdIds.map(id => request('builder-block-delete','DELETE',{},id).catch(() => null)));
      await load();
      status(error.message,'error');
    } finally {
      if (templateButton) templateButton.disabled = false;
    }
  }
  $('#builderTemplates')?.addEventListener('click',() => { if (templateModal) templateModal.hidden = false; renderTemplateCatalog(); });
  document.getElementById('builderTemplateSearch')?.addEventListener('input',renderTemplateCatalog);
  document.querySelectorAll('[data-template-filter]').forEach(button => button.addEventListener('click',() => {
    document.querySelectorAll('[data-template-filter]').forEach(item => item.classList.toggle('is-active',item === button));
    renderTemplateCatalog();
  }));
  if (templateModal) templateModal.addEventListener('click',event => {
    if (event.target === templateModal || event.target.closest('[data-template-close]')) templateModal.hidden = true;
    const button = event.target.closest('[data-template]');
    if (button) applyTemplate(button.dataset.template);
  });


  document.querySelectorAll('[data-add-block]').forEach(button => {
    button.draggable = true;
    button.addEventListener('dragstart',event => {
      dragLibraryType = String(button.dataset.addBlock || '');
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('text/plain','milad-studio-block:' + dragLibraryType);
      }
      button.classList.add('is-dragging');
    });
    button.addEventListener('dragend',() => {
      dragLibraryType = null;
      button.classList.remove('is-dragging');
      root.querySelectorAll('.is-library-drop-target').forEach(node => node.classList.remove('is-library-drop-target'));
    });
    button.addEventListener('click',() => addBlock(button.dataset.addBlock));
  });
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
      feature:{content:{symbol:'✦',title:'ویژگی جدید',description:'توضیح ویژگی'}},
      pricing:{content:{title:'پلن حرفه‌ای',price:'۲۹۹٬۰۰۰',period:'ماهانه',description:'برای شروع و رشد',items:[{text:'دسترسی کامل'},{text:'پشتیبانی'}],button_text:'انتخاب پلن',button_url:'#contact'}},
      accordion:{content:{title:'سؤالات متداول',items:[{question:'چطور شروع کنم؟',answer:'از طریق فرم تماس با ما در ارتباط باش.'}]}},
      testimonial:{content:{quote:'همکاری بسیار خوبی بود.',author:'نام مشتری',role:'مشتری'}},
      gallery:{content:{title:'گالری تصاویر',items:[{src:'',alt:'تصویر اول',title:'تصویر اول'},{src:'',alt:'تصویر دوم',title:'تصویر دوم'}]}},
      timeline:{content:{title:'مسیر ما',items:[{date:'۱۴۰۵',title:'شروع',description:'آغاز مسیر'}]}},
      skill:{content:{title:'JavaScript',value:85}},
      logo_cloud:{content:{title:'مورد اعتماد',items:[{name:'Brand One'},{name:'Brand Two'}]}},
      team:{content:{title:'تیم ما',items:[{name:'نام عضو',role:'توسعه‌دهنده'}]}},
      tabs:{content:{title:'جزئیات',items:[{title:'معرفی',description:'توضیحات تب اول'},{title:'ویژگی‌ها',description:'توضیحات تب دوم'}]}},
      newsletter:{content:{title:'عضویت در خبرنامه',description:'آخرین مطالب را دریافت کن.',placeholder:'ایمیل شما',button_text:'عضویت'}},
      icon_box:{content:{symbol:'✦',title:'عنوان کارت',description:'توضیحات کوتاه'}},
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
  let canvasClipboard = null;
  const canvasContextMenu = document.getElementById('builderCanvasContextMenu');
  function openCanvasContextMenu(target) {
    if (!canvasContextMenu || !frame) return;
    const rect = frame.getBoundingClientRect();
    canvasContextMenu.style.left = Math.max(8,Math.min(window.innerWidth - 228,rect.left + target.x)) + 'px';
    canvasContextMenu.style.top = Math.max(8,Math.min(window.innerHeight - 420,rect.top + target.y)) + 'px';
    canvasContextMenu.hidden = false;
  }
  function closeCanvasContextMenu() { if (canvasContextMenu) canvasContextMenu.hidden = true; }
  function activeProject() { return projects.find(item => Number(item.id) === Number(selectedProjectId)) || null; }
  function copySelectedElement() {
    const project = activeProject();
    const block = blocks.find(item => Number(item.id) === Number(selectedId));
    if (project) canvasClipboard = {kind:'project',data:cloneData(project)};
    else if (block && selectedNodeId) {
      const node = findNestedNode(block,selectedNodeId);
      if (node) canvasClipboard = {kind:'node',data:cloneData(node.node)};
    } else if (block) canvasClipboard = {kind:'block',data:cloneData(block)};
    if (canvasClipboard) status('المان انتخاب‌شده کپی شد.','success');
  }
  async function pasteSelectedElement() {
    if (!canvasClipboard) return;
    try {
      if (canvasClipboard.kind === 'project') {
        const copy=cloneData(canvasClipboard.data);
        copy.slug=String(copy.slug||'project')+'-copy-'+Date.now().toString(36);
        copy.title=String(copy.title||'پروژه')+' (کپی)';
        copy.sort_order=projects.length+1;
        const result=await request('projects','POST',copy);
        await load(); selectProject(Number(result.id)); return;
      }
      if (canvasClipboard.kind === 'block') {
        const source=cloneData(canvasClipboard.data);
        const result=await request('builder-block-add','POST',{type:source.block_type});
        await load(Number(result.id));
        const block=blocks.find(item=>Number(item.id)===Number(result.id));
        if (!block) return;
        queueUndo(block); block.label=String(source.label||names[source.block_type]||source.block_type);
        block.content=cloneData(source.content||{}); block.settings=cloneData(source.settings||{});
        block.is_visible=source.is_visible!==false; normalizeNestedIds(block,new Set());
        scheduleSave(block,{label:block.label,content:block.content,settings:block.settings,is_visible:!!block.is_visible});
        renderCanvas();renderInspector();postPreviewState(); return;
      }
      if (canvasClipboard.kind === 'node') {
        const block=blocks.find(item=>Number(item.id)===Number(selectedId));
        if (!block) return;
        let collection=null;
        if (selectedNodeId) {
          const parent=findNestedNode(block,selectedNodeId);
          if (parent && ['container','grid','card'].includes(String(parent.node.type||''))) {
            if (!Array.isArray(parent.node.children)) parent.node.children=[];
            collection=parent.node.children;
          }
        } else if (['container','grid','card'].includes(block.block_type)) {
          block.content=Object.assign({},block.content||{});
          if (!Array.isArray(block.content.children)) block.content.children=[];
          collection=block.content.children;
        }
        if (!collection || collection.length>=40) { status('برای جای‌گذاری، یک کانتینر یا گرید با ظرفیت خالی انتخاب کن.','error'); return; }
        const copy=cloneData(canvasClipboard.data); regenerateNodeIds(copy); queueUndo(block); collection.push(copy);
        selectedNodeId=copy.id; renderCanvas();renderInspector();postPreviewState();
        scheduleSave(block,{label:block.label,content:block.content,settings:block.settings||{},is_visible:!!block.is_visible});
      }
    } catch(error) { status(error.message,'error'); }
  }
  function duplicateSelectedElement() {
    const project=activeProject();
    const block=blocks.find(item=>Number(item.id)===Number(selectedId));
    if(project) return duplicateProject(project.id);
    if(!block) return;
    if(!selectedNodeId) return cloneBlock(Number(block.id));
    const record=findNestedNode(block,selectedNodeId);
    if(!record) return;
    queueUndo(block);
    const copy=cloneData(record.node);regenerateNodeIds(copy);
    record.collection.splice(record.index+1,0,copy);selectedNodeId=copy.id;
    renderCanvas();renderInspector();postPreviewState();
    scheduleSave(block,{label:block.label,content:block.content,settings:block.settings||{},is_visible:!!block.is_visible});
  }
  function renameSelectedElement() {
    const project=activeProject();
    const block=blocks.find(item=>Number(item.id)===Number(selectedId));
    const record=block&&selectedNodeId?findNestedNode(block,selectedNodeId):null;
    const current=project?project.title:(record?record.node.name||names[record.node.type]||'':block&&block.label);
    const next=window.prompt('نام المان:',String(current||''));
    if(next===null||!next.trim())return;
    if(project){queueUndoProject(project);project.title=next.trim();scheduleProjectSave(project,projectFormPayloadForProject(project));renderCanvas();postPreviewState();renderInspector();}
    else if(block){queueUndo(block);if(record)record.node.name=next.trim();else block.label=next.trim();renderCanvas();scheduleSave(block,{label:block.label,content:block.content||{},settings:block.settings||{},is_visible:!!block.is_visible});}
  }
  function projectFormPayloadForProject(project) {
    return {slug:project.slug,title:project.title,category:project.category,kind_label:project.kind_label,excerpt:project.excerpt,description:project.description,status:project.status,visual_variant:project.visual_variant,is_featured:!!project.is_featured,is_published:!!project.is_published,sort_order:Number(project.sort_order||0),technologies:project.technologies||[],links:project.links||[]};
  }
  function hideSelectedElement() {
    const project=activeProject();
    const block=blocks.find(item=>Number(item.id)===Number(selectedId));
    if(project){queueUndoProject(project);project.is_published=!project.is_published;scheduleProjectSave(project,projectFormPayloadForProject(project));renderCanvas();postPreviewState();return;}
    if(!block)return;queueUndo(block);block.is_visible=!block.is_visible;
    renderCanvas();postPreviewState();scheduleSave(block,{label:block.label,content:block.content||{},settings:block.settings||{},is_visible:!!block.is_visible});
  }
  function resetSelectedStyles() {
    const project=activeProject();
    const block=blocks.find(item=>Number(item.id)===Number(selectedId));
    if(project){queueUndoProject(project);project.visual_variant='generic';scheduleProjectSave(project,projectFormPayloadForProject(project));renderCanvas();postPreviewState();renderInspector();return;}
    if(!block)return;queueUndo(block);
    if(selectedNodeId){const record=findNestedNode(block,selectedNodeId);if(!record)return;record.node.styles={};}
    else block.settings={};
    renderCanvas();renderInspector();postPreviewState();scheduleSave(block,{label:block.label,content:block.content||{},settings:block.settings||{},is_visible:!!block.is_visible});
  }
  function moveSelectedElement(delta) {
    const project=activeProject();
    const block=blocks.find(item=>Number(item.id)===Number(selectedId));
    if(project){
      const from=projects.findIndex(item=>Number(item.id)===Number(project.id)),to=from+delta;
      if(from<0||to<0||to>=projects.length)return;
      queueUndoProjectOrder();const item=projects.splice(from,1)[0];projects.splice(to,0,item);saveProjectOrder();return;
    }
    if(!block)return;
    if(selectedNodeId){
      const record=findNestedNode(block,selectedNodeId);if(!record)return;
      const to=record.index+delta;if(to<0||to>=record.collection.length)return;
      queueUndo(block);const item=record.collection.splice(record.index,1)[0];record.collection.splice(to,0,item);
      renderCanvas();renderInspector();postPreviewState();scheduleSave(block,{label:block.label,content:block.content,settings:block.settings||{},is_visible:!!block.is_visible});return;
    }
    move(Number(block.id),delta);
  }
  function selectParentElement() {
    const block=blocks.find(item=>Number(item.id)===Number(selectedId));
    if(!block||!selectedNodeId)return;
    const record=findNestedNode(block,selectedNodeId);if(!record)return;
    if(record.parent&&record.parent.id)selectNestedNode(block.id,record.parent.id);else selectBlock(block.id);
  }
  function selectFirstChildElement() {
    const block=blocks.find(item=>Number(item.id)===Number(selectedId));if(!block)return;
    const parent=selectedNodeId?findNestedNode(block,selectedNodeId)?.node:null;
    const list=parent?(parent.children||parent.content?.children||[]):(block.content?.children||[]);
    if(Array.isArray(list)&&list[0]?.id)selectNestedNode(block.id,list[0].id);
  }
  if(canvasContextMenu) {
    canvasContextMenu.addEventListener('click',event=>{
      const button=event.target.closest('[data-canvas-action]');if(!button)return;
      const action=button.dataset.canvasAction;closeCanvasContextMenu();
      if(action==='edit'){if(selectedProjectId!==null)selectProject(selectedProjectId);else if(selectedNodeId)selectNestedNode(selectedId,selectedNodeId);else if(selectedId)selectBlock(selectedId);}
      else if(action==='copy')copySelectedElement();
      else if(action==='paste')pasteSelectedElement();
      else if(action==='duplicate')duplicateSelectedElement();
      else if(action==='delete'){if(selectedProjectId!==null)deleteProject(selectedProjectId);else if(selectedNodeId)deleteSelectedNode();else if(selectedId)deleteBlock(Number(selectedId));}
      else if(action==='rename')renameSelectedElement();
      else if(action==='up')moveSelectedElement(-1);
      else if(action==='down')moveSelectedElement(1);
      else if(action==='hide')hideSelectedElement();
      else if(action==='reset-styles')resetSelectedStyles();
      else if(action==='parent')selectParentElement();
      else if(action==='child')selectFirstChildElement();
      else if(action==='copy-styles'){
        const block=blocks.find(item=>Number(item.id)===Number(selectedId));
        const record=block&&selectedNodeId?findNestedNode(block,selectedNodeId):null;
        const style=activeProject()?{visual_variant:activeProject().visual_variant}:record?(record.node.styles||{}):block?(block.settings||{}):null;
        if(style)canvasClipboard={kind:'styles',data:cloneData(style)};
      } else if(action==='paste-styles'&&canvasClipboard&&canvasClipboard.kind==='styles'){
        const block=blocks.find(item=>Number(item.id)===Number(selectedId));
        if(activeProject()){Object.assign(activeProject(),canvasClipboard.data);scheduleProjectSave(activeProject(),projectFormPayloadForProject(activeProject()));}
        else if(block&&selectedNodeId){const r=findNestedNode(block,selectedNodeId);if(r)r.node.styles=cloneData(canvasClipboard.data);}
        else if(block)block.settings=cloneData(canvasClipboard.data);
        if(block)scheduleSave(block,{label:block.label,content:block.content||{},settings:block.settings||{},is_visible:!!block.is_visible});
        renderCanvas();renderInspector();postPreviewState();
      }
    });
    document.addEventListener('pointerdown',event=>{if(!canvasContextMenu.hidden&&!canvasContextMenu.contains(event.target))closeCanvasContextMenu();});
    document.addEventListener('keydown',event=>{if(!canvasContextMenu.hidden&&event.key==='Escape')closeCanvasContextMenu();});
  }


  $('#builderRedo')?.addEventListener('click',redoEdit);
  document.addEventListener('keydown',event => {
    const tag=event.target&&event.target.tagName?event.target.tagName.toLowerCase():'';
    const typing=['input','textarea','select'].includes(tag)||(event.target&&event.target.isContentEditable);
    const key=String(event.key||'').toLowerCase();
    if((event.ctrlKey||event.metaKey)&&!typing){
      if(key==='z'){event.preventDefault();if(event.shiftKey)redoEdit();else undoEdit();return;}
      if(key==='y'){event.preventDefault();redoEdit();return;}
      if(key==='c'){event.preventDefault();copySelectedElement();return;}
      if(key==='v'){event.preventDefault();pasteSelectedElement();return;}
      if(key==='d'){event.preventDefault();duplicateSelectedElement();return;}
    }
    if(!typing&&(key==='delete'||key==='backspace')){
      if(selectedId!==null||selectedProjectId!==null){event.preventDefault();if(selectedProjectId!==null)deleteProject(selectedProjectId);else if(selectedNodeId)deleteSelectedNode();else deleteBlock(Number(selectedId));}
    }
    if(!typing&&key==='f2'){event.preventDefault();renameSelectedElement();}
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
  const acceptedPreviewMessages = new Set(['milad-builder-ready','milad-builder-select','milad-builder-node-select','milad-builder-project-select','milad-builder-context-menu','milad-builder-direct-resize','milad-builder-inline-edit']);
  window.addEventListener('message',event => {
    if (!frame || event.source !== frame.contentWindow || (event.origin !== window.location.origin && event.origin !== 'null')) return;
    const message = event.data;
    if (!message || typeof message !== 'object' || Array.isArray(message) || typeof message.type !== 'string' || !acceptedPreviewMessages.has(message.type)) return;
    if (message.type === 'milad-builder-ready') {
      previewReady = true;
      postPreviewState();
    } else if (message.type === 'milad-builder-select') {
      if (typeof message.key !== 'string' || message.key.length > 90) return;
      const block = blocks.find(item => blockKey(item) === message.key);
      if (block) selectBlock(Number(block.id));
    } else if (message.type === 'milad-builder-node-select') {
      if (typeof message.key !== 'string' || message.key.length > 90) return;
      if (message.nodeId !== undefined && (typeof message.nodeId !== 'string' || message.nodeId.length > 120)) return;
      if (message.legacyField !== undefined && (typeof message.legacyField !== 'string' || message.legacyField.length > 80)) return;
      const block = blocks.find(item => blockKey(item) === message.key);
      if (!block) return;
      if (message.legacyField) selectLegacyElement(Number(block.id),message.legacyField);
      else if (message.nodeId) selectNestedNode(Number(block.id),message.nodeId);
      else selectBlock(Number(block.id));
    } else if (message.type === 'milad-builder-project-select') {
      const projectId = Number(message.projectId);
      if (!Number.isSafeInteger(projectId) || projectId < 1 || !projects.some(item => Number(item.id) === projectId)) return;
      selectProject(projectId);
    } else if (message.type === 'milad-builder-context-menu') {
      const x = Number(message.x), y = Number(message.y);
      if (!Number.isFinite(x) || !Number.isFinite(y) || x < 0 || y < 0 || x > 10000 || y > 10000) return;
      if (message.projectId !== undefined) {
        const projectId = Number(message.projectId);
        if (!Number.isSafeInteger(projectId) || !projects.some(item => Number(item.id) === projectId)) return;
        selectProject(projectId);
        openCanvasContextMenu({projectId:projectId,x:x,y:y});
      } else {
        if (typeof message.key !== 'string' || message.key.length > 90) return;
        const block = blocks.find(item => blockKey(item) === message.key);
        if (!block || (message.nodeId && (typeof message.nodeId !== 'string' || message.nodeId.length > 120))) return;
        if (message.nodeId) selectNestedNode(Number(block.id),message.nodeId); else selectBlock(Number(block.id));
        openCanvasContextMenu({blockId:Number(block.id),nodeId:message.nodeId || '',x:x,y:y});
      }
    } else if (message.type === 'milad-builder-direct-resize') {
      applyDirectResize(message);
    } else if (message.type === 'milad-builder-inline-edit') {
      if (typeof message.key !== 'string' || message.key.length > 90 || typeof message.field !== 'string' || message.field.length > 80 || typeof message.value !== 'string' || message.value.length > 5000) return;
      const block = blocks.find(item => blockKey(item) === message.key);
      const field = message.field;
      const allowed = block && (fields[block.block_type] || []).some(([key]) => key === field);
      if (!allowed) return;
      block.content = Object.assign({},block.content || {});
      block.content[field] = message.value;
      const form = $('#builderLegacyForm') || $('#builderBlockForm');
      if (form && form.elements[field]) form.elements[field].value = block.content[field];
      renderCanvas();
      scheduleSave(block,{label:form && form.elements.label ? form.elements.label.value : block.label,content:block.content,settings:block.settings || {},is_visible:!!block.is_visible});
    }
  });

  function applyDirectResize(message) {
    const block = blocks.find(item => blockKey(item) === String(message.key || ''));
    if (!block || !message || typeof message !== 'object') return;
    const rawWidth = Number(message.widthPercent);
    const rawHeight = Number(message.height);
    if (!Number.isFinite(rawWidth) || !Number.isFinite(rawHeight) || rawWidth < 1 || rawWidth > 100 || rawHeight < 0 || rawHeight > 4000) return;
    const width = rawWidth.toFixed(2) + '%';
    const height = Math.round(rawHeight);
    const record = message.nodeId ? findNestedNode(block,String(message.nodeId)) : null;
    if (message.nodeId && !record) return;
    queueUndo(block);
    if (message.nodeId) {
      record.node.styles = Object.assign({},record.node.styles || {},{width:width});
      if (height) record.node.styles.min_height = height + 'px';
      selectedNodeId = String(message.nodeId);
      selectedLegacyField = null;
    } else {
      block.settings = Object.assign({},block.settings || {},{width:width});
      if (height) block.settings.min_height = height + 'px';
      selectedNodeId = null;
      selectedLegacyField = null;
    }
    renderCanvas();
    renderInspector();
    postPreviewState();
    scheduleSave(block,{label:block.label,content:block.content,settings:block.settings || {},is_visible:!!block.is_visible});
    status('اندازه عنصر مستقیماً روی بوم تغییر کرد؛ ذخیره خودکار انجام می‌شود.','');
  }

  $('#builderLayerSearch')?.addEventListener('input',event => {
    const term = String(event.target.value || '').trim().toLowerCase();
    $('.builder-block').forEach(item => {
      const text = String(item.textContent || '').toLowerCase();
      item.hidden = term !== '' && text.indexOf(term) === -1;
    });
    $('.builder-project-layer').forEach(item => {
      const text = String(item.textContent || '').toLowerCase();
      item.hidden = term !== '' && text.indexOf(term) === -1;
    });
  });
  $('#builderLayerExpandAll')?.addEventListener('click',() => {
    $('.builder-navigator details').forEach(node => node.open = true);
  });
  $('#builderLayerCollapseAll')?.addEventListener('click',() => {
    $('.builder-navigator details').forEach(node => node.open = false);
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