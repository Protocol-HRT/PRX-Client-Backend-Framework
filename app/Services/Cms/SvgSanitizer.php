<?php

namespace App\Services\Cms;

use DOMDocument;
use DOMElement;

/**
 * Allowlist SVG sanitizer for admin-supplied inline markup. The frontend
 * injects this verbatim, so anything executable here would be stored XSS on
 * every client site.
 *
 * Parses with DOMDocument (entities are normalized by the parser BEFORE
 * allow/deny decisions — regex approaches are bypassable via encoding) and
 * removes every element and attribute not on the allowlists. URL-bearing
 * attributes only survive with fragment references. Returns null unless the
 * cleaned root is an <svg> element.
 */
class SvgSanitizer
{
    private const ALLOWED_ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc',
        'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        'linearGradient', 'radialGradient', 'stop',
        'clipPath', 'mask', 'pattern', 'marker',
    ];

    private const ALLOWED_ATTRIBUTES = [
        // Geometry / layout
        'd', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'width', 'height', 'points', 'viewBox', 'preserveAspectRatio', 'transform',
        'dx', 'dy', 'pathLength', 'offset', 'markerWidth', 'markerHeight', 'refX', 'refY', 'orient',
        'patternUnits', 'gradientUnits', 'gradientTransform', 'spreadMethod', 'clipPathUnits', 'maskUnits',
        // Presentation
        'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap',
        'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-miterlimit', 'stroke-opacity',
        'opacity', 'color', 'stop-color', 'stop-opacity', 'clip-rule', 'clip-path', 'mask',
        'font-size', 'font-family', 'font-weight', 'text-anchor', 'dominant-baseline', 'letter-spacing',
        // Metadata / hooks (safe: no script capability)
        'id', 'class', 'lang', 'role', 'aria-hidden', 'aria-label', 'focusable',
        'xmlns', 'xmlns:xlink', 'version',
    ];

    /** Attributes that may reference other content — fragment-only. */
    private const URL_ATTRIBUTES = ['href', 'xlink:href'];

    public static function sanitize(string $svg): ?string
    {
        $svg = trim($svg);

        if ($svg === '' || stripos($svg, '<svg') === false) {
            return null;
        }

        $previousErrors = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOENT | LIBXML_NOCDATA);

            if (! $loaded || ! $document->documentElement || strtolower($document->documentElement->localName) !== 'svg') {
                return null;
            }

            self::cleanElement($document->documentElement);

            return $document->saveXML($document->documentElement) ?: null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }

    private static function cleanElement(DOMElement $element): void
    {
        // Snapshot child elements first — removals mutate the live list.
        $children = [];

        foreach ($element->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                if (! in_array($child->localName, self::ALLOWED_ELEMENTS, true)) {
                    $element->removeChild($child);

                    continue;
                }

                self::cleanElement($child);
            } elseif ($child->nodeType !== XML_TEXT_NODE) {
                // Comments, CDATA remnants, processing instructions — drop.
                $element->removeChild($child);
            }
        }

        // Snapshot attributes for the same reason.
        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $attributes[] = $attribute;
        }

        foreach ($attributes as $attribute) {
            $name = $attribute->nodeName;

            if (in_array($name, self::URL_ATTRIBUTES, true) || in_array($attribute->localName, self::URL_ATTRIBUTES, true)) {
                // Parser has already decoded entities here, so scheme checks
                // can't be smuggled past with &#106;avascript: tricks.
                if (! str_starts_with(trim($attribute->nodeValue ?? ''), '#')) {
                    $element->removeAttributeNode($attribute);
                }

                continue;
            }

            if (! in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }
}
