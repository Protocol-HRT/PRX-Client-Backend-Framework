<?php

namespace Tests\Unit\Cms;

use App\Cms\Support\LayoutDefaults;
use App\Cms\Support\LayoutFields;
use App\Enums\SectionType;
use PHPUnit\Framework\TestCase;

/**
 * Design defaults are merged when a payload is served rather than stamped
 * into a row at creation, so retuning a type reaches sections that already
 * exist. The merge must never overwrite an operator's choice, and must never
 * become a back door for content.
 */
class LayoutDefaultsTest extends TestCase
{
    public function test_a_default_fills_a_knob_the_operator_left_unset(): void
    {
        $merged = LayoutFields::applyDefaults(
            ['heading' => 'Real copy', 'content_width' => null],
            ['content_width' => 'xwide'],
        );

        $this->assertSame('xwide', $merged['content_width']);
    }

    public function test_a_default_fills_a_knob_that_is_absent_entirely(): void
    {
        $merged = LayoutFields::applyDefaults(['heading' => 'Real copy'], ['content_width' => 'wide']);

        $this->assertSame('wide', $merged['content_width']);
    }

    public function test_an_operator_choice_always_wins(): void
    {
        $merged = LayoutFields::applyDefaults(
            ['content_width' => 'narrow'],
            ['content_width' => 'xwide'],
        );

        $this->assertSame('narrow', $merged['content_width']);
    }

    /**
     * layoutDefaults() must not be able to smuggle authored copy into a
     * payload — everything it merges is a presentation key, which is what
     * lets has_content stay correct without knowing the merge happened.
     */
    public function test_a_key_outside_the_knob_vocabulary_is_refused(): void
    {
        $merged = LayoutFields::applyDefaults([], ['heading' => 'Injected copy']);

        $this->assertArrayNotHasKey('heading', $merged);
    }

    /**
     * Every entry, not a sample: a typo'd key is dropped by applyDefaults and
     * a typo'd value is dropped by the frontend's allow-list, so neither
     * throws anywhere. This assertion is the only thing that catches one.
     */
    public function test_every_declared_default_names_a_real_knob_and_a_real_token(): void
    {
        $vocabulary = [
            'content_width' => ['narrow', 'medium', 'wide', 'xwide', 'full'],
            'media_width' => ['contained', 'full'],
            'extra_padding' => ['sm', 'md', 'lg'],
            'content_inset' => ['sm', 'md', 'lg', 'xl'],
            'content_align' => ['left', 'center', 'right'],
        ];

        $this->assertNotEmpty(LayoutDefaults::all());

        foreach (LayoutDefaults::all() as $type => $defaults) {
            foreach ($defaults as $key => $value) {
                $this->assertContains($key, LayoutFields::KEYS, "{$type} declares an unknown knob '{$key}'.");
                $this->assertContains(
                    $value,
                    $vocabulary[$key],
                    "{$type}.{$key} is set to '{$value}', which is not in the knob's vocabulary.",
                );
            }
        }
    }

    /**
     * prx-backend ships to every client; a section type that exists only in
     * one client's database must not be named in its code.
     */
    public function test_the_table_names_only_types_the_backend_itself_defines(): void
    {
        $codeTypes = array_map(static fn ($case): string => $case->value, SectionType::cases());

        foreach (array_keys(LayoutDefaults::all()) as $type) {
            $this->assertContains($type, $codeTypes, "'{$type}' is not a code-defined section type; its defaults belong in the client's database.");
        }
    }

    public function test_an_unknown_type_declares_nothing(): void
    {
        $this->assertSame([], LayoutDefaults::for('not-a-section-type'));
    }
}
