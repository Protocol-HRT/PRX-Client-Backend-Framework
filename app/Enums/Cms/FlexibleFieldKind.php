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
    case Number = 'number';
    case Color = 'color';
    case Repeater = 'repeater';
    case Products = 'products';
    case Packages = 'packages';
    case Product = 'product';
    case Package = 'package';
    case Category = 'category';
    case Categories = 'categories';
    case Cta = 'cta';
    case Group = 'group';

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
            self::Number => 'Number',
            self::Color => 'Color',
            self::Repeater => 'Repeating items',
            self::Products => 'Product picker (multiple)',
            self::Packages => 'Package picker (multiple)',
            self::Product => 'Product picker (single)',
            self::Package => 'Package picker (single)',
            self::Category => 'Category picker (single)',
            self::Categories => 'Category picker (multiple)',
            self::Cta => 'Call to action (link or add-to-cart)',
            self::Group => 'Field group (nested map)',
        };
    }

    /**
     * Kinds authorable inside a repeater in the admin UI. The validator
     * additionally allows one level of repeater-in-repeater for seeded
     * schemas; groups never nest.
     */
    public static function repeaterChildKinds(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $kind): bool => $kind !== self::Repeater && $kind !== self::Group,
        ));
    }

    /**
     * Kinds whose stored value is resolved in place in the API payload
     * (media ids -> url objects, catalog ids -> inlined cards) unless the
     * field opts out with `raw: true`.
     */
    public static function inlineResolvedKinds(): array
    {
        return [self::Image, self::Svg, self::Products, self::Packages, self::Product, self::Package];
    }
}
