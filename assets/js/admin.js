(() => {
  'use strict';
  const body = document.body;
  if (body.dataset.mode === 'setup') return;
  const basePath = document.body.dataset.basePath || '';
  const appUrl = path => basePath + '/' + String(path).replace(/^\\/+/, '');
  const API = appUrl('api/admin.php');
  let csrf = body.dataset.csrf || '';
  let projects = [];
  let sections = [];
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const toast = (message, error = false) => {
    const region = $('#toastRegion');
    if (!region) return;
    const node = document.createElement('div');
    node.className = 'toast' + (error ? ' error' : '');
    node.textContent = message;
    region.append(node);
    window.setTimeout(() => node.remove(), 3600);
  };
  async function request(action, method = 'GET', data = null, id = null) {
    const params = new URLSearchParams({action});
    if (id !== null) params.set('id', String(id));
    const headers = {'Accept':'application/json'};
    if (method !== 'GET') {
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-Token'] = csrf;
    }
    const response = await fetch(API + '?' + params.toString(), {
      method, headers, credentials:'same-origin',
      body: method === 'GET' ? undefined : JSON.stringify(data ?? {})
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok === false) {
      if (result.error?.code === 'AUTH_REQUIRED') window.location.reload();
      throw new Error(result.error?.message || 'ارتباط با سرور ناموفق بود.');
    }
    return result;
  }

  const loginForm = $('#loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async event => {
      event.preventDefault();
      const submit = $('button[type="submit"]', loginForm);
      const error = $('#loginError');
      submit.disabled = true;
      error.textContent = '';
      const form = new FormData(loginForm);
      try {
        const result = await request('login', 'POST', {username:form.get('username'), password:form.get('password')});
        csrf = result.data.csrf || csrf;
        window.location.reload();
      } catch (e) {
        error.textContent = e.message;
        submit.disabled = false;
      }
    });
    return;
  }

  if (body.dataset.mode !== 'admin') return;
  const labels = {
    hero:'معرفی اصلی', intro:'نوار معرفی', projects:'معرفی پروژه‌ها',
    about:'درباره من', principles:'اصول کاری', now:'اکنون / تمرکز فعلی',
    contact:'راه‌های ارتباطی', footer:'پاورقی سایت'
  };
  const fieldLabels = {
    eyebrow:'برچسب بالای عنوان',title_line1:'خط اول عنوان',title_line2:'خط دوم عنوان',
    description:'توضیحات',primary_cta_text:'متن دکمه اصلی',primary_cta_url:'لینک دکمه اصلی',
    secondary_cta_text:'متن دکمه دوم',secondary_cta_url:'لینک دکمه دوم',footnote:'متن پایانی معرفی',
    text_before:'متن قبل از تأکید',text_emphasis:'متن تأکیدی',
    heading_before:'عنوان قبل از تأکید',heading_emphasis:'عنوان تأکیدی',
    section_index:'شناسه نمایشی بخش',link_text:'متن لینک',link_url:'آدرس لینک',
    items:'آیتم‌ها (JSON)',status_title:'عنوان وضعیت',status_subtitle:'زیرعنوان وضعیت',
    tagline:'متن پایانی',copyright:'متن کپی‌رایت'
  };
  const sectionPicker = $('#sectionPicker');
  const sectionEditor = $('#sectionEditor');
  let selectedSection = null;

  function setView(view) {
    $$('.admin-view').forEach(node => node.classList.toggle('is-active', node.id === 'view-' + view));
    $$('.side-link[data-view]').forEach(node => node.classList.toggle('is-active', node.dataset.view === view));
    const titles = {dashboard:'نمای کلی',content:'محتوای سایت',projects:'مدیریت پروژه‌ها',settings:'تنظیمات سایت',activity:'فعالیت‌های اخیر'};
    $('#pageTitle').textContent = titles[view] || 'مدیریت';
    $('#sidebar')?.classList.remove('is-open');
    if (view === 'content' && !sections.length) loadSections();
    if (view === 'projects') loadProjects();
    if (view === 'settings') loadSettings();
    if (view === 'activity') loadActivity(true);
  }
  $$('[data-view]').forEach(button => button.addEventListener('click', () => setView(button.dataset.view)));
  $$('[data-go]').forEach(button => button.addEventListener('click', () => setView(button.dataset.go)));
  $('#mobileMenu')?.addEventListener('click', () => $('#sidebar').classList.toggle('is-open'));

  async function loadDashboard() {
    try {
      const response = await request('dashboard');
      const data = response.data;
      $('#dashboardStats').innerHTML = [
        ['کل پروژه‌ها',data.total_projects,'ثبت‌شده در سایت'],
        ['منتشرشده',data.published_projects,'قابل مشاهده برای بازدیدکنندگان'],
        ['پیش‌نویس',data.draft_projects,'در انتظار انتشار']
      ].map(item => '<article class="stat-card"><small>' + item[0] + '</small><strong>' + Number(item[1]).toLocaleString('fa-IR') + '</strong><span class="stat-foot">' + item[2] + '</span></article>').join('');
      renderActivity(data.activity || [], $('#dashboardActivity'));
    } catch(e) { toast(e.message, true); }
  }

  function renderActivity(rows, target) {
    if (!target) return;
    if (!rows.length) { target.innerHTML = '<p class="muted">هنوز فعالیتی ثبت نشده است.</p>'; return; }
    target.innerHTML = rows.map(row => '<div class="activity-item"><i class="activity-dot"></i><span><strong>' + escapeHtml(row.summary || row.action) + '</strong><small>' + escapeHtml(row.entity_type || 'system') + ' · ' + escapeHtml(row.created_at || '') + '</small></span></div>').join('');
  }
  async function loadActivity(full = false) {
    try {
      const response = await request('activity');
      renderActivity(response.data, full ? $('#fullActivity') : $('#dashboardActivity'));
    } catch(e) { toast(e.message, true); }
  }

  function renderSectionPicker() {
    sectionPicker.innerHTML = sections.map(section => '<button class="section-option ' + (selectedSection === section.key ? 'is-active' : '') + '" data-section="' + escapeHtml(section.key) + '"><span>' + escapeHtml(labels[section.key] || section.key) + '</span><small>' + (section.is_visible ? 'نمایش' : 'مخفی') + '</small></button>').join('');
    $$('[data-section]', sectionPicker).forEach(button => button.addEventListener('click', () => {
      selectedSection = button.dataset.section;
      renderSectionPicker();
      renderSectionEditor(sections.find(item => item.key === selectedSection));
    }));
  }
  function renderSectionEditor(section) {
    if (!section) return;
    const entries = Object.entries(section.content || {});
    let fields = '';
    entries.forEach(([key,value]) => {
      const json = typeof value === 'object' && value !== null;
      const text = json ? JSON.stringify(value, null, 2) : String(value ?? '');
      const label = fieldLabels[key] || key;
      const area = json || text.length > 95 || ['description','footnote'].includes(key);
      if (area) {
        fields += '<label>' + escapeHtml(label) + '<textarea class="section-field" data-key="' + escapeHtml(key) + '" data-json="' + (json ? '1':'0') + '">' + escapeHtml(text) + '</textarea></label>';
      } else {
        fields += '<label>' + escapeHtml(label) + '<input class="section-field" data-key="' + escapeHtml(key) + '" value="' + escapeHtml(text) + '"></label>';
      }
    });
    sectionEditor.innerHTML = '<div class="editor-title"><div><span class="section-kicker">SECTION EDITOR</span><h3>' + escapeHtml(labels[section.key] || section.key) + '</h3></div><label class="visibility-toggle"><input type="checkbox" id="sectionVisible" ' + (section.is_visible ? 'checked' : '') + '> نمایش در سایت</label></div><form id="sectionForm"><div class="field-grid">' + fields + '</div><div class="editor-actions"><span class="form-hint">آخرین ذخیره: ' + escapeHtml(section.updated_at || '—') + '</span><button class="primary-button compact" type="submit">ذخیره تغییرات <b>↙</b></button></div></form>';
    $('#sectionForm').addEventListener('submit', async event => {
      event.preventDefault();
      const content = {};
      try {
        $$('.section-field', event.currentTarget).forEach(field => {
          content[field.dataset.key] = field.dataset.json === '1' ? JSON.parse(field.value) : field.value;
        });
      } catch(e) { toast('ساختار JSON معتبر نیست.', true); return; }
      try {
        await request('section-save','PUT',{key:section.key,content,is_visible:$('#sectionVisible').checked});
        toast('بخش ذخیره شد.');
        await loadSections(section.key);
      } catch(e) { toast(e.message,true); }
    });
  }
  async function loadSections(selectKey = null) {
    try {
      const response = await request('sections');
      sections = response.data;
      selectedSection = selectKey || selectedSection || (sections[0] && sections[0].key);
      renderSectionPicker();
      renderSectionEditor(sections.find(item => item.key === selectedSection));
    } catch(e) { toast(e.message,true); }
  }

  const statusNames = {active:'فعال',in_progress:'در حال توسعه',paused:'متوقف‌شده',project:'پروژه',archived:'بایگانی‌شده'};
  const categoryNames = {os:'سیستم‌عامل',apps:'اپلیکیشن',tools:'ابزار'};
  function renderProjects() {
    const query = ($('#projectSearch')?.value || '').trim().toLocaleLowerCase('fa');
    const filter = $('#projectFilter')?.value || 'all';
    const filtered = projects.filter(p => {
      const matchText = !query || [p.title,p.slug,p.excerpt,p.kind_label].join(' ').toLocaleLowerCase('fa').includes(query);
      const matchFilter = filter === 'all' || (filter === 'published' && p.is_published) || (filter === 'draft' && !p.is_published) || p.category === filter;
      return matchText && matchFilter;
    });
    $('#projectCount').textContent = filtered.length.toLocaleString('fa-IR') + ' پروژه';
    if (!filtered.length) { $('#projectList').innerHTML = '<div class="empty-state"><b>▦</b><h3>پروژه‌ای پیدا نشد</h3><p>فیلتر را تغییر بده یا پروژه جدید بساز.</p></div>'; return; }
    $('#projectList').innerHTML = filtered.map(p => {
      return '<article class="project-admin-row" data-id="' + p.id + '"><div><h3>' + escapeHtml(p.title) + '</h3><p>' + escapeHtml(p.kind_label) + ' · ' + escapeHtml(p.slug) + '</p><div class="project-meta"><span class="mini-tag">' + escapeHtml(categoryNames[p.category] || p.category) + '</span><span class="mini-tag">' + escapeHtml(statusNames[p.status] || p.status) + '</span>' + (p.is_featured ? '<span class="mini-tag">ویژه</span>' : '') + '</div></div><span class="status-pill ' + (p.is_published ? '' : 'draft') + '">' + (p.is_published ? 'منتشرشده' : 'پیش‌نویس') + '</span><div class="row-actions"><button title="بالا" data-move="-1" data-id="' + p.id + '">↑</button><button title="پایین" data-move="1" data-id="' + p.id + '">↓</button><button title="ویرایش" data-edit="' + p.id + '">✎</button><button class="delete-button" title="حذف" data-delete="' + p.id + '">×</button></div></article>';
    }).join('');
    $$('[data-edit]').forEach(button => button.addEventListener('click', () => openProjectModal(projects.find(p => p.id == button.dataset.edit))));
    $$('[data-delete]').forEach(button => button.addEventListener('click', () => deleteProject(Number(button.dataset.delete))));
    $$('[data-move]').forEach(button => button.addEventListener('click', () => moveProject(Number(button.dataset.id), Number(button.dataset.move))));
  }
  async function loadProjects() {
    try {
      const response = await request('projects');
      projects = response.data;
      renderProjects();
    } catch(e) { toast(e.message,true); }
  }
  $('#projectSearch')?.addEventListener('input',renderProjects);
  $('#projectFilter')?.addEventListener('change',renderProjects);

  const modal = $('#projectModal');
  function openProjectModal(project = null) {
    const form = $('#projectForm');
    form.reset();
    form.elements.id.value = project?.id || '';
    form.elements.title.value = project?.title || '';
    form.elements.slug.value = project?.slug || '';
    form.elements.category.value = project?.category || 'tools';
    form.elements.kind_label.value = project?.kind_label || '';
    form.elements.excerpt.value = project?.excerpt || '';
    form.elements.description.value = project?.description || '';
    form.elements.status.value = project?.status || 'active';
    form.elements.visual_variant.value = project?.visual_variant || 'generic';
    form.elements.sort_order.value = project?.sort_order ?? (projects.length + 1) * 10;
    form.elements.technologies.value = (project?.technologies || []).join(', ');
    form.elements.links.value = JSON.stringify(project?.links || [], null, 2);
    form.elements.is_featured.checked = !!project?.is_featured;
    form.elements.is_published.checked = project ? !!project.is_published : true;
    $('#projectModalTitle').textContent = project ? 'ویرایش پروژه' : 'پروژه جدید';
    $('#projectFormHint').textContent = '';
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    form.elements.title.focus();
  }
  function closeProjectModal() {
    modal.hidden = true;
    document.body.style.overflow = '';
  }
  $('#newProjectButton')?.addEventListener('click',() => openProjectModal());
  $$('[data-close-modal]').forEach(button => button.addEventListener('click',closeProjectModal));
  modal?.addEventListener('click',event => { if (event.target === modal) closeProjectModal(); });
  $('#projectForm')?.addEventListener('submit',async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const id = form.elements.id.value;
    let links;
    try { links = JSON.parse(form.elements.links.value || '[]'); }
    catch { toast('ساختار JSON لینک‌ها معتبر نیست.',true); return; }
    const payload = {
      title:form.elements.title.value,slug:form.elements.slug.value,category:form.elements.category.value,
      kind_label:form.elements.kind_label.value,excerpt:form.elements.excerpt.value,description:form.elements.description.value,
      status:form.elements.status.value,visual_variant:form.elements.visual_variant.value,sort_order:Number(form.elements.sort_order.value),
      technologies:form.elements.technologies.value.split(/[,\n،]/).map(s=>s.trim()).filter(Boolean),
      links,is_featured:form.elements.is_featured.checked,is_published:form.elements.is_published.checked
    };
    const button = $('button[type="submit"]',form);
    button.disabled = true;
    try {
      await request(id ? 'project-save' : 'projects',id ? 'PUT' : 'POST',payload,id || null);
      closeProjectModal();
      toast(id ? 'پروژه به‌روزرسانی شد.' : 'پروژه ایجاد شد.');
      await loadProjects();
      await loadDashboard();
    } catch(e) { $('#projectFormHint').textContent = e.message; toast(e.message,true); }
    finally { button.disabled = false; }
  });
  async function deleteProject(id) {
    const project = projects.find(p => Number(p.id) === id);
    if (!project || !window.confirm('پروژه «' + project.title + '» حذف شود؟ این عملیات قابل بازگشت نیست.')) return;
    try { await request('project-delete','DELETE',{},id); toast('پروژه حذف شد.'); await loadProjects(); await loadDashboard(); }
    catch(e) { toast(e.message,true); }
  }
  async function moveProject(id, direction) {
    const index = projects.findIndex(p => Number(p.id) === id);
    const next = index + direction;
    if (index < 0 || next < 0 || next >= projects.length) return;
    [projects[index],projects[next]] = [projects[next],projects[index]];
    renderProjects();
    try { await request('project-reorder','POST',{ids:projects.map(p=>p.id)}); toast('ترتیب ذخیره شد.'); }
    catch(e) { toast(e.message,true); await loadProjects(); }
  }

  const settingLabels = {
    site_title:'عنوان سایت',site_description:'توضیحات SEO',canonical_url:'آدرس Canonical',
    og_image_url:'تصویر اشتراک‌گذاری (OG)',github_url:'لینک GitHub',telegram_url:'لینک Telegram',
    avatar_path:'مسیر تصویر پروفایل',theme_color:'رنگ نوار مرورگر'
  };
  async function loadSettings() {
    try {
      const response = await request('settings');
      const data = response.data;
      $('#settingsFields').innerHTML = Object.entries(settingLabels).map(([key,label]) => {
        const value = data[key] || '';
        const long = key === 'site_description';
        return '<label class="' + (long ? 'field-span-2':'') + '">' + label + (long ? '<textarea name="' + key + '" rows="3">' + escapeHtml(value) + '</textarea>' : '<input name="' + key + '" value="' + escapeHtml(value) + '" ' + (key.includes('url') ? 'dir="ltr"':'') + '>') + '</label>';
      }).join('');
    } catch(e) { toast(e.message,true); }
  }
  $('#settingsForm')?.addEventListener('submit',async event => {
    event.preventDefault();
    const payload = {};
    $$('input,textarea',event.currentTarget).forEach(input => payload[input.name] = input.value);
    try { await request('settings','PUT',payload); toast('تنظیمات ذخیره شد.'); }
    catch(e) { toast(e.message,true); }
  });

  $('#logoutButton')?.addEventListener('click',async () => {
    if (!window.confirm('از پنل مدیریت خارج می‌شوی؟')) return;
    try { await request('logout','POST',{}); window.location.href=appUrl('admin'); }
    catch(e) { toast(e.message,true); }
  });

  loadDashboard();
})();
