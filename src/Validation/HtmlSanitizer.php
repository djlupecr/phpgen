<?php
namespace PhpGen\Validation;

/**
 * HtmlSanitizer — Remove tags perigosas e atributos que permitem XSS.
 * Whitelist de tags permitidas: p, br, strong, em, u, s, a, ul, ol, li, h1-h6, blockquote, code, pre, img, table, thead, tbody, tr, th, td, span, div.
 */
class HtmlSanitizer
{
    private static array $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a',
        'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'code', 'pre', 'img', 'table', 'thead',
        'tbody', 'tr', 'th', 'td', 'span', 'div', 'hr',
    ];

    private static array $allowedAttrs = [
        'a'   => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'width', 'height', 'title'],
        'td'  => ['colspan', 'rowspan'],
        'th'  => ['colspan', 'rowspan'],
    ];

    public static function sanitize(string $html): string
    {
        if (trim($html) === '') return '';

        // 1. Remove tags <script>, <style>, <iframe>, <object>, <embed> E SEU CONTEÚDO
        $html = preg_replace('/<(script|style|iframe|object|embed|form|input|button)[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<(script|style|iframe|object|embed|form|input|button)[^>]*\/?>/is', '', $html);

        // 2. Remove comentários HTML (que podem esconder scripts)
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // 3. Remove tags que não estão na whitelist (mantém o texto interno)
        $html = strip_tags($html, '<' . implode('><', self::$allowedTags) . '>');

        // 4. Remove atributos perigosos (onclick, onerror, javascript:, etc.)
        $html = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);
        $html = preg_replace('/data\s*:/i', '', $html);

        // 5. Remove atributos que não estão na whitelist
        $html = preg_replace_callback('/<(\w+)([^>]*)>/i', function ($m) {
            $tag = strtolower($m[1]);
            $attrs = $m[2];
            if (!in_array($tag, self::$allowedTags, true)) return '';
            $allowed = self::$allowedAttrs[$tag] ?? [];
            $attrs = preg_replace_callback('/(\w+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/', function ($a) use ($allowed) {
                return in_array(strtolower($a[1]), $allowed, true) ? " {$a[1]}={$a[2]}" : '';
            }, $attrs);
            return "<{$tag}{$attrs}>";
        }, $html);

        return trim($html);
    }
}