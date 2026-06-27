# ProtocolHRT — Blade Template Port

Port of the Rocket-built ProtocolHRT marketing site into Laravel 12 + Livewire 3 Blade components. Design tokens, primitives, and section composition are split so the home page can be re-ordered, themed, or driven from a CMS without touching markup.

## Quick start

Drop these files into a fresh Laravel 12 app (or merge with an existing one), then:

```bash
composer require livewire/livewire
npm install
npm run dev
```

Hit `/` and you should see the full ported home page.

## File map

```
resources/
├── css/
│   └── app.css                       # @font-face, CSS tokens, @layer components
├── js/
│   └── app.js                        # Alpine + @alpinejs/collapse bootstrap
└── views/
    ├── layouts/
    │   └── app.blade.php             # Base shell (head, nav, slot, footer)
    ├── pages/
    │   └── home.blade.php            # Composes the 13 sections
    └── components/
        ├── site/
        │   ├── nav.blade.php         # Sticky top nav + mobile drawer
        │   └── footer.blade.php      # 4-col link footer + email capture
        ├── ui/                       # Atomic primitives — composed everywhere
        │   ├── button.blade.php
        │   ├── eyebrow.blade.php
        │   ├── section-header.blade.php
        │   ├── pricing-card.blade.php
        │   ├── benefit-card.blade.php
        │   ├── physician-card.blade.php
        │   ├── testimonial-card.blade.php
        │   ├── faq-item.blade.php
        │   ├── step-card.blade.php
        │   └── stat.blade.php
        └── sections/                 # Page-level sections — thin compositions
            ├── hero.blade.php                  (1)
            ├── stats-marquee.blade.php         (2)
            ├── results-stats.blade.php         (3)
            ├── pricing-tiers.blade.php         (4)
            ├── physicians.blade.php            (5)
            ├── story.blade.php                 (6)
            ├── benefits-him.blade.php          (7)
            ├── benefits-her.blade.php          (8)
            ├── how-it-works.blade.php          (9)
            ├── testimonials.blade.php          (10)
            ├── transformed.blade.php           (11)
            ├── faq.blade.php                   (12)
            └── final-cta.blade.php             (13)

public/
├── images/
│   ├── logo.svg
│   ├── physicians/                   # baldasare / palumbo / ashley × {card, formal, thumb, portrait}
│   ├── ambassadors/                  # dan-bilzerian.webp
│   ├── lifestyle/                    # him-hero.webp, her-hero.webp
│   └── products/                     # testosterone, glp1, nad-sermorelin vials
└── fonts/                            # cormorant-garamond, dm-sans, jetbrains-mono (woff2)

tailwind.config.js                    # Design tokens (colors, fonts, spacing, animations)
vite.config.js
postcss.config.js
package.json
routes/web.php                        # Single `/` route
```

## How the design system is structured

### Tokens → CSS variables → Tailwind classes

The Rocket source already uses CSS custom properties (`--bg-primary`, `--text-body`, etc.) for theming. We preserved that, then exposed them as Tailwind colors via `var(--…)` references. Result:

- **Color tokens that flip with theme** — `bg-bg-primary`, `text-text-body`, etc. resolve differently when an ancestor has `data-theme="dark"` or the `.theme-dark` class. Sections that need a dark background just declare `class="theme-dark"` and every text/bg utility re-resolves automatically.
- **Static brand colors** — `accent-gold`, `accent-emerald`, `accent-mint` are not theme-dependent, just available.
- **Fonts** — `font-display` (Cormorant Garamond), `font-body` (DM Sans), `font-mono` (JetBrains Mono), `font-sub` (Syne).

### `@layer components` extraction

Anything that repeats 3+ times across the site is extracted in `resources/css/app.css`:

| Class | Where used |
|---|---|
| `.btn` / `.btn-primary` / `.btn-gold` / `.btn-ghost` / `.btn-ghost-dark` / `.btn-link` | All CTAs |
| `.card-luxury` / `.card-luxury-dark` | All card surfaces |
| `.card-pricing` / `.card-pricing-featured` | Pricing tiers |
| `.eyebrow` / `.eyebrow-muted` | Every section's small uppercase label |
| `.lead` | Section lead paragraphs |
| `.section-title` | H2 typographic treatment |
| `.section` / `.section-tight` / `.section-container` | Section padding rhythm |
| `.pill-mint` / `.pill-gold` / `.pill-dark` / `.pill-light` | Status/category pills |
| `.symptom-pill` | Hero AI concierge selector |
| `.checklist` / `.checklist-item` | Pricing tier feature lists |
| `.marquee` / `.marquee-track` | Stats strip |
| `.gold-rule` | Decorative divider |

If you find yourself repeating a utility chain in a fourth place — promote it here.

## Component conventions

- **Primitives** (`components/ui/*`): Anonymous Blade components. Take props for everything visual. Carry a `$slot` so consumers can drop in custom content without forking the component.
- **Sections** (`components/sections/*`): Thin wrappers that arrange primitives. **Section data is declared at the top of each section file in `@php` arrays** — when you wire a CMS, swap each `@php` block for a model/DTO without touching the markup below it. Every section was deliberately written this way.
- **Site chrome** (`components/site/*`): Nav and footer. Link arrays are declared in PHP at the top — same CMS-swap pattern.

## Where Livewire belongs (today vs eventual)

Today, this template is fully static / Alpine-only. The places that should become Livewire components when the actual app behind this template comes online:

| Component | Why |
|---|---|
| `SymptomSelector` (currently the Alpine pills inside `sections/hero.blade.php`) | Feeds the assessment intake — needs server state, persistence, and AI orchestration. The Alpine `selected` array is shaped to mirror what a Livewire `public array $selected = []` would look like, so the swap is a 1:1 replacement. |
| `EmailSubscribe` (footer email capture) | Needs validation + queued newsletter dispatch. Replace the `<form>` block with `<livewire:email-subscribe />`. |
| `FaqItem` | Only if you want server-side analytics on which questions get opened. The Alpine version is fine for static. |
| `PricingTiers` | If pricing/availability becomes dynamic (waitlist counts, A/B tests, regional pricing). |

The architecture is set up so that conversion is markup-replace-only — none of the design or composition has to change.

## Extending: adding a new section

1. Build any new primitives in `components/ui/`.
2. Build the section in `components/sections/yourname.blade.php`. Declare any data as a `@php` array at the top of the file.
3. Add `<x-sections.yourname />` wherever it should appear in `pages/home.blade.php` (or any other page).

## Extending: making sections CMS-driven

The current shape is deliberately compatible with a `Block`-style CMS. Skeleton:

```php
// Block model
class Block extends Model
{
    protected $casts = ['data' => 'array'];

    public function component(): string
    {
        return "sections.{$this->kind}";    // 'sections.pricing-tiers' etc.
    }
}

// Page render
@foreach ($page->blocks as $block)
    <x-dynamic-component :component="$block->component" {{ $block->data }} />
@endforeach
```

Each section component already accepts its data as an array — making them Block-driven is migrating the inline `@php` array into the database, no markup changes.

## Asset notes

- **Fonts are self-hosted** (5 woff2 files in `public/fonts`, ~160KB total). Syne and Space Mono aren't in the inlined source, so they load from Google Fonts via the `<link>` in `layouts/app.blade.php`. Self-host them too if you want zero third-party requests for HIPAA/compliance hygiene.
- **17 images extracted** from the Rocket source's inlined base64 data URIs. They're placeholder quality (the source stored small versions); replace with production photography before launch. The `*.png` portrait files are higher resolution if you need them.
- **Logo** is a simple SVG emblem — replace as needed.

## Known footguns

- Most footer/nav links are `href="#"` placeholders. Wire them to real routes as those pages come online.
- The Dan Bilzerian endorsement is hard-coded — abstract it to config or a model if there's any chance it gets pulled.
- The hero AI concierge claims "Powered by Claude" — verify with the client that this is intended messaging.
- Pricing copy includes "$49 credited toward any peptide order" and "Lock in $149/mo for life" — verify these promises with legal before going live.
- FDA disclaimer text is in the footer. Don't remove it.

## Browser support

- Modern evergreen only (Chrome/Edge/Firefox/Safari last 2). The source uses `text-wrap: balance`, CSS custom properties, and CSS scroll behaviour — IE11 is not a target.
- Reduced-motion is respected via the source's existing `@media (prefers-reduced-motion: no-preference)` guards on smooth scroll.
