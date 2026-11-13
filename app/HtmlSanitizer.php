<?php
declare(strict_types=1);
final class HtmlSanitizer
{
    private const TAGS = ['p','br','h2','h3','h4','strong','b','em','i','u','ul','ol','li','blockquote','pre','code','hr','a'];
    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') return '';
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?><div id="cms-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$loaded) {
            libxml_clear_errors(); libxml_use_internal_errors($previous);
            return nl2br(htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
        $root = $dom->getElementById('cms-root');
        if (!$root) { libxml_clear_errors(); libxml_use_internal_errors($previous); return ''; }
        self::sanitizeChildren($root);
        $result = '';
        foreach ($root->childNodes as $child) $result .= $dom->saveHTML($child);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        return $result;
    }
    private static function sanitizeChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;
            if ($node instanceof DOMComment) { $parent->removeChild($node); $node = $next; continue; }
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (!in_array($tag, self::TAGS, true)) {
                    if (in_array($tag, ['script','style','iframe','object','embed','svg','math','form'], true)) {
                        $parent->removeChild($node);
                    } else {
                        self::sanitizeChildren($node);
                        while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                        $parent->removeChild($node);
                    }
                    $node = $next; continue;
                }
                $allowed = $tag === 'a' ? ['href','title'] : [];
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    if (!in_array(strtolower($attribute->name), $allowed, true)) $node->removeAttribute($attribute->name);
                }
                if ($tag === 'a') {
                    $href = trim($node->getAttribute('href'));
                    if ($href !== '' && !preg_match('~^(https?://|mailto:|/|#)~i', $href)) $node->removeAttribute('href');
                    if (preg_match('~^https?://~i', $href)) {
                        $node->setAttribute('rel', 'nofollow noopener noreferrer');
                        $node->setAttribute('target', '_blank');
                    }
                }
                self::sanitizeChildren($node);
            }
            $node = $next;
        }
    }
}
