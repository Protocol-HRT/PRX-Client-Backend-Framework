<?php

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Enums\SectionType;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Seeder;

/**
 * Seeds a starter "home" page in the CMS with all 13 standard section types
 * pre-populated from their blueprint defaults.
 *
 * Idempotent: deletes any existing `home` page (cascades sections) and re-creates it.
 * Run after a fresh migrate to give admins a fully editable starting point at
 * /admin/pages/{home}/edit. All section content comes from blueprint defaults —
 * replace with real copy via the admin UI.
 */
class HomePageSeeder extends Seeder
{
    public function run(): void
    {
        Page::withTrashed()->where('slug', 'home')->forceDelete();

        $page = Page::create([
            'title' => 'Home',
            'slug' => 'home',
            'status' => PageStatus::Published,
            'template' => 'default',
            'meta_title' => null,
            'meta_description' => null,
            'noindex' => false,
        ]);

        $order = [
            SectionType::Hero,
            SectionType::StatsMarquee,
            SectionType::ResultsStats,
            SectionType::PricingTiers,
            SectionType::Physicians,
            SectionType::Story,
            SectionType::BenefitsHim,
            SectionType::BenefitsHer,
            SectionType::HowItWorks,
            SectionType::Testimonials,
            SectionType::Transformed,
            SectionType::Faq,
            SectionType::FinalCta,
        ];

        foreach ($order as $i => $type) {
            PageSection::create([
                'page_id' => $page->id,
                'type' => $type->value,
                'position' => $i + 1,
                'enabled' => true,
                'data' => $type->blueprint()->defaults(),
            ]);
        }

        $this->command?->info("Home page seeded with {$page->sections()->count()} sections (slug: {$page->slug}).");
    }
}
