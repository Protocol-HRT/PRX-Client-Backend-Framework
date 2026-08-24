<?php

namespace App\Cms\Support;

use Filament\Forms\Components\RichEditor;

/**
 * Shared rich-text inputs for admin-authored copy.
 *
 * Every blueprint builds its text fields from these two factories rather than
 * configuring RichEditor ad hoc, so an operator meets the same toolbar for the
 * same kind of field on every section type, and a consuming frontend can rely
 * on the HTML shape each kind emits.
 *
 * Which kind a field takes is decided by how the frontend renders it, not by
 * how long the copy is:
 *
 * - `inline()` — the frontend supplies the element (a heading, a card title, a
 *   stat label, a list item). Block markup is stripped on save; see HtmlCopy.
 * - `prose()` — the frontend renders the value into a container of its own, so
 *   headings, lists and multiple paragraphs are all valid.
 *
 * Note this trades away TextInput's maxLength: character counts are misleading
 * once a value carries markup, and the editor has no equivalent. Field length
 * is now a matter of editorial judgement rather than a hard stop.
 */
final class CopyFields
{
    /**
     * Deliberately no block tools. The frontend owns the wrapping element for
     * these fields, so offering h2/h3 would invite invalid nesting.
     */
    public const INLINE_TOOLBAR = ['bold', 'italic', 'link', 'undo', 'redo'];

    /** Block tools are legitimate here — the value gets its own container. */
    public const PROSE_TOOLBAR = [
        'bold', 'italic', 'link',
        'h2', 'h3',
        'bulletList', 'orderedList', 'blockquote',
        'undo', 'redo',
    ];

    /**
     * A heading, eyebrow, card title, badge, stat value or other run of copy
     * the frontend wraps in an element it chooses.
     */
    public static function inline(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons(self::INLINE_TOOLBAR)
            ->dehydrateStateUsing(static fn (?string $state): ?string => HtmlCopy::inline($state))
            ->helperText('Bold, italic and links are honored. Shift+Enter forces a line break.');
    }

    /**
     * Body copy rendered into a container of its own: a text block, an FAQ
     * answer, a physician bio, a timeline step.
     */
    public static function prose(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons(self::PROSE_TOOLBAR)
            ->dehydrateStateUsing(static fn (?string $state): ?string => HtmlCopy::prose($state))
            ->columnSpanFull()
            ->helperText('Rendered as HTML on the public site — headings, lists, links and line breaks are honored.');
    }
}
