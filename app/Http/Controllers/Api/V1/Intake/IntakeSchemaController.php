<?php

namespace App\Http\Controllers\Api\V1\Intake;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Catalog\Product;
use App\Services\Telehealth\TelehealthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/intake/schema
 *
 * Returns the intake question schema for a set of selected products/packages.
 * The frontend renders this schema dynamically — no hardcoded form fields anywhere.
 *
 * Resolution priority for encounter type:
 *   1. product.provider_encounter_type_id  (product-level override)
 *   2. category.provider_encounter_type_id (first category with one set)
 *   3. null → 404 (admin must map category or product to an encounter type)
 *
 * Query params:
 *   ?products[]=1&products[]=2   Product IDs (local integer IDs)
 *   ?packages[]=5                Package IDs
 *
 * When multiple products map to different encounter types, the first non-null
 * encounter type found (in the order products are listed) wins. If the cart
 * genuinely spans incompatible encounter types, the checkout flow should split
 * them into separate intakes — that's a frontend concern for now.
 */
class IntakeSchemaController extends ApiController
{
    public function __invoke(Request $request, TelehealthManager $telehealth): JsonResponse
    {
        $request->validate([
            'products' => ['sometimes', 'array'],
            'products.*' => ['integer'],
            'packages' => ['sometimes', 'array'],
            'packages.*' => ['integer'],
        ]);

        $encounterTypeId = $this->resolveEncounterTypeId(
            $request->input('products', []),
            $request->input('packages', [])
        );

        if ($encounterTypeId === null) {
            return $this->error(
                'No encounter type is mapped to the selected products. Configure one via Admin → Catalog.',
                422
            );
        }

        $schema = $telehealth->getEncounterTypeSchema($encounterTypeId);

        return $this->success([
            'encounter_type_id' => $encounterTypeId,
            'schema' => $schema,
        ]);
    }

    /**
     * Walk the selected product/package IDs in order and return the first
     * encounter_type_id found via the product-override → category cascade.
     *
     * @param  list<int>  $productIds
     * @param  list<int>  $packageIds
     */
    private function resolveEncounterTypeId(array $productIds, array $packageIds): ?string
    {
        $ids = array_merge(
            $productIds,
            // Packages expose products; load their products for encounter type lookup.
            \App\Models\Catalog\Package::whereIn('id', $packageIds)
                ->with('products:id,provider_encounter_type_id')
                ->get()
                ->flatMap(fn ($pkg) => $pkg->products->pluck('id'))
                ->all()
        );

        if (empty($ids) && empty($packageIds)) {
            // Try packages directly (package-level encounter type override).
            $ids = $packageIds;
        }

        // Check product-level overrides first.
        $products = Product::whereIn('id', array_unique($ids))
            ->with(['categories:id,provider_encounter_type_id'])
            ->get(['id', 'provider_encounter_type_id']);

        foreach ($products as $product) {
            if (filled($product->provider_encounter_type_id)) {
                return $product->provider_encounter_type_id;
            }

            foreach ($product->categories as $category) {
                if (filled($category->provider_encounter_type_id)) {
                    return $category->provider_encounter_type_id;
                }
            }
        }

        // Fall back to package-level encounter type if no product mapping found.
        if (! empty($packageIds)) {
            $pkg = \App\Models\Catalog\Package::whereIn('id', $packageIds)
                ->whereNotNull('provider_encounter_type_id')
                ->value('provider_encounter_type_id');

            if ($pkg) {
                return $pkg;
            }
        }

        return null;
    }
}
