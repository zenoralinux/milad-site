<?php
declare(strict_types=1);

function builder_home_page_id(): int
{
    return (int) db()->query("SELECT id FROM pages WHERE slug='home' LIMIT 1")->fetchColumn();
}

function builder_home_blocks(): array
{
    $pageId = builder_home_page_id();
    if ($pageId < 1) return [];
    $stmt = db()->prepare("SELECT id,block_key,block_type,label,content_json,settings_json,is_visible,sort_order
        FROM page_blocks WHERE page_id=? ORDER BY sort_order ASC,id ASC");
    $stmt->execute([$pageId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $content = json_decode((string) $row['content_json'], true);
        $settings = json_decode((string) $row['settings_json'], true);
        $row['content'] = is_array($content) ? $content : [];
        $row['settings'] = is_array($settings) ? $settings : [];
        $row['is_visible'] = (bool) $row['is_visible'];
        unset($row['content_json'], $row['settings_json']);
    }
    return $rows;
}

function builder_design_settings(): array
{
    $rows = db()->query("SELECT setting_key,setting_value FROM design_settings")->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];
    foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
    return $settings;
}

function builder_valid_color($value, string $fallback): string
{
    $value = trim((string) $value);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
}

function builder_design_css(array $settings): string
{
    $accent = builder_valid_color($settings['accent_color'] ?? '', '#a58aff');
    $background = builder_valid_color($settings['page_background'] ?? '', '#080b12');
    $width = max(760, min(1600, (int) ($settings['content_width'] ?? 1160)));
    $radius = max(0, min(48, (int) ($settings['corner_radius'] ?? 22)));
    $scale = max(0.85, min(1.2, (float) ($settings['font_scale'] ?? 1)));
    $spacing = max(32, min(180, (int) ($settings['section_spacing'] ?? 72)));
    return '<style id="builder-design-tokens">:root{--accent:' . $accent . ';--accent-strong:' . $accent .
        ';--bg:' . $background . ';--container:' . $width . 'px;--radius-lg:' . $radius .
        'px;--radius-md:' . $radius . 'px;--radius-sm:' . max(0, (int) round($radius * 0.68)) .
        'px;--builder-font-scale:' . $scale . ';--builder-section-gap:' . $spacing . 'px}' .
        'body{font-size:calc(15px * var(--builder-font-scale,1));background-color:var(--bg)}' .
        '.section-shell{scroll-margin-top:110px}' .
        '.work-section,.about-section,.now-section,.contact-section{padding-block:var(--builder-section-gap,72px)}' .
        '.builder-custom-block .panel-card{padding:clamp(20px,4vw,44px);border:1px solid var(--line);border-radius:var(--radius-lg)}' .
        '.builder-custom-block h2{margin:0 0 12px;font-size:clamp(24px,4vw,42px)}' .
        '.builder-custom-block p{color:var(--muted);line-height:2.1}' .
        '</style>';
}

function builder_extract_section(string &$html, string $marker): string
{
    $start = strpos($html, $marker);
    if ($start === false) return '';
    $tail = substr($html, $start);
    if (!preg_match_all('/<section\b[^>]*>|<\/section>/i', $tail, $matches, PREG_OFFSET_CAPTURE)) return '';
    $depth = 0;
    $end = null;
    foreach ($matches[0] as $match) {
        if (stripos($match[0], '</section') === 0) {
            $depth--;
            if ($depth === 0) {
                $end = $match[1] + strlen($match[0]);
                break;
            }
        } else {
            $depth++;
        }
    }
    if ($end === null) return '';
    $fragment = substr($html, $start, $end);
    $html = substr($html, 0, $start) . substr($html, $start + $end);
    return $fragment;
}

function builder_extract_footer(string &$html): string
{
    $start = strpos($html, '<footer class="site-footer">');
    if ($start === false) return '';
    $endTag = '</footer>';
    $end = strpos($html, $endTag, $start);
    if ($end === false) return '';
    $end += strlen($endTag);
    $fragment = substr($html, $start, $end - $start);
    $html = substr($html, 0, $start) . substr($html, $end);
    return $fragment;
}

function builder_block_style(array $settings): string
{
    $styles = [];
    if (isset($settings['background']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $settings['background'])) {
        $styles[] = 'background-color:' . $settings['background'];
    }
    if (isset($settings['text_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $settings['text_color'])) {
        $styles[] = 'color:' . $settings['text_color'];
    }
    if (isset($settings['padding'])) $styles[] = 'padding-block:' . max(0, min(180, (int) $settings['padding'])) . 'px';
    if (isset($settings['radius'])) $styles[] = 'border-radius:' . max(0, min(48, (int) $settings['radius'])) . 'px';
    return $styles ? ' style="' . h(implode(';', $styles)) . '"' : '';
}

function builder_render_custom_block(array $block): string
{
    $type = (string) ($block['block_type'] ?? '');
    $content = is_array($block['content'] ?? null) ? $block['content'] : [];
    $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
    $style = builder_block_style($settings);
    $title = h($content['title'] ?? '');
    $body = nl2br(h($content['body'] ?? ''));
    $key = h($block['block_key'] ?? '');
    $id = 'block-' . preg_replace('/[^a-z0-9_-]/i', '', (string) ($block['block_key'] ?? ''));

    if ($type === 'text') {
        return '<section class="section-shell builder-custom-block builder-text-block" id="' . $id . '" data-builder-key="' . $key . '" data-builder-id="' . (int) $block['id'] . '"' . $style .
            '><div class="container"><div class="section-heading"><h2>' . $title . '</h2><p>' . $body . '</p></div></div></section>';
    }
    if ($type === 'cta') {
        $buttonText = h($content['button_text'] ?? 'تماس با من');
        $url = (string) ($content['button_url'] ?? '#contact');
        $safeUrl = '#contact';
        if (preg_match('/^#[a-zA-Z0-9_-]+$/', $url)) $safeUrl = $url;
        elseif (strpos($url, '/') === 0 && strpos($url, '//') !== 0) $safeUrl = app_url($url);
        elseif (preg_match('#^https://[a-z0-9.-]+(?::[0-9]{1,5})?(?:/.*)?$#i', $url)) $safeUrl = $url;
        return '<section class="section-shell builder-custom-block builder-cta-block" data-builder-key="' . $key . '" data-builder-id="' . (int) $block['id'] . '"' . $style .
            '><div class="container"><div class="panel-card"><h2>' . $title . '</h2><p>' . $body .
            '</p><a class="button button-primary" href="' . h($safeUrl) . '">' . $buttonText . '</a></div></div></section>';
    }
    if ($type === 'stats') {
        $items = isset($content['items']) && is_array($content['items']) ? $content['items'] : [];
        $cards = '';
        foreach (array_slice($items, 0, 6) as $item) {
            if (!is_array($item)) continue;
            $cards .= '<article class="principle-card"><span class="principle-icon">' . h($item['value'] ?? '') .
                '</span><h3>' . h($item['label'] ?? '') . '</h3><p>' . h($item['description'] ?? '') . '</p></article>';
        }
        return '<section class="section-shell builder-custom-block builder-stats-block" data-builder-key="' . $key . '" data-builder-id="' . (int) $block['id'] . '"' . $style .
            '><div class="container">' . ($title !== '' ? '<h2>' . $title . '</h2>' : '') .
            '<div class="principles-grid">' . $cards . '</div></div></section>';
    }
    if ($type === 'spacer') {
        $height = max(8, min(240, (int) ($content['height'] ?? 48)));
        return '<div class="builder-spacer" data-builder-key="' . $key . '" data-builder-id="' . (int) $block['id'] . '" aria-hidden="true" style="height:' . $height . 'px"></div>';
    }
    return '';
}

function builder_apply_fragment_attributes(string $fragment, array $block): string
{
    $key = h($block['block_key'] ?? '');
    $id = (int) ($block['id'] ?? 0);
    $style = builder_block_style(is_array($block['settings'] ?? null) ? $block['settings'] : []);
    return preg_replace_callback('/^<([a-z][a-z0-9:-]*)(\b[^>]*)>/i', static function (array $match) use ($key, $id, $style): string {
        return '<' . $match[1] . $match[2] . ' data-builder-key="' . $key . '" data-builder-id="' . $id . '"' . $style . '>';
    }, $fragment, 1) ?? $fragment;
}

function builder_apply_to_html(string $html, array $blocks, array $design): string
{
    $markers = [
        'hero' => '<section class="hero section-shell" id="home">',
        'intro' => '<section class="intro-strip">',
        'projects' => '<section class="work-section section-shell" id="work">',
        'about' => '<section class="about-section section-shell" id="about">',
        'now' => '<section class="now-section section-shell" id="now">',
        'contact' => '<section class="contact-section section-shell" id="contact">'
    ];
    $fragments = [];
    foreach ($markers as $key => $marker) $fragments[$key] = builder_extract_section($html, $marker);
    $footerFragment = builder_extract_footer($html);

    $footerBlock = null;
    $rendered = '';
    foreach ($blocks as $block) {
        $type = (string) ($block['block_type'] ?? '');
        if ($type === 'footer') {
            $footerBlock = $block;
            continue;
        }
        if (empty($block['is_visible'])) continue;
        $fragment = $fragments[$type] ?? '';
        if ($fragment !== '') {
            $rendered .= builder_apply_fragment_attributes($fragment, $block) . "\n";
        } else {
            $rendered .= builder_render_custom_block($block) . "\n";
        }
    }

    $main = strpos($html, '<main id="main">');
    if ($main !== false) {
        $contentStart = strpos($html, '>', $main) + 1;
        $contentEnd = strrpos($html, '</main>');
        if ($contentStart > 0 && $contentEnd !== false && $contentEnd >= $contentStart) {
            $html = substr($html, 0, $contentStart) . "\n" . $rendered . "\n" . substr($html, $contentEnd);
        }
    }

    if ($footerFragment !== '' && ($footerBlock === null || !empty($footerBlock['is_visible']))) {
        $footerHtml = $footerBlock === null ? $footerFragment : builder_apply_fragment_attributes($footerFragment, $footerBlock);
        $bodyEnd = stripos($html, '</body>');
        if ($bodyEnd !== false) $html = substr($html, 0, $bodyEnd) . $footerHtml . "\n" . substr($html, $bodyEnd);
    }

    $css = builder_design_css($design);
    $headEnd = stripos($html, '</head>');
    if ($headEnd !== false) $html = substr($html, 0, $headEnd) . $css . substr($html, $headEnd);
    return $html;
}

function builder_preview_script(): string
{
    return <<<'HTML'
<style id="builder-live-preview-style">
html[data-builder-preview="1"] [data-builder-key]{cursor:pointer;outline:1px dashed transparent;outline-offset:3px;transition:outline-color .12s,box-shadow .12s}
html[data-builder-preview="1"] [data-builder-key]:hover{outline-color:rgba(139,120,255,.9);box-shadow:0 0 0 4px rgba(139,120,255,.08)}
html[data-builder-preview="1"] [data-builder-key].builder-live-selected{outline:2px solid #9c83ff!important;outline-offset:3px!important;box-shadow:0 0 0 5px rgba(156,131,255,.13)!important}
html[data-builder-preview="1"] body{padding-top:0!important}
</style>
<script>
(function(){
  'use strict';
  if (window.parent === window) return;
  document.documentElement.dataset.builderPreview = '1';
  var selectedKey = null;
  function safeHref(value){
    var raw = String(value || '');
    if (/^#[a-zA-Z0-9_-]+$/.test(raw)) return raw;
    if (raw.charAt(0) === '/' && raw.charAt(1) !== '/') return raw;
    try { var u = new URL(raw, location.href); return u.protocol === 'https:' ? raw : '#'; } catch(e){ return '#'; }
  }
  function setText(root, selector, value){
    var node = root.querySelector(selector);
    if (node && value !== undefined && value !== null) node.textContent = String(value);
  }
  function setSplit(root, selector, first, second){
    var node = root.querySelector(selector);
    if (!node) return;
    node.replaceChildren(document.createTextNode(String(first || '')), document.createElement('br'));
    var span = document.createElement('span');
    span.className = 'type-gradient';
    span.textContent = String(second || '');
    node.appendChild(span);
  }
  function card(tag, className, value){
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (value !== undefined) node.textContent = String(value);
    return node;
  }
  function makeCustom(block){
    var c = block.content || {}, node, container, title, body;
    if (block.block_type === 'spacer') {
      node = card('div','builder-spacer');
      node.setAttribute('aria-hidden','true');
      return node;
    }
    node = card('section','section-shell builder-custom-block builder-' + block.block_type + '-block');
    container = card('div','container');
    title = card('h2','',c.title || '');
    body = card('p','',c.body || '');
    if (block.block_type === 'text') {
      var heading = card('div','section-heading');
      heading.append(title,body); container.appendChild(heading);
    } else if (block.block_type === 'cta') {
      var panel = card('div','panel-card');
      var link = card('a','button button-primary',c.button_text || 'تماس با من');
      link.href = safeHref(c.button_url || '#contact');
      panel.append(title,body,link); container.appendChild(panel);
    } else if (block.block_type === 'stats') {
      var grid = card('div','principles-grid');
      (Array.isArray(c.items) ? c.items : []).slice(0,6).forEach(function(item){
        if (!item || typeof item !== 'object') return;
        var tile = card('article','principle-card');
        tile.append(card('span','principle-icon',item.value || ''),card('h3','',item.label || ''),card('p','',item.description || ''));
        grid.appendChild(tile);
      });
      if (c.title) container.appendChild(title);
      container.appendChild(grid);
    }
    node.appendChild(container);
    return node;
  }
  function updateContent(node, block){
    var c = block.content || {};
    var type = block.block_type;
    if (type === 'hero') {
      setText(node,'.hero-copy .eyebrow',c.eyebrow);
      setSplit(node,'.hero-copy h1',c.title_line1,c.title_line2);
      setText(node,'.hero-description',c.description);
      var links = node.querySelectorAll('.hero-actions a');
      if (links[0]) { links[0].textContent = String(c.primary_cta_text || ''); links[0].href = safeHref(c.primary_cta_url || '#work'); }
      if (links[1]) { links[1].textContent = String(c.secondary_cta_text || ''); links[1].href = safeHref(c.secondary_cta_url || '#contact'); }
      setText(node,'.hero-footnote span:last-child',c.footnote);
    } else if (type === 'intro') {
      var p = node.querySelector('.intro-strip-inner p');
      if (p) {
        p.replaceChildren(document.createTextNode(String(c.text_before || '') + ' '));
        p.appendChild(card('span','',c.text_emphasis || ''));
      }
    } else if (type === 'projects') {
      var heading = node.querySelector('.section-heading h2');
      if (heading) {
        heading.replaceChildren(document.createTextNode(String(c.heading_before || '') + ' '));
        heading.appendChild(card('span','type-gradient',c.heading_emphasis || ''));
      }
      setText(node,'.section-heading > p',c.description);
    } else if (type === 'about') {
      setText(node,'.about-heading .section-index',c.section_index);
      setSplit(node,'.about-heading h2',c.title_line1,c.title_line2);
      setText(node,'.about-heading > p',c.description);
      setText(node,'.about-heading .text-link',c.link_text);
      var aboutLink = node.querySelector('.about-heading .text-link');
      if (aboutLink && c.link_url) aboutLink.href = safeHref(c.link_url);
      var grid = node.querySelector('.principles-grid');
      if (grid && Array.isArray(c.principles_items)) {
        grid.replaceChildren();
        c.principles_items.slice(0,12).forEach(function(item){
          if (!item || typeof item !== 'object') return;
          var tile = card('article','principle-card reveal');
          tile.append(card('span','principle-icon',item.number || ''),card('h3','',item.title || ''),card('p','',item.description || ''));
          grid.appendChild(tile);
        });
      }
      if (grid) grid.hidden = c.show_principles === false || c.show_principles === 0 || c.show_principles === '0';
    } else if (type === 'now') {
      setText(node,'.now-panel-copy .section-index',c.section_index);
      setSplit(node,'.now-panel-copy h2',c.title_line1,c.title_line2);
      setText(node,'.now-panel-copy > p',c.description);
      setText(node,'.now-note strong',c.status_title);
      setText(node,'.now-note small',c.status_subtitle);
    } else if (type === 'contact') {
      setText(node,'.contact-copy .section-index',c.section_index);
      setSplit(node,'.contact-copy h2',c.title_line1,c.title_line2);
      setText(node,'.contact-copy > p',c.description);
    } else if (type === 'footer') {
      setText(node,'.footer-brand > span:last-child',c.tagline);
      var copy = node.querySelector('.footer-copy');
      if (copy) copy.textContent = '© ' + new Date().getFullYear() + ' میلاد · ' + String(c.copyright || '');
    } else if (type === 'text' || type === 'cta' || type === 'stats') {
      var h = node.querySelector('h2');
      var p2 = node.querySelector('p');
      if (h) h.textContent = String(c.title || '');
      if (p2) p2.textContent = String(c.body || '');
      if (type === 'cta') {
        var a = node.querySelector('a.button');
        if (a) { a.textContent = String(c.button_text || 'تماس با من'); a.href = safeHref(c.button_url || '#contact'); }
      }
      if (type === 'stats') {
        var g = node.querySelector('.principles-grid');
        if (g) {
          g.replaceChildren();
          (Array.isArray(c.items) ? c.items : []).slice(0,6).forEach(function(item){
            if (!item || typeof item !== 'object') return;
            var tile = card('article','principle-card');
            tile.append(card('span','principle-icon',item.value || ''),card('h3','',item.label || ''),card('p','',item.description || ''));
            g.appendChild(tile);
          });
        }
      }
    } else if (type === 'spacer') {
      node.style.height = Math.max(8,Math.min(240,Number(c.height) || 48)) + 'px';
    }
  }
  function applyState(data){
    if (!data || !Array.isArray(data.blocks)) return;
    var main = document.querySelector('#main');
    if (!main) return;
    var footerNode = document.querySelector('footer.site-footer');
    var activeKeys = new Set(data.blocks.map(function(block){ return String(block.block_key); }));
    document.querySelectorAll('[data-builder-key]').forEach(function(node){
      if (!activeKeys.has(String(node.dataset.builderKey))) node.hidden = true;
    });
    data.blocks.forEach(function(block){
      if (block.block_type === 'footer') return;
      var node = Array.prototype.find.call(document.querySelectorAll('[data-builder-key]'),function(el){ return el.dataset.builderKey === block.block_key; });
      if (!node && ['text','cta','stats','spacer'].indexOf(block.block_type) !== -1) {
        node = makeCustom(block);
        node.dataset.builderKey = block.block_key;
      }
      if (!node) return;
      node.dataset.builderId = String(block.id);
      node.hidden = !block.is_visible;
      node.style.backgroundColor = block.settings && block.settings.background || '';
      node.style.color = block.settings && block.settings.text_color || '';
      node.style.paddingBlock = block.settings && block.settings.padding !== undefined ? Number(block.settings.padding) + 'px' : '';
      node.style.borderRadius = block.settings && block.settings.radius !== undefined ? Number(block.settings.radius) + 'px' : '';
      updateContent(node,block);
      main.appendChild(node);
    });
    var footerBlock = data.blocks.find(function(block){ return block.block_type === 'footer'; });
    if (footerNode && footerBlock) {
      footerNode.dataset.builderKey = footerBlock.block_key;
      footerNode.dataset.builderId = String(footerBlock.id);
      footerNode.hidden = !footerBlock.is_visible;
      updateContent(footerNode,footerBlock);
    } else if (footerNode) {
      footerNode.hidden = true;
    }
    var d = data.design || {};
    document.documentElement.style.setProperty('--accent',d.accent_color || '#a58aff');
    document.documentElement.style.setProperty('--accent-strong',d.accent_color || '#a58aff');
    document.documentElement.style.setProperty('--bg',d.page_background || '#080b12');
    document.documentElement.style.setProperty('--container',Math.max(760,Math.min(1600,Number(d.content_width) || 1160)) + 'px');
    document.documentElement.style.setProperty('--radius-lg',Math.max(0,Math.min(48,Number(d.corner_radius) || 0)) + 'px');
    document.documentElement.style.setProperty('--radius-md',Math.max(0,Math.min(48,Number(d.corner_radius) || 0)) + 'px');
    document.documentElement.style.setProperty('--builder-font-scale',d.font_scale || '1');
    document.documentElement.style.setProperty('--builder-section-gap',Math.max(32,Math.min(180,Number(d.section_spacing) || 72)) + 'px');
    if (footerNode && footerBlock) main.after(footerNode);
    if (selectedKey) selectOutline(selectedKey);
  }
  function selectOutline(key){
    selectedKey = key;
    document.querySelectorAll('.builder-live-selected').forEach(function(node){ node.classList.remove('builder-live-selected'); });
    var selected = Array.prototype.find.call(document.querySelectorAll('[data-builder-key]'),function(el){ return el.dataset.builderKey === key; });
    if (selected) selected.classList.add('builder-live-selected');
  }
  document.addEventListener('click',function(event){
    var target = event.target.closest('[data-builder-key]');
    if (!target) return;
    if (event.target.closest('a')) event.preventDefault();
    event.stopPropagation();
    window.parent.postMessage({type:'milad-builder-select',key:target.dataset.builderKey},location.origin);
  },true);
  window.addEventListener('message',function(event){
    if (event.origin !== location.origin || event.source !== window.parent) return;
    var message = event.data || {};
    if (message.type === 'milad-builder-state') applyState(message);
    if (message.type === 'milad-builder-select-outline') selectOutline(String(message.key || ''));
  });
  window.parent.postMessage({type:'milad-builder-ready'},location.origin);
})();
</script>
HTML;
}
