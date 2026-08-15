<?php

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Enums\SectionType;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Seeder;

/**
 * Seeds a starter "home" page in the CMS with an empty scaffold of the
 * standard landing-page section types, in a typical order.
 *
 * Blueprint defaults are intentionally content-free: nothing renders on the
 * frontend until an operator authors content in the admin UI, so a fresh
 * install can never leak copy that belongs to another deployment.
 *
 * Idempotent: deletes any existing `home` page (cascades sections) and
 * re-creates it. Run after a fresh migrate for an editable starting point at
 * /admin/pages/{home}/edit.
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
            SectionType::CategoryGrid,
            SectionType::ImageTextSplit,
            SectionType::HowItWorks,
            SectionType::Physicians,
            SectionType::PackageSlider,
            SectionType::ProductSlider,
            SectionType::Faq,
            SectionType::FinalCta,
            SectionType::Testimonials,
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
