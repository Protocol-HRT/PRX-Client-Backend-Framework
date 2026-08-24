<?php

namespace App\Cms\Support;

/**
 * Normalizes rich-editor output into the HTML shape a field kind promises.
 *
 * The CMS exposes two kinds of authored copy, and they differ in what the
 * consuming frontend is allowed to receive:
 *
 * - **inline** — the frontend picks the wrapping element itself (an <h1> for
 *   one section, an <h2> or a card's <h3> for another). The stored value must
 *   therefore be inline HTML only. A block node here nests <h2> inside <h1>,
 *   which is invalid and silently corrupts the document outline that SEO
 *   depends on.
 * - **prose** — the frontend renders the value into a container of its own,
 *   so headings, lists and paragraphs are all legitimate.
 *
 * Both normalize on save rather than trusting the toolbar, because hiding a
 * button does not remove the capability. Filament's editor registers TipTap's
 * Heading extension with levels 1-6 unconditionally and binds Mod-Alt-1..6, so
 * a paste from Word or a stray keyboard shortcut introduces a block node into
 * a field whose toolbar shows no heading button.
 *
 * Trust model is unchanged: this is permission-gated admin content, not user
 * input, and these methods normalize structure — they are not a sanitizer.
 */
final class HtmlCopy
{
    /** Inline tags an inline-kind field may keep. */
    private const INLINE_TAGS = '<b><strong><i><em><u><s><a><br><span><sup><sub><small><code><mark>';

    /** Block tags whose close is a line boundary worth preserving as a break. */
    private const BLOCK_BOUNDARY = '#</(p|div|h[1-6]|li|blockquote|pre|tr|figcaption)>#i';

    /**
     * Flattens a value to inline HTML, preserving line structure as <br />.
     *
     * Pasted paragraphs keep their breaks rather than running together, and a
     * value that carries no text at all collapses to null so the section's
     * own empty-content guard can drop it.
     */
    public static function inline(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $value = preg_replace('#<br\s*/?>#i', '<br />', $html);
        $value = preg_replace(self::BLOCK_BOUNDARY, '<br />', $value);
        $value = strip_tags($value, self::INLINE_TAGS);

        // Blank lines in the editor arrive as empty blocks, so collapse the
        // runs of breaks they leave behind and trim the ends.
        $value = preg_replace('#(\s*<br />\s*){2,}#', '<br />', $value);
        $value = preg_replace('#^(\s*<br />)+#', '', $value);
        $value = preg_replace('#(<br />\s*)+$#', '', $value);
        $value = trim($value);

        return self::isBlank($value) ? null : $value;
    }

    /**
     * Keeps block structure, but drops the empty paragraphs the editor emits
     * for every blank line — a few stray keystrokes otherwise leave a run of
     * <p></p> that renders as dead vertical space on the public page.
     */
    public static function prose(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $value = preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $html);
        $value = trim($value);

        return self::isBlank($value) ? null : $value;
    }

    /** True when the value carries markup but no readable text. */
    private static function isBlank(string $value): bool
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // \xC2\xA0 is a UTF-8 non-breaking space, which trim() does not strip.
        return trim(str_replace("\xC2\xA0", ' ', $text)) === '';
    }
}
