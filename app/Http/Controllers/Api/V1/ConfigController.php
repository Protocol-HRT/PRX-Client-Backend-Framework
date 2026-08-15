<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Telehealth\TelehealthManager;
use App\Settings\BrandSettings;
use App\Settings\ContactSettings;
use App\Settings\SeoSettings;
use App\Settings\ThemeSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * GET /api/v1/config
 *
 * Single public bootstrap call for the React frontend.
 * Aggregates brand + theme + contact + SEO + provider capabilities
 * into one cached response so the app shell renders in a single round-trip.
 *
 * Cached for 5 minutes (config.api.config_ttl). Cleared by Settings observers
 * whenever admin saves changes.
 */
class ConfigController extends ApiController
{
    /**
     * Get the site configuration bundle.
     *
     * Returns brand, theme, contact, SEO, and provider capability settings in a single
     * cached response. The React frontend calls this once on app boot. Cached for 5 minutes.
     *
     * @tags Config
     *
     * @unauthenticated
     */
    public function __invoke(
        BrandSettings $brand,
        ThemeSettings $theme,
        ContactSettings $contact,
        SeoSettings $seo,
        TelehealthManager $telehealth,
    ): JsonResponse {
        $config = Cache::remember('api.v1.config', (int) config('api.config_ttl', 300), function () use ($brand, $theme, $contact, $seo, $telehealth): array {
            $provider = $telehealth->provider();

            return [
                'brand' => [
                    'name' => $brand->name,
                    'tagline' => $brand->tagline,
                    'logo_url' => $brand->logo_path ? Storage::url($brand->logo_path) : null,
                    'logo_dark_url' => $brand->logo_dark_path ? Storage::url($brand->logo_dark_path) : null,
                    'logo_light_url' => $brand->logo_light_path ? Storage::url($brand->logo_light_path) : null,
                    'favicon_url' => $brand->favicon_path ? Storage::url($brand->favicon_path) : null,
                    'hero_image_url' => $brand->hero_image_path ? Storage::url($brand->hero_image_path) : null,
                    'announcement' => $brand->announcement_enabled ? [
                        'emphasis' => $brand->announcement_emphasis,
                        'text' => $brand->announcement_text,
                    ] : null,
                ],
                'theme' => [
                    'primary_color' => $theme->primary_color,
                    'accent_color' => $theme->accent_color,
                    'accent_secondary_color' => $theme->accent_secondary_color,
                    'background_color' => $theme->background_color,
                    'text_color' => $theme->text_color,
                    'font_display' => $theme->font_display,
                    'font_body' => $theme->font_body,
                    'custom_css' => $theme->custom_css,
                    'frontend_template' => $theme->frontend_template,
                ],
                'contact' => array_filter([
                    'support_email' => $contact->support_email,
                    'sales_email' => $contact->sales_email,
                    'phone' => $contact->phone,
                    'address' => array_filter([
                        'line_1' => $contact->address_line_1,
                        'line_2' => $contact->address_line_2,
                        'city' => $contact->city,
                        'state' => $contact->state,
                        'postal_code' => $contact->postal_code,
                        'country' => $contact->country,
                    ]),
                    'business_hours' => $contact->business_hours,
                    'social' => array_filter([
                        'instagram' => $contact->instagram_url,
                        'twitter' => $contact->twitter_url,
                        'facebook' => $contact->facebook_url,
                        'linkedin' => $contact->linkedin_url,
                        'tiktok' => $contact->tiktok_url,
                        'youtube' => $contact->youtube_url,
                    ]),
                ]),
                'seo' => [
                    'default_title' => $seo->default_meta_title,
                    'default_description' => $seo->default_meta_description,
                    'og_image_url' => $seo->og_image_path ? Storage::url($seo->og_image_path) : null,
                    'google_analytics_id' => $seo->google_analytics_id,
                    'google_tag_manager_id' => $seo->google_tag_manager_id,
                    'facebook_pixel_id' => $seo->facebook_pixel_id,
                    'tiktok_pixel_id' => $seo->tiktok_pixel_id,
                    'custom_head_scripts' => $seo->custom_head_scripts,
                    'custom_body_scripts' => $seo->custom_body_scripts,
                    'allow_indexing' => $seo->allow_indexing,
                ],
                'provider' => [
                    'name' => $provider->getName(),
                    'slug' => $provider->getSlug(),
                    'supports_embed' => $provider->supportsEmbed(),
                    'supports_patient_portal_auth' => $provider->supportsPatientPortalAuth(),
                ],
            ];
        });

        return $this->success($config);
    }
}
