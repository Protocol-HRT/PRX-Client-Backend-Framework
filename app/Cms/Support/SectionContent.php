<?php

namespace App\Cms\Support;

/**
 * Decides whether a section's payload holds anything an operator authored.
 *
 * Both repos state the rule "a section with no authored content renders
 * nothing", but it was only half true: a payload was judged empty when every
 * value was null, and blueprint defaults legitimately ship structural flags
 * (`theme: "light"`, `alignment: "left"`, `mode: "manual"`). A section an
 * editor added and never filled in therefore looked authored, and an empty
 * scaffold could reach a live page.
 *
 * Presentation keys come from the definition, so the decision is made once
 * here rather than guessed from a hardcoded flag list in each frontend.
 */
final class SectionContent
{
    /**
     * @param  array<string, mixed>  $data  Payload AFTER field-kind transformation
     *                                      and resolveData(), so inlined catalog
     *                                      results count as the content they are.
     * @param  list<string>  $presentationKeys
     */
    public static function hasContent(array $data, array $presentationKeys): bool
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $presentationKeys, true)) {
                continue;
            }

            if (! self::isEmpty($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Empty means "nothing an operator put here". A literal 0 is content — a
     * stat reading "0" is a real value — so only null, "", false and
     * structurally empty arrays count.
     */
    private static function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === false || $value === []) {
            return true;
        }

        // A served sub-block carries its OWN verdict, and it is the only
        // thing worth asking. Walking one generically would find its `type`
        // discriminator — always a non-empty string — and call the child
        // authored, so a section holding nothing but empty children would
        // report has_content: true and leak an empty scaffold onto a live
        // page. That is the regression this class exists to prevent, one
        // level down.
        if (SectionChildren::isEnvelope($value)) {
            return $value['has_content'] === false;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (! self::isEmpty($item)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
