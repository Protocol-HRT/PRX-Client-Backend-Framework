<?php

namespace Tests\Unit\Cms;

use App\Cms\Support\HtmlCopy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The HTML contract each field kind promises a consuming frontend.
 *
 * `inline` values are guaranteed free of block markup, because the frontend
 * supplies the wrapping element. `prose` values keep their block structure.
 * Both collapse to null when they carry no readable text, so a section's
 * empty-content guard can drop them.
 */
class HtmlCopyTest extends TestCase
{
    public static function inlineCases(): array
    {
        return [
            'unwraps the editor paragraph' => ['<p>Our Story</p>', 'Our Story'],
            'keeps inline emphasis' => ['<p>Our <em>Story</em></p>', 'Our <em>Story</em>'],
            'keeps an authored hard break' => [
                '<p>The Operating System<br>for Longevity</p>',
                'The Operating System<br />for Longevity',
            ],
            'flattens a pasted heading' => ['<h2>Pasted</h2>', 'Pasted'],
            'joins sibling blocks with a break' => ['<p>One</p><p>Two</p>', 'One<br />Two'],
            'flattens a pasted list' => ['<ul><li>a</li><li>b</li></ul>', 'a<br />b'],
            'keeps a link' => ['<p>See <a href="/x">this</a></p>', 'See <a href="/x">this</a>'],
            'passes plain text through' => ['Plain text', 'Plain text'],
            'drops trailing empty blocks' => ['<p>Hipaa Policy</p><p></p><p></p>', 'Hipaa Policy'],
            'nulls an all-empty value' => ['<p></p><p></p>', null],
            'nulls a non-breaking space' => ['<p>&nbsp;</p>', null],
            'nulls an empty string' => ['', null],
            'nulls null' => [null, null],
        ];
    }

    #[DataProvider('inlineCases')]
    public function test_inline_flattens_to_inline_html(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, HtmlCopy::inline($input));
    }

    public function test_inline_never_emits_block_markup(): void
    {
        $blocky = '<h1>A</h1><p>B</p><ul><li>C</li></ul><blockquote>D</blockquote><table><tr><td>E</td></tr></table>';

        $this->assertDoesNotMatchRegularExpression(
            '#<(p|div|h[1-6]|ul|ol|li|blockquote|table|tr|td|pre)\b#i',
            (string) HtmlCopy::inline($blocky),
        );
    }

    public static function proseCases(): array
    {
        return [
            'keeps paragraphs' => ['<p>One</p><p>Two</p>', '<p>One</p><p>Two</p>'],
            'keeps headings' => ['<h2>Section</h2><p>Body</p>', '<h2>Section</h2><p>Body</p>'],
            'keeps lists' => ['<ul><li>a</li></ul>', '<ul><li>a</li></ul>'],
            'drops the empty paragraph spew' => [
                '<p>Hipaa Policy</p><p></p><p></p><p></p>',
                '<p>Hipaa Policy</p>',
            ],
            'drops paragraphs holding only a break' => ['<p>A</p><p><br></p>', '<p>A</p>'],
            'nulls an all-empty value' => ['<p></p><p></p>', null],
            'nulls null' => [null, null],
        ];
    }

    #[DataProvider('proseCases')]
    public function test_prose_keeps_block_structure(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, HtmlCopy::prose($input));
    }
}
