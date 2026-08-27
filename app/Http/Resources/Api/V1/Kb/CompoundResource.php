<?php

namespace App\Http\Resources\Api\V1\Kb;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Public shape of a knowledge-base monograph.
 *
 * The eight prose sections are the bulk of the payload — roughly 28,000
 * characters per compound — so they are gated to the `show` route exactly as
 * ProductResource gates `description`. An index page that shipped them would
 * send about 3 MB to render a list of names.
 *
 * `regulatory` is an object rather than a bare string on purpose. The frontend
 * has to render a label, colour a badge, and decide whether to show a
 * not-approved notice; deriving all three from an enum value would put this
 * app's regulatory vocabulary into the frontend's code, where a new case would
 * silently render as unstyled text.
 */
class CompoundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isDetail = $request->routeIs('api.v1.kb.compounds.show');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'tagline' => $this->tagline,
            'is_peptide' => (bool) $this->is_peptide,
            'compound_class' => $this->compound_class,
            'route_of_administration' => $this->route_of_administration,
            'brand_names' => array_values($this->brand_names ?? []),
            'synonyms' => array_values($this->synonyms ?? []),
            'hero_image_url' => $this->hero_image_path ? Storage::disk('public')->url($this->hero_image_path) : null,

            'regulatory' => $this->regulatory_status === null ? null : [
                'value' => $this->regulatory_status->value,
                'label' => $this->regulatory_status->label(),
                'description' => $this->regulatory_status->description(),
                'is_approved_for_human_use' => $this->regulatory_status->isApprovedForHumanUse(),
            ],

            'evidence' => [
                'tier' => $this->evidence_tier,
                'score' => $this->evidence_score !== null ? (float) $this->evidence_score : null,
            ],

            // The trust signal. Emitted on the index too, because a listing
            // that shows who reviewed each entry is doing the same job as the
            // detail page's byline.
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn (): ?array => $this->reviewedBy === null ? null : [
                'name' => $this->reviewedBy->name,
                'title' => $this->reviewedBy->title,
                'credentials' => $this->reviewedBy->credentials,
                'slug' => $this->reviewedBy->slug,
                'image_url' => $this->reviewedBy->image_path
                    ? Storage::disk('public')->url($this->reviewedBy->image_path)
                    : null,
            ]),
            'reviewed_at' => $this->reviewed_at?->toDateString(),
            'published_at' => $this->published_at?->toDateString(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // What this pharmacy actually sells that contains the compound.
            // The reason the KB is not a generic health wiki.
            'products' => $this->whenLoaded('ingredient', fn (): array => $this->ingredient === null
                ? []
                : $this->ingredient->products
                    ->map(fn ($product): array => [
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'subtitle' => $product->subtitle,
                        'hero_image_url' => $product->hero_image_path
                            ? Storage::disk('public')->url($product->hero_image_path)
                            : null,
                    ])
                    ->values()
                    ->all()),

            'description' => $this->description,

            'monograph' => $this->when($isDetail, fn (): array => [
                'overview' => $this->overview,
                'mechanism_of_action' => $this->mechanism_of_action,
                'pharmacology' => $this->pharmacology,
                'clinical_evidence' => $this->clinical_evidence,
                'dosing_guidelines' => $this->dosing_guidelines,
                'safety_profile' => $this->safety_profile,
                'patient_summary' => $this->patient_summary,
            ]),

            'clinical_references' => $this->when($isDetail, fn (): array => array_values($this->clinical_references ?? [])),

            'seo' => $this->when($isDetail, fn (): array => [
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'og_image_url' => $this->og_image_path ? Storage::disk('public')->url($this->og_image_path) : null,
            ]),

            // Provenance travels with the payload, and it is a trust signal
            // rather than a disclaimer: this text is summarised from the
            // operator's own clinical literature corpus by a retrieval
            // pipeline, and `source_count` is the size of the evidence base
            // behind the page. Emitted on the index too, because a card
            // showing "42 sources" is doing the same job as the byline.
            'provenance' => [
                'source_count' => $this->sourceCount(),
                'reference_count' => count($this->clinical_references ?? []),
                'source_system' => $this->when($isDetail, $this->source_system),
                'content_model' => $this->when($isDetail, $this->content_model),
                'content_generated_at' => $this->when($isDetail, $this->content_generated_at?->toIso8601String()),
                'dosing_source_count' => $this->when($isDetail, $this->source_dosing_count ?: null),
            ],
        ];
    }
}
