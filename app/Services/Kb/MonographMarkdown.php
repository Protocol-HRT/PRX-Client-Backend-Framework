<?php

namespace App\Services\Kb;

use App\Cms\Support\HtmlCopy;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Converts the seed monographs from markdown to the prose HTML this CMS
 * stores everywhere else.
 *
 * Why convert at import rather than at render: the source is markdown — every
 * one of the 106 rows uses `##` headings, 105 use `**bold**` and bulleted
 * lists, and 86 carry pipe tables of dosing titration. Storing it raw would
 * mean the admin's rich editor shows an operator literal `##` and `**`, and
 * the public page renders them as text. Converting once, on the way in, makes
 * the imported rows indistinguishable from hand-authored prose fields for
 * every consumer downstream.
 *
 * The output tag set is the one `CopyFields::prose()` promises — p / lists /
 * blockquote / strong / em / a — plus tables, which the seed needs and which
 * Filament's RichEditor has a tool for. Headings come out one level lower than
 * the markdown asked for; see `toHtml()` for why.
 *
 * Two things it deliberately does NOT do: it introduces no facts, and it moves
 * no content between fields. Restructuring the prose into typed cards is the
 * next phase's job and belongs behind the review gate, not in a converter.
 */
class MonographMarkdown
{
    private ?MarkdownConverter $converter = null;

    /**
     * Markdown in, prose HTML out. Null and blank pass straight through so a
     * missing section stays missing rather than becoming an empty paragraph.
     */
    public function toHtml(?string $markdown): ?string
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        $html = (string) $this->converter()->convert($markdown);

        // Every heading drops one level.
        //
        // The public page gives each monograph field its own <h2> ("Dosing",
        // "Safety and side effects") — those are the page's structure and the
        // targets its contents list links to. The source's own `##` headings
        // sit INSIDE one of those fields, so emitting them as <h2> would make
        // them siblings of the section that contains them and flatten the
        // document outline the whole page is being written to earn.
        //
        // h1/h2 become h3; anything deeper collapses to h4, which is as far as
        // the prose styles go. Exactly one seed row uses `####`.
        // One pass, not two chained replaces: running h1-2 → h3 and then
        // h3-6 → h4 demotes an original h2 TWICE, because the second pass sees
        // what the first just wrote.
        $html = preg_replace_callback(
            '#<(/?)h([1-6])([^>]*)>#i',
            static fn (array $m): string => sprintf('<%sh%d%s>', $m[1], (int) $m[2] <= 2 ? 3 : 4, $m[3]),
            $html
        );

        // Same normalisation every hand-authored prose field goes through on
        // save, so an imported value and an edited one are the same shape.
        return HtmlCopy::prose($html);
    }

    /**
     * Flattens markdown to plain text — for a meta description or any other
     * attribute, where markup would print as characters.
     */
    public function toPlainText(?string $markdown, int $limit = 0): ?string
    {
        $html = $this->toHtml($markdown);

        if ($html === null) {
            return null;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return null;
        }

        if ($limit > 0 && mb_strlen($text) > $limit) {
            // Break on a word so the truncation does not read as a typo.
            $text = mb_substr($text, 0, $limit);
            $lastSpace = mb_strrpos($text, ' ');
            $text = rtrim($lastSpace !== false ? mb_substr($text, 0, $lastSpace) : $text, " ,;:.").'…';
        }

        return $text;
    }

    private function converter(): MarkdownConverter
    {
        if ($this->converter !== null) {
            return $this->converter;
        }

        $environment = new Environment([
            // The seed is admin-trusted content from a sibling internal system,
            // the same trust model as every other field in this CMS. Escaping
            // would break the one row that already contains HTML.
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);

        return $this->converter = new MarkdownConverter($environment);
    }
}
