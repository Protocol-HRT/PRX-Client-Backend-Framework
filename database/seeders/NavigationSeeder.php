<?php

namespace Database\Seeders;

use App\Enums\Cms\MenuLinkType;
use App\Enums\Cms\Region;
use App\Enums\Cms\RegionItemKind;
use App\Enums\PageStatus;
use App\Enums\SectionType;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Cms\RegionItem;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Seeder;

/**
 * Seeds the global site chrome: the three standard menus (main-nav,
 * footer-quick-links, footer-legal), the header/footer region items that
 * mount them, and published stub pages for every entity link target
 * (menu items whose page is missing or unpublished are dropped from the
 * API payload, so the targets must exist for the nav to render at all).
 *
 * Dev/demo content like HomePageSeeder — invoked explicitly, never from
 * DatabaseSeeder. Idempotent: re-running recreates the three menus and
 * their region mounts (region items cascade with their menu), but reuses
 * existing pages untouched so real authored content is never clobbered.
 *
 * Menu items and region items carry no explicit position: SortableTrait's
 * sort_when_creating ignores supplied values, so ordering is creation order.
 */
class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'about-us' => $this->ensurePage('about-us', 'About Us'),
            'how-it-works' => $this->ensurePage('how-it-works', 'How It Works'),
            'faq' => $this->ensurePage('faq', 'FAQ'),
            'contact-us' => $this->ensurePage('contact-us', 'Contact Us'),
            'privacy-policy' => $this->ensurePage('privacy-policy', 'Privacy Policy'),
            'terms-and-conditions' => $this->ensurePage('terms-and-conditions', 'Terms & Conditions'),
        ];

        // Deleting the menus cascades their items AND the region items that
        // mount them (region_items.menu_id cascadeOnDelete).
        Menu::query()
            ->whereIn('slug', ['main-nav', 'footer-quick-links', 'footer-legal'])
            ->get()
            ->each(fn (Menu $menu) => $menu->delete());

        $mainNav = Menu::create([
            'name' => 'Main Navigation',
            'slug' => 'main-nav',
            'description' => 'Primary header navigation.',
        ]);
        $this->urlItem($mainNav, 'Stacks', '/stacks');
        $this->pageItem($mainNav, 'How It Works', $pages['how-it-works']);
        $this->pageItem($mainNav, 'FAQ', $pages['faq']);
        $this->pageItem($mainNav, 'About', $pages['about-us']);

        $quickLinks = Menu::create([
            'name' => 'Footer Quick Links',
            'slug' => 'footer-quick-links',
            'description' => 'Footer "Quick Links" column.',
        ]);
        $this->pageItem($quickLinks, 'About Us', $pages['about-us']);
        $this->pageItem($quickLinks, 'Contact', $pages['contact-us']);
        $this->pageItem($quickLinks, 'FAQ', $pages['faq']);

        $legal = Menu::create([
            'name' => 'Footer Legal',
            'slug' => 'footer-legal',
            'description' => 'Footer "Legal" column.',
        ]);
        $this->pageItem($legal, 'Privacy Policy', $pages['privacy-policy']);
        $this->pageItem($legal, 'Terms & Conditions', $pages['terms-and-conditions']);

        $this->mountMenu(Region::Header, $mainNav);
        $this->mountMenu(Region::Footer, $quickLinks);
        $this->mountMenu(Region::Footer, $legal);

        $this->command?->info('Navigation seeded: 3 menus, header + footer region mounts, '.count($pages).' link target pages ensured.');
    }

    /**
     * Guarantee a published page exists at the slug. Newly created stubs get
     * a single text-block section; existing pages (any status) are republished
     * only if needed and otherwise left untouched.
     */
    private function ensurePage(string $slug, string $title): Page
    {
        $page = Page::withTrashed()->where('slug', $slug)->first();

        if ($page !== null) {
            $page->restore();

            if (! $page->isPublished()) {
                $page->update(['status' => PageStatus::Published]);
            }

            return $page;
        }

        $page = Page::create([
            'title' => $title,
            'slug' => $slug,
            'status' => PageStatus::Published,
            'template' => 'default',
        ]);

        PageSection::create([
            'page_id' => $page->id,
            'type' => SectionType::TextBlock->value,
            'enabled' => true,
            'data' => array_merge(SectionType::TextBlock->blueprint()->defaults(), [
                'heading' => $title,
            ]),
        ]);

        return $page;
    }

    private function pageItem(Menu $menu, string $label, Page $page): MenuItem
    {
        return MenuItem::create([
            'menu_id' => $menu->id,
            'label' => $label,
            'link_type' => MenuLinkType::Page,
            'linkable_type' => 'page',
            'linkable_id' => $page->id,
            'enabled' => true,
        ]);
    }

    private function urlItem(Menu $menu, string $label, string $url): MenuItem
    {
        return MenuItem::create([
            'menu_id' => $menu->id,
            'label' => $label,
            'link_type' => MenuLinkType::Url,
            'url' => $url,
            'enabled' => true,
        ]);
    }

    private function mountMenu(Region $region, Menu $menu): RegionItem
    {
        return RegionItem::create([
            'region' => $region,
            'kind' => RegionItemKind::Menu,
            'menu_id' => $menu->id,
            'enabled' => true,
        ]);
    }
}
