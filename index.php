<?php
declare(strict_types=1);

// Keep the original static homepage available if the PHP configuration/bootstrap
// cannot be loaded (for example, a malformed local config.php on shared hosting).
try {
    require __DIR__ . '/app/bootstrap.php';
} catch (Throwable $e) {
    error_log('Homepage bootstrap failed: ' . $e->getMessage());
    http_response_code(200);
    include __DIR__ . '/index.html';
    exit;
}

try {
    $pdo = db();
    $settings = get_site_settings();
    $sections = get_site_sections();
    $projects = get_projects(true);
} catch (Throwable $e) {
    // Preserve the static homepage and leave a useful server-side diagnostic.
    error_log('Homepage data load failed: ' . $e->getMessage());
    include __DIR__ . '/index.html';
    exit;
}

function public_setting(string $key, string $fallback = ''): string
{
    global $settings;
    return (string) ($settings[$key] ?? $fallback);
}
function public_section(string $key): array
{
    global $sections;
    return $sections[$key]['content'] ?? [];
}
function public_text(string $section, string $field, string $fallback = ''): string
{
    $content = public_section($section);
    return (string) ($content[$field] ?? $fallback);
}
function replace_inner(string $html, string $start, string $end, string $replacement): string
{
    $a = strpos($html, $start);
    if ($a === false) return $html;
    $a += strlen($start);
    $b = strpos($html, $end, $a);
    if ($b === false) return $html;
    return substr($html, 0, $a) . $replacement . substr($html, $b);
}
function remove_named_section(string $html, string $start): string
{
    $a = strpos($html, $start);
    if ($a === false) return $html;
    $b = strpos($html, '</section>', $a);
    if ($b === false) return $html;
    return substr($html, 0, $a) . substr($html, $b + strlen('</section>'));
}
function remove_named_element(string $html, string $start, string $end): string
{
    $a = strpos($html, $start);
    if ($a === false) return $html;
    $b = strpos($html, $end, $a);
    if ($b === false) return $html;
    return substr($html, 0, $a) . substr($html, $b + strlen($end));
}
function fa_digits(int $number): string
{
    return strtr((string) $number, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
}
function public_href(string $url): string
{
    $url = trim($url);
    if (preg_match('/^#[a-zA-Z0-9_-]+$/', $url)) return $url;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return app_url($url);
    if (str_starts_with($url, './') && !str_contains($url, '..')) return $url;
    $parts = parse_url($url);
    if (is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https' && !empty($parts['host'])) return $url;
    return '#';
}
function render_project_card(array $project, int $number): string
{
    $categoryLabels = ['os' => 'سیستم‌عامل', 'apps' => 'اپلیکیشن', 'tools' => 'ابزارها'];
    $statusLabels = ['active' => 'فعال', 'in_progress' => 'در توسعه', 'paused' => 'متوقف', 'project' => 'پروژه', 'archived' => 'بایگانی'];
    $category = (string) ($project['category'] ?? 'tools');
    $variant = (string) ($project['visual_variant'] ?? 'generic');
    $featured = !empty($project['is_featured']);
    $search = trim(implode(' ', [
        $project['title'] ?? '', $project['slug'] ?? '', $project['excerpt'] ?? '',
        $project['description'] ?? '', $project['kind_label'] ?? '',
        implode(' ', $project['technologies'] ?? [])
    ]));
    $html = '<article class="project-card' . ($featured ? ' project-card--featured' : '') . ' reveal" data-category="' . h($category) . '" data-search="' . h($search) . '">';
    $html .= '<div class="project-card-top"><span class="project-number">' . sprintf('%02d', $number) . '</span><span class="project-kind">' . h($project['kind_label']) . '</span></div>';
    $html .= '<div class="project-visual project-visual--' . h($variant) . '">' . project_visual_markup($variant) . '</div>';
    $html .= '<div class="project-card-content"><div class="project-title-row"><h3>' . h($project['title']) . '</h3><span class="project-status"><i></i>' . h($statusLabels[$project['status']] ?? 'پروژه') . '</span></div>';
    $html .= '<p>' . h($project['excerpt']) . '</p><div class="tag-list">';
    foreach ($project['technologies'] as $technology) $html .= '<span>' . h($technology) . '</span>';
    $html .= '</div><div class="project-actions">';
    foreach ($project['links'] as $link) {
        $url = public_href((string) $link['url']);
        $external = str_starts_with($url, 'https://');
        $html .= '<a href="' . h($url) . '"' . ($external ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . h($link['label']) . ' <span>↗</span></a>';
    }
    $html .= '</div></div></article>';
    return $html;
}

$siteTitle = public_setting('site_title', 'میلاد | توسعه‌دهنده مستقل و سازنده محصولات دیجیتال');
$siteDescription = public_setting('site_description', 'فضای دیجیتال میلاد؛ توسعه‌دهنده مستقل، علاقه‌مند به نرم‌افزار متن‌باز و سازنده پروژه‌هایی مانند Zenora Linux و ZenoraChat.');
$canonical = public_href(public_setting('canonical_url', app_absolute_url('/')));
$ogImage = public_href(public_setting('og_image_url', app_absolute_url('og-image.svg')));
$avatar = public_href(public_setting('avatar_path', './milad_avatar.jpg'));
$github = public_href(public_setting('github_url', 'https://github.com/miladalizadw'));
$telegram = public_href(public_setting('telegram_url', 'https://t.me/miladalizadw'));
$html = file_get_contents(__DIR__ . '/index.html');
if ($html === false) { http_response_code(500); exit('Homepage template unavailable.'); }

$html = str_replace('<title>میلاد | توسعه‌دهنده مستقل و سازنده محصولات دیجیتال</title>', '<title>' . h($siteTitle) . '</title>', $html);
$html = preg_replace('/<meta name="description" content="[^"]*">/', '<meta name="description" content="' . h($siteDescription) . '">', $html, 1);
$html = preg_replace('/<link rel="canonical" href="[^"]*">/', '<link rel="canonical" href="' . h($canonical) . '">', $html, 1);
$html = preg_replace('/<meta property="og:title" content="[^"]*">/', '<meta property="og:title" content="' . h($siteTitle) . '">', $html, 1);
$html = preg_replace('/<meta property="og:description" content="[^"]*">/', '<meta property="og:description" content="' . h($siteDescription) . '">', $html, 1);
$html = preg_replace('/<meta property="og:url" content="[^"]*">/', '<meta property="og:url" content="' . h($canonical) . '">', $html, 1);
$html = preg_replace('/<meta property="og:image" content="[^"]*">/', '<meta property="og:image" content="' . h($ogImage) . '">', $html, 1);
$html = preg_replace('/<meta name="twitter:title" content="[^"]*">/', '<meta name="twitter:title" content="' . h($siteTitle) . '">', $html, 1);
$html = preg_replace('/<meta name="twitter:description" content="[^"]*">/', '<meta name="twitter:description" content="' . h($siteDescription) . '">', $html, 1);
$html = preg_replace('/<meta name="theme-color" content="[^"]*">/', '<meta name="theme-color" content="' . h(public_setting('theme_color', '#080b12')) . '">', $html, 1);
$html = str_replace('"url": "https://miladalizadw.ir/"', '"url": "' . h($canonical) . '"', $html);
$html = str_replace('"image": "https://miladalizadw.ir/milad_avatar.jpg"', '"image": "' . h($avatar) . '"', $html);
$html = str_replace('https://github.com/miladalizadw', h($github), $html);
$html = str_replace('https://t.me/miladalizadw', h($telegram), $html);
$html = str_replace('./milad_avatar.jpg', h($avatar), $html);

$hero = public_section('hero');
$html = str_replace('توسعه‌دهنده مستقل · سازنده محصولات دیجیتال</div>', h($hero['eyebrow'] ?? 'توسعه‌دهنده مستقل · سازنده محصولات دیجیتال') . '</div>', $html);
$html = replace_inner($html, '<h1>', '</h1>', h($hero['title_line1'] ?? 'ایده‌ها را') . '<br><span class="type-gradient">' . h($hero['title_line2'] ?? 'به تجربه تبدیل می‌کنم.') . '</span>');
$html = replace_inner($html, '<p class="hero-description">', '</p>', "\n            " . h($hero['description'] ?? '') . "\n          ");
$html = str_replace('href="#work">دیدن پروژه‌ها <span aria-hidden="true">↙</span>', 'href="' . h(public_href((string) ($hero['primary_cta_url'] ?? '#work'))) . '">' . h($hero['primary_cta_text'] ?? 'دیدن پروژه‌ها') . ' <span aria-hidden="true">↙</span>', $html);
$html = str_replace('href="#contact">ارتباط با من</a>', 'href="' . h(public_href((string) ($hero['secondary_cta_url'] ?? '#contact'))) . '">' . h($hero['secondary_cta_text'] ?? 'ارتباط با من') . '</a>', $html);
$html = str_replace('ساختن مستقل، یادگیری مداوم، بهبود بی‌وقفه</span>', h($hero['footnote'] ?? 'ساختن مستقل، یادگیری مداوم، بهبود بی‌وقفه') . '</span>', $html);

$intro = public_section('intro');
$html = replace_inner($html, '<p>', '</p>', h($intro['text_before'] ?? 'تمرکز من روی ساخت محصولاتی است که') . ' <span>' . h($intro['text_emphasis'] ?? 'واقعاً به کار می‌آیند.') . '</span>');
$projectIntro = public_section('projects');
$html = str_replace('چیزهایی که <span class="type-gradient">می‌سازم.</span>', h($projectIntro['heading_before'] ?? 'چیزهایی که') . ' <span class="type-gradient">' . h($projectIntro['heading_emphasis'] ?? 'می‌سازم.') . '</span>', $html);
$html = str_replace('مجموعه‌ای از پروژه‌ها و تجربه‌هایی که بخشی از مسیر من در توسعه نرم‌افزار هستند.', h($projectIntro['description'] ?? 'مجموعه‌ای از پروژه‌ها و تجربه‌هایی که بخشی از مسیر من در توسعه نرم‌افزار هستند.'), $html);
$projectStart = '        <div class="projects-grid" id="projectsGrid">';
$projectEnd = '        <p class="empty-filter" id="emptyFilter"';
$cards = '<div class="projects-grid" id="projectsGrid">';
foreach ($projects as $index => $project) $cards .= render_project_card($project, $index + 1);
$cards .= "</div>\n";
$html = replace_inner($html, $projectStart, $projectEnd, substr($cards, strlen($projectStart)));

$about = public_section('about');
$html = str_replace('02 — A LITTLE ABOUT ME', h($about['section_index'] ?? '02 — A LITTLE ABOUT ME'), $html);
$html = str_replace('پشت هر محصول،<br><span class="type-gradient">یک طرز فکر است.</span>', h($about['title_line1'] ?? 'پشت هر محصول،') . '<br><span class="type-gradient">' . h($about['title_line2'] ?? 'یک طرز فکر است.') . '</span>', $html);
$html = str_replace('برای من توسعه نرم‌افزار فقط کنار هم گذاشتن کدها نیست؛ فرایند فهم مسئله، طراحی راه‌حل و بهتر کردن تجربه آدم‌هاست.', h($about['description'] ?? ''), $html);
$html = str_replace('بیشتر در گیت‌هاب', h($about['link_text'] ?? 'بیشتر در گیت‌هاب'), $html);
$principles = public_section('principles');
$principleHtml = '<div class="principles-grid">';
foreach (($principles['items'] ?? []) as $item) {
    $principleHtml .= '<article class="principle-card reveal"><span class="principle-icon">' . h($item['number'] ?? '') . '</span><h3>' . h($item['title'] ?? '') . '</h3><p>' . h($item['description'] ?? '') . '</p></article>';
}
$principleHtml .= "</div>\n";
$html = replace_inner($html, '<div class="principles-grid">', '      </div>' . "\n    </section>\n\n    <section class=\"now-section", substr($principleHtml, strlen('<div class="principles-grid">')) . '      ');

$now = public_section('now');
$html = str_replace('03 — NOW / CURRENT FOCUS', h($now['section_index'] ?? '03 — NOW / CURRENT FOCUS'), $html);
$html = str_replace('همیشه چیزی<br><span class="type-gradient">در حال ساخته‌شدن است.</span>', h($now['title_line1'] ?? 'همیشه چیزی') . '<br><span class="type-gradient">' . h($now['title_line2'] ?? 'در حال ساخته‌شدن است.') . '</span>', $html);
$html = str_replace('این روزها تمرکزم روی توسعه و بهبود پروژه‌های Zenora، به‌خصوص تجربه ZenoraChat و ابزارهای مبتنی بر لینوکس است. این بخش تصویری از مسیر فعلی من است، نه یک فهرست ثابت از دستاوردها.', h($now['description'] ?? ''), $html);
$html = str_replace('<strong>در حال ساختن</strong><small>Build · Learn · Improve</small>', '<strong>' . h($now['status_title'] ?? 'در حال ساختن') . '</strong><small>' . h($now['status_subtitle'] ?? 'Build · Learn · Improve') . '</small>', $html);

$contact = public_section('contact');
$html = str_replace('04 — LET\'S CONNECT', h($contact['section_index'] ?? '04 — LET\'S CONNECT'), $html);
$html = str_replace('ایده‌ای داری؟<br><span class="type-gradient">گفت‌وگو کنیم.</span>', h($contact['title_line1'] ?? 'ایده‌ای داری؟') . '<br><span class="type-gradient">' . h($contact['title_line2'] ?? 'گفت‌وگو کنیم.') . '</span>', $html);
$html = str_replace('برای دیدن کدها، دنبال‌کردن پروژه‌ها یا شروع یک گفت‌وگوی فنی، از این مسیرها در دسترسم.', h($contact['description'] ?? ''), $html);

$footer = public_section('footer');
$html = str_replace('ساخته‌شده با دقت و علاقه.', h($footer['tagline'] ?? 'ساخته‌شده با دقت و علاقه.'), $html);
$html = str_replace('تمام حقوق محفوظ است.', h($footer['copyright'] ?? 'تمام حقوق محفوظ است.'), $html);

// Block visibility is owned by the visual builder after its migration; keep source fragments intact for live toggling.
$projectCount = count($projects);
$html = str_replace('۰۵ پروژه', h(fa_digits($projectCount)) . ' پروژه', $html);
// Prefix root-relative links from the static source with the detected installation path.
// This keeps links working when the project lives in /portfolio, /site/v2, or the domain root.
$mountPath = app_base_path();
$html = preg_replace_callback(
    '~\b(href|src|action)=(["\'])/(?!/)([^"\']*)\2~i',
    static function (array $match) use ($mountPath): string {
        $path = '/' . $match[3];
        if ($mountPath !== '' && ($path === $mountPath || str_starts_with($path, $mountPath . '/'))) {
            return $match[0];
        }
        return $match[1] . '=' . $match[2] . h(app_url($path)) . $match[2];
    },
    $html
) ?? $html;

require_once __DIR__ . '/app/page_builder.php';
$builderBlocks = builder_home_blocks();
$builderDesign = builder_design_settings();
$isBuilderPreview = isset($_GET['builder_preview']) && (string) $_GET['builder_preview'] === '1';
$html = builder_apply_to_html($html, $builderBlocks, $builderDesign, $isBuilderPreview);

if (isset($_GET['builder_preview']) && (string) $_GET['builder_preview'] === '1') {
    start_admin_session();
    if (!current_admin()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Preview access denied.');
    }
    $previewAssets = builder_preview_script();
    $bodyEnd = stripos($html, '</body>');
    if ($bodyEnd !== false) {
        $html = substr($html, 0, $bodyEnd) . $previewAssets . substr($html, $bodyEnd);
    }
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
}

echo $html;
