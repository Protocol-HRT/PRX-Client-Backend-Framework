<?php

namespace App\Enums\Cms;

enum FlexibleFieldKind: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case RichText = 'richtext';
    case Image = 'image';
    case Svg = 'svg';
    case Link = 'link';
    case Boolean = 'boolean';
    case Select = 'select';
    case Repeater = 'repeater';
    case Products = 'products';
    case Packages = 'packages';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text (single line)',
            self::Textarea => 'Text (multi-line)',
            self::RichText => 'Rich text',
            self::Image => 'Image (media library)',
            self::Svg => 'SVG markup',
            self::Link => 'Link (label + URL)',
            self::Boolean => 'Toggle (yes/no)',
            self::Select => 'Dropdown choice',
            self::Repeater => 'Repeating items',
            self::Products => 'Product picker',
            self::Packages => 'Package picker',
        };
    }

    /** Kinds allowed inside a repeater (no nested repeaters). */
    public static function repeaterChildKinds(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $kind): bool => $kind !== self::Repeater,
        ));
    }
}
