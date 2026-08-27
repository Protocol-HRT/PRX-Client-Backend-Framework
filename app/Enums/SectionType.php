<?php

namespace App\Enums;

use App\Cms\Sections\BenefitsDiagramSection;
use App\Cms\Sections\BenefitsHerSection;
use App\Cms\Sections\BenefitsHimSection;
use App\Cms\Sections\CategoryGridSection;
use App\Cms\Sections\CtaBannerSection;
use App\Cms\Sections\FaqCategoriesSection;
use App\Cms\Sections\FaqSection;
use App\Cms\Sections\FeaturesGridSection;
use App\Cms\Sections\FinalCtaSection;
use App\Cms\Sections\HeroSection;
use App\Cms\Sections\HighlightBannerSection;
use App\Cms\Sections\HowItWorksSection;
use App\Cms\Sections\ImageCalloutBannerSection;
use App\Cms\Sections\ImageTextSplitSection;
use App\Cms\Sections\PackagePricingComparisonSection;
use App\Cms\Sections\PackageSliderSection;
use App\Cms\Sections\PhysiciansSection;
use App\Cms\Sections\PricingTiersSection;
use App\Cms\Sections\QuizCtaSection;
use App\Cms\Sections\ProductCalloutSection;
use App\Cms\Sections\ProductGridSection;
use App\Cms\Sections\ProductSliderSection;
use App\Cms\Sections\ResultsStatsSection;
use App\Cms\Sections\SectionBlueprint;
use App\Cms\Sections\StatsMarqueeSection;
use App\Cms\Sections\StorySection;
use App\Cms\Sections\TestimonialsSection;
use App\Cms\Sections\TextBlockSection;
use App\Cms\Sections\TimelineSection;
use App\Cms\Sections\TransformedSection;
use App\Cms\Sections\VideoEmbedSection;

enum SectionType: string
{
    // 13 imported from the PrescribeRx Open Source Backend template
    case Hero = 'hero';
    case StatsMarquee = 'stats-marquee';
    case ResultsStats = 'results-stats';
    case PricingTiers = 'pricing-tiers';
    case Physicians = 'physicians';
    case Story = 'story';
    case BenefitsHim = 'benefits-him';
    case BenefitsHer = 'benefits-her';
    case HowItWorks = 'how-it-works';
    case Testimonials = 'testimonials';
    case Transformed = 'transformed';
    case Faq = 'faq';
    case FinalCta = 'final-cta';

    // 5 generic Tailwind blocks
    case TextBlock = 'text-block';
    case ImageTextSplit = 'image-text-split';
    case CtaBanner = 'cta-banner';
    case QuizCta = 'quiz-cta';
    case FeaturesGrid = 'features-grid';
    case VideoEmbed = 'video-embed';
    case HighlightBanner = 'highlight-banner';
    case ImageCalloutBanner = 'image-callout-banner';
    case BenefitsDiagram = 'benefits-diagram';
    case Timeline = 'timeline';

    // Product-aware sections (catalog data inlined at API read time)
    case ProductSlider = 'product-slider';
    case ProductGrid = 'product-grid';
    case ProductCallout = 'product-callout';
    case PackageSlider = 'package-slider';
    case PackagePricingComparison = 'package-pricing-comparison';
    case CategoryGrid = 'category-grid';

    // Content-dataset-aware sections (FAQ dataset inlined at API read time)
    case FaqCategories = 'faq-categories';

    public function blueprint(): SectionBlueprint
    {
        return match ($this) {
            self::Hero => app(HeroSection::class),
            self::StatsMarquee => app(StatsMarqueeSection::class),
            self::ResultsStats => app(ResultsStatsSection::class),
            self::PricingTiers => app(PricingTiersSection::class),
            self::Physicians => app(PhysiciansSection::class),
            self::Story => app(StorySection::class),
            self::BenefitsHim => app(BenefitsHimSection::class),
            self::BenefitsHer => app(BenefitsHerSection::class),
            self::HowItWorks => app(HowItWorksSection::class),
            self::Testimonials => app(TestimonialsSection::class),
            self::Transformed => app(TransformedSection::class),
            self::Faq => app(FaqSection::class),
            self::FinalCta => app(FinalCtaSection::class),
            self::TextBlock => app(TextBlockSection::class),
            self::ImageTextSplit => app(ImageTextSplitSection::class),
            self::CtaBanner => app(CtaBannerSection::class),
            self::QuizCta => app(QuizCtaSection::class),
            self::FeaturesGrid => app(FeaturesGridSection::class),
            self::VideoEmbed => app(VideoEmbedSection::class),
            self::HighlightBanner => app(HighlightBannerSection::class),
            self::ImageCalloutBanner => app(ImageCalloutBannerSection::class),
            self::BenefitsDiagram => app(BenefitsDiagramSection::class),
            self::Timeline => app(TimelineSection::class),
            self::ProductSlider => app(ProductSliderSection::class),
            self::ProductGrid => app(ProductGridSection::class),
            self::ProductCallout => app(ProductCalloutSection::class),
            self::PackageSlider => app(PackageSliderSection::class),
            self::PackagePricingComparison => app(PackagePricingComparisonSection::class),
            self::CategoryGrid => app(CategoryGridSection::class),
            self::FaqCategories => app(FaqCategoriesSection::class),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $opts = [];
        foreach (self::cases() as $case) {
            $opts[$case->value] = $case->blueprint()->label();
        }

        return $opts;
    }
}
