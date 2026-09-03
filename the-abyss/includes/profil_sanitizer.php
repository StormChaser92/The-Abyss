<?php
/**
 * The Abyss — bezpieczne wyświetlanie bogatej historii profilu.
 * Pozwala na kolory, fonty, formatowanie i obrazki z lokalnego uploadu,
 * ale usuwa skrypty, eventy JS, iframy, formularze i niebezpieczne CSS.
 */

if (!function_exists('abyss_table_exists')) {
    function abyss_table_exists(mysqli $polaczenie, string $table): bool {
        $safe = $polaczenie->real_escape_string($table);
        $res = $polaczenie->query("SHOW TABLES LIKE '$safe'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('abyss_safe_url')) {
    function abyss_safe_url(string $url): bool {
        $url = trim($url);
        if ($url === '') return false;
        if (preg_match('/^\s*javascript:/i', $url)) return false;
        if (preg_match('/^\s*data:/i', $url)) return false;
        if (preg_match('/^\s*vbscript:/i', $url)) return false;

        // Do historii profilu dopuszczamy lokalne obrazki z uploads/ oraz zwykłe http/https.
        if (preg_match('#^uploads/#i', $url)) return true;
        if (preg_match('#^https?://#i', $url)) return true;
        if (preg_match('#^/#', $url)) return true;
        return false;
    }
}

if (!function_exists('abyss_sanitize_style')) {
    function abyss_sanitize_style(string $style): string {
        $allowed = [
            'color', 'background-color', 'font-family', 'font-size', 'font-weight', 'font-style',
            'text-decoration', 'text-align', 'line-height', 'letter-spacing', 'text-transform',
            'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
            'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
            'border', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-radius',
            'width', 'max-width', 'min-width', 'height', 'max-height',
            'display', 'float', 'clear', 'opacity', 'box-shadow'
        ];

        $clean = [];
        foreach (explode(';', $style) as $chunk) {
            if (strpos($chunk, ':') === false) continue;
            [$prop, $value] = array_map('trim', explode(':', $chunk, 2));
            $prop_l = strtolower($prop);
            $value_l = strtolower($value);

            if (!in_array($prop_l, $allowed, true)) continue;
            if (preg_match('/expression\s*\(|url\s*\(|javascript\s*:|vbscript\s*:|behavior\s*:/i', $value)) continue;
            if (preg_match('/position\s*:|z-index\s*:|fixed|absolute/i', $chunk)) continue;

            // display tylko w bezpiecznych wariantach
            if ($prop_l === 'display' && !in_array($value_l, ['block','inline','inline-block','flex','grid','none'], true)) continue;
            $clean[] = $prop_l . ':' . $value;
        }
        return implode(';', $clean);
    }
}

if (!function_exists('abyss_sanitize_profile_html')) {
    function abyss_sanitize_profile_html(?string $html): string {
        $html = (string)$html;
        if (trim($html) === '') return '';

        $allowed_tags = [
            'p','br','div','span','b','strong','i','em','u','s','strike','small','big',
            'h1','h2','h3','h4','h5','h6','blockquote','pre','code',
            'ul','ol','li','hr','a','img','figure','figcaption','font','center',
            'table','thead','tbody','tr','td','th'
        ];
        $allowed_attrs = [
            'style','class','title','alt','src','href','target','rel','width','height','align',
            'face','color','size','colspan','rowspan'
        ];

        if (!class_exists('DOMDocument')) {
            $allowed = '<p><br><div><span><b><strong><i><em><u><s><strike><small><big><h1><h2><h3><h4><h5><h6><blockquote><pre><code><ul><ol><li><hr><a><img><figure><figcaption><font><center><table><thead><tbody><tr><td><th>';
            return strip_tags($html, $allowed);
        }

        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="abyss-root">' . $html . '</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $clean_node = function($node) use (&$clean_node, $doc, $allowed_tags, $allowed_attrs) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($node->nodeName);
                if ($tag === 'script' || $tag === 'iframe' || $tag === 'object' || $tag === 'embed' || $tag === 'form' || $tag === 'input' || $tag === 'button' || $tag === 'textarea' || $tag === 'select' || $tag === 'video' || $tag === 'audio') {
                    $node->parentNode?->removeChild($node);
                    return;
                }

                if (!in_array($tag, $allowed_tags, true) && $tag !== 'div') {
                    $frag = $doc->createDocumentFragment();
                    while ($node->firstChild) $frag->appendChild($node->firstChild);
                    $node->parentNode?->replaceChild($frag, $node);
                    return;
                }

                if ($node->hasAttributes()) {
                    $attrs_to_remove = [];
                    foreach ($node->attributes as $attr) {
                        $name = strtolower($attr->name);
                        $value = trim($attr->value);

                        if (str_starts_with($name, 'on') || !in_array($name, $allowed_attrs, true)) {
                            $attrs_to_remove[] = $attr->name;
                            continue;
                        }

                        if ($name === 'style') {
                            $safe_style = abyss_sanitize_style($value);
                            if ($safe_style === '') $attrs_to_remove[] = $attr->name;
                            else $node->setAttribute('style', $safe_style);
                            continue;
                        }

                        if (($name === 'src' || $name === 'href') && !abyss_safe_url($value)) {
                            $attrs_to_remove[] = $attr->name;
                            continue;
                        }

                        if ($name === 'target' && $value !== '_blank') {
                            $attrs_to_remove[] = $attr->name;
                            continue;
                        }
                    }
                    foreach ($attrs_to_remove as $attr_name) $node->removeAttribute($attr_name);

                    if ($tag === 'a' && $node->hasAttribute('target') && $node->getAttribute('target') === '_blank') {
                        $node->setAttribute('rel', 'noopener noreferrer');
                    }
                }
            }

            if ($node->hasChildNodes()) {
                // Kopia listy, bo w trakcie sprzątania drzewo może się zmieniać.
                $children = [];
                foreach ($node->childNodes as $child) $children[] = $child;
                foreach ($children as $child) {
                    if ($child->parentNode) $clean_node($child);
                }
            }
        };

        $root = $doc->getElementById('abyss-root');
        if (!$root) return '';
        $clean_node($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }
}
