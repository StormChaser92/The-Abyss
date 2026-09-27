<?php
/* the-abyss/includes/formatuj.php
   rp_format()        — tekst gracza → bezpieczny HTML: najpierw escapowanie, potem
                        *narracja*, **pogrubienie**, _kursywa_, @wzmianki i nowe linie.
   html_bezpieczny()  — HTML z systemu (rzuty, wiadomości systemowe czatu, powiadomienia)
                        przepuszczony przez białą listę tagów i atrybutów. Skrypty, iframe,
                        on*=, javascript: i url() w stylach wylatują. */

if (!function_exists('rp_format')) {

function rp_format(string $t): string {
    $s = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/\*\*(.+?)\*\*/su', '<b>$1</b>', $s);
    $s = preg_replace('/\*(.+?)\*/su', '<span style="color:var(--txt-mute);font-style:italic">*$1*</span>', $s);
    $s = preg_replace('/(?<![\p{L}\p{N}_])_(?=\S)(.+?)(?<=\S)_(?![\p{L}\p{N}_])/su', '<i>$1</i>', $s);
    $s = preg_replace('/@([a-zA-Z0-9_ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]+)/u', '<span class="wspomnienie">@$1</span>', $s);
    return nl2br($s);
}

function html_bezpieczny(string $html): string {
    if ($html === '' || strpbrk($html, '<&') === false) return $html;
    if (!class_exists('DOMDocument')) return strip_tags($html, '<b><i><u><em><strong><small><br><span><div><a><sub><sup><p><hr>');
    $d = new DOMDocument();
    $stare = libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="utf-8"?><div id="hb-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); libxml_use_internal_errors($stare);
    $root = (new DOMXPath($d))->query('//div[@id="hb-root"]')->item(0);
    if (!$root) return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    hb_czysc($root);
    $out = '';
    foreach ($root->childNodes as $n) $out .= $d->saveHTML($n);
    return $out;
}

function hb_czysc(DOMNode $el): void {
    static $TAGI = ['b','i','u','em','strong','small','br','span','div','a','sub','sup','p','hr','ul','ol','li'];
    static $WYTNIJ = ['script','style','iframe','object','embed','form','input','button','textarea','select','link','meta','svg','math','template','noscript','base','frame','frameset'];
    foreach (iterator_to_array($el->childNodes) as $n) {
        if ($n->nodeType === XML_COMMENT_NODE || $n->nodeType === XML_PI_NODE) { $el->removeChild($n); continue; }
        if ($n->nodeType !== XML_ELEMENT_NODE) continue;
        $tag = strtolower($n->nodeName);
        if (in_array($tag, $WYTNIJ, true)) { $el->removeChild($n); continue; }
        hb_czysc($n);
        if (!in_array($tag, $TAGI, true)) {                   // nieznany tag: zostaje treść
            while ($n->firstChild) $el->insertBefore($n->firstChild, $n);
            $el->removeChild($n);
            continue;
        }
        foreach (iterator_to_array($n->attributes) as $a) {
            $nazwa = strtolower($a->nodeName); $w = trim($a->nodeValue);
            $ok = in_array($nazwa, ['class', 'title'], true)
               || ($nazwa === 'style' && !preg_match('/url\s*\(|expression|javascript|behavior|@import|-moz-binding/i', $w))
               || ($tag === 'a' && $nazwa === 'href' && preg_match('#^(game\.php|https?://|/|\#)#i', $w))
               || ($tag === 'a' && $nazwa === 'target' && in_array($w, ['_blank', '_self'], true));
            if (!$ok) $n->removeAttribute($a->nodeName);
        }
        if ($tag === 'a' && $n->getAttribute('target') === '_blank') $n->setAttribute('rel', 'noopener');
    }
}

}
