<?php
declare(strict_types=1);

/** Owner or (administrator, when the owner shared an employee note) can edit and delete a note. */
function note_can_edit(array $n, array $u): bool
{
    if ((int)$n['user_id'] === (int)$u['id']) return true;
    return $u['role'] === 'admin' && (int)$n['admin_access'] === 1;
}
/** Can the user read the note? Adds read-only access for employees an administrator shared a note with. */
function note_can_access(array $n, array $u): bool
{
    if (note_can_edit($n, $u)) return true;
    if ($u['role'] === 'admin') return false;
    if (!q_val('SELECT COUNT(*) FROM users WHERE id=? AND role="admin"', [$n['user_id']])) return false;
    return (int)$n['share_all'] === 1 || (bool)q_val('SELECT COUNT(*) FROM note_shares WHERE note_id=? AND user_id=?', [$n['id'], $u['id']]);
}

const NOTE_FONTS = ['Arial', 'Helvetica', 'Times New Roman', 'Georgia', 'Verdana', 'Courier New', 'Trebuchet MS', 'Tahoma', 'Comic Sans MS', 'Garamond', 'Palatino Linotype', 'Impact'];

/** Returns a safe value for one allowed CSS property, or null. */
function note_css_value(string $prop, string $val): ?string
{
    $val = trim($val);
    $color = '/^(#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}\s*(,\s*\d{1,3}\s*){2}(,\s*[\d.]+\s*)?\)|[a-z]{3,20})$/i';
    switch ($prop) {
        case 'color': case 'background-color': return preg_match($color, $val) ? $val : null;
        case 'font-size': return preg_match('/^\d{1,3}(\.\d+)?(px|pt|em|rem|%)$/i', $val) ? $val : null;
        case 'font-weight': return preg_match('/^(normal|bold|[1-9]00)$/i', $val) ? $val : null;
        case 'font-style': return preg_match('/^(normal|italic)$/i', $val) ? $val : null;
        case 'text-decoration': case 'text-decoration-line': return preg_match('/^(none|underline|line-through|underline line-through|line-through underline)$/i', $val) ? $val : null;
        case 'font-family':
            $first = trim(explode(',', $val)[0], " \t'\"");
            foreach (NOTE_FONTS as $f) if (strcasecmp($f, $first) === 0) return "'$f'";
            return null;
    }
    return null;
}
function note_clean_style(string $style): string
{
    $out = [];
    foreach (explode(';', $style) as $decl) {
        if (!str_contains($decl, ':')) continue;
        [$p, $v] = explode(':', $decl, 2);
        $p = strtolower(trim($p));
        $ok = note_css_value($p, $v);
        if ($ok !== null) $out[] = "$p:$ok";
    }
    return implode(';', $out);
}
function note_clean_node(DOMNode $node): void
{
    static $allowed = ['p', 'div', 'br', 'span', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'blockquote', 'font', 'mark'];
    static $drop = ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'head', 'title', 'base', 'img', 'video', 'audio'];
    foreach (iterator_to_array($node->childNodes, false) as $c) {
        if ($c instanceof DOMText) continue;
        if (!($c instanceof DOMElement)) { $node->removeChild($c); continue; }
        $tag = strtolower($c->tagName);
        if (in_array($tag, $drop, true)) { $node->removeChild($c); continue; }
        note_clean_node($c);
        if (!in_array($tag, $allowed, true)) {
            while ($c->firstChild) $node->insertBefore($c->firstChild, $c);
            $node->removeChild($c);
            continue;
        }
        foreach (iterator_to_array($c->attributes, false) as $a) {
            $n = strtolower($a->name); $v = $a->value;
            if ($n === 'style') { $s = note_clean_style($v); if ($s === '') $c->removeAttribute($a->name); else $c->setAttribute('style', $s); }
            elseif ($tag === 'font' && $n === 'color' && note_css_value('color', $v) !== null) continue;
            elseif ($tag === 'font' && $n === 'size' && preg_match('/^[1-7]$/', $v)) continue;
            elseif ($tag === 'font' && $n === 'face' && note_css_value('font-family', $v) !== null) continue;
            else $c->removeAttribute($a->name);
        }
    }
}
/** Whitelist sanitiser for the notebook's rich text (formatting only; no scripts, links, images or event handlers). */
function note_sanitize(string $html): string
{
    $html = trim($html);
    if ($html === '') return '';
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="note-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = (new DOMXPath($doc))->query('//div[@id="note-root"]')->item(0);
    if (!$root) return '';
    note_clean_node($root);
    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return trim($out);
}
/** HTML to show/edit for a stored body (older notes were plain text). */
function note_body_html(string $b): string
{
    return preg_match('/<(p|div|br|span|b|i|u|s|font|ul|ol|li|h[1-3]|strong|em)\b/i', $b) ? note_sanitize($b) : nl2br(e($b));
}
function note_plain(string $b): string
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/li|\/h[1-3])[^>]*>/i', ' ', $b)), ENT_QUOTES, 'UTF-8')));
}
