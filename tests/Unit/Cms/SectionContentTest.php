<?php

namespace Tests\Unit\Cms;

use App\Cms\Support\LayoutFields;
use App\Cms\Support\SectionContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * "A section with no authored content renders nothing" — the half of that rule
 * that was missing. Structural flags shipped by blueprint defaults must not
 * make an untouched scaffold look authored.
 */
class SectionContentTest extends TestCase
{
    /** text-block's real presentation keys: layout knobs + its two flags. */
    private const TEXT_BLOCK_KEYS = [...LayoutFields::KEYS, 'alignment', 'theme'];

    public static function cases(): array
    {
        return [
            'authored heading is content' => [
                ['body' => null, 'heading' => 'Privacy Policy', 'theme' => 'light', 'alignment' => 'left'],
                true,
            ],
            'untouched scaffold is not' => [
                ['body' => null, 'heading' => null, 'theme' => 'light', 'alignment' => 'left'],
                false,
            ],
            'changing only the knobs is not' => [
                ['body' => null, 'heading' => null, 'theme' => 'dark', 'alignment' => 'center', 'content_width' => 'narrow'],
                false,
            ],
            'an empty repeater is not' => [
                ['items' => [], 'theme' => 'light'],
                false,
            ],
            'a repeater of empty rows is not' => [
                ['items' => [['title' => null, 'body' => null]], 'theme' => 'light'],
                false,
            ],
            'a repeater with one filled row is' => [
                ['items' => [['title' => null], ['title' => 'Real']], 'theme' => 'light'],
                true,
            ],
            'a resolved image is content' => [
                ['heading' => null, 'image' => ['url' => '/x.jpg', 'alt' => ''], 'theme' => 'light'],
                true,
            ],
            'an unresolved image field is not' => [
                ['heading' => null, 'image' => null, 'theme' => 'light'],
                false,
            ],
            'a literal zero is content' => [
                ['heading' => null, 'stats' => [['value' => 0]], 'theme' => 'light'],
                true,
            ],
            'an entirely empty payload is not' => [[], false],
        ];
    }

    #[DataProvider('cases')]
    public function test_presentation_keys_do_not_count_as_content(array $data, bool $expected): void
    {
        $this->assertSame($expected, SectionContent::hasContent($data, self::TEXT_BLOCK_KEYS));
    }

    public function test_layout_knobs_alone_are_never_content(): void
    {
        $data = array_fill_keys(LayoutFields::KEYS, 'lg');

        $this->assertFalse(SectionContent::hasContent($data, LayoutFields::KEYS));
    }

    /**
     * A key the definition does not list stays content even when it looks like
     * a flag — the classification is the blueprint's to make, not a guess from
     * the key's name or shape.
     */
    public function test_an_undeclared_flag_is_treated_as_content(): void
    {
        $this->assertTrue(SectionContent::hasContent(['some_new_toggle' => 'left'], LayoutFields::KEYS));
    }
}
