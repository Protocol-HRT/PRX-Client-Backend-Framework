<?php

namespace Database\Seeders;

use App\Models\Catalog\AdministrationMethod;
use App\Models\Catalog\MeasurementUnit;
use App\Models\Catalog\ProductForm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds deployment-neutral clinical vocabulary for the catalog lookup
 * tables: administration methods, product forms, and measurement units.
 *
 * Terminology and provider_value integers mirror the PrescribeRx
 * integer-backed enums (ProductDeliveryMethod, ProductForm, UnitsOfMeasure)
 * so synced products land with matching labels. All rows remain fully
 * admin-editable — deployments using other fulfillment providers can
 * rename, deactivate, or extend the vocabulary in the Filament admin.
 *
 * Idempotent: safe to re-run; matches on slug and never overwrites
 * admin-edited names.
 */
class CatalogVocabularySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdministrationMethods();
        $this->seedProductForms();
        $this->seedMeasurementUnits();
    }

    private function seedAdministrationMethods(): void
    {
        $methods = [
            ['name' => 'Oral', 'abbreviation' => 'PO', 'provider_value' => 0],
            ['name' => 'Subcutaneous Injection', 'abbreviation' => 'SC', 'provider_value' => 1],
            ['name' => 'Intramuscular Injection', 'abbreviation' => 'IM', 'provider_value' => 2],
            ['name' => 'Intranasal', 'abbreviation' => 'IN', 'provider_value' => 3],
            ['name' => 'Topical', 'abbreviation' => 'TOP', 'provider_value' => 4],
            ['name' => 'Sublingual', 'abbreviation' => 'SL', 'provider_value' => 5],
            ['name' => 'Vaginal', 'abbreviation' => 'PV', 'provider_value' => 6],
            ['name' => 'Rectal', 'abbreviation' => 'PR', 'provider_value' => 7],
            ['name' => 'Transdermal', 'abbreviation' => 'TD', 'provider_value' => 8],
            ['name' => 'Intracavernosal', 'abbreviation' => 'IC', 'provider_value' => 9],
            ['name' => 'Buccal', 'abbreviation' => 'BUC', 'provider_value' => 10],
            ['name' => 'Intravenous', 'abbreviation' => 'IV', 'provider_value' => 11],
            ['name' => 'Ophthalmic', 'abbreviation' => 'OPH', 'provider_value' => 12],
            ['name' => 'Otic', 'abbreviation' => 'OT', 'provider_value' => 13],
            ['name' => 'Inhaled', 'abbreviation' => 'INH', 'provider_value' => 14],
            ['name' => 'Intradermal', 'abbreviation' => 'ID', 'provider_value' => 15],
        ];

        foreach ($methods as $position => $method) {
            AdministrationMethod::withTrashed()->firstOrCreate(
                ['slug' => Str::slug($method['name'])],
                [...$method, 'position' => $position],
            );
        }
    }

    private function seedProductForms(): void
    {
        $volumetric = [1, 2, 3, 4, 5, 11, 14, 15, 16, 18, 23];

        $forms = [
            ['name' => 'Orally Disintegrating Tablet (ODT)', 'provider_value' => 0],
            ['name' => 'Prefilled Syringe', 'provider_value' => 1],
            ['name' => 'Prefilled Pen', 'provider_value' => 2],
            ['name' => 'Vial (Lyophilized)', 'provider_value' => 3],
            ['name' => 'Vial (Reconstituted)', 'provider_value' => 4],
            ['name' => 'Vial (Compounded)', 'provider_value' => 5],
            ['name' => 'Nasal Inhaler', 'provider_value' => 6],
            ['name' => 'Tablet', 'provider_value' => 7],
            ['name' => 'Capsule', 'provider_value' => 8],
            ['name' => 'Troche', 'provider_value' => 9],
            ['name' => 'Gummy', 'provider_value' => 10],
            ['name' => 'Oral Suspension', 'provider_value' => 11],
            ['name' => 'Sublingual Drops', 'provider_value' => 12],
            ['name' => 'Oral Dissolvable Strip', 'provider_value' => 13],
            ['name' => 'Cream', 'provider_value' => 14],
            ['name' => 'Gel', 'provider_value' => 15],
            ['name' => 'Lotion', 'provider_value' => 16],
            ['name' => 'Patch', 'provider_value' => 17],
            ['name' => 'Transdermal Spray', 'provider_value' => 18],
            ['name' => 'Vaginal Suppository', 'provider_value' => 19],
            ['name' => 'Rectal Suppository', 'provider_value' => 20],
            ['name' => 'Sublingual Strips', 'provider_value' => 21],
            ['name' => 'Rapid Dissolve Tablet (RDT)', 'provider_value' => 22],
            ['name' => 'Tincture', 'provider_value' => 23],
            ['name' => 'Mini Troche', 'provider_value' => 24],
        ];

        foreach ($forms as $position => $form) {
            ProductForm::withTrashed()->firstOrCreate(
                ['slug' => Str::slug($form['name'])],
                [
                    ...$form,
                    'requires_volume' => in_array($form['provider_value'], $volumetric, true),
                    'position' => $position,
                ],
            );
        }
    }

    private function seedMeasurementUnits(): void
    {
        $units = [
            ['name' => 'Milligrams', 'abbreviation' => 'mg', 'provider_value' => 0],
            ['name' => 'Milliliters', 'abbreviation' => 'ml', 'provider_value' => 1],
            ['name' => 'Micrograms', 'abbreviation' => 'mcg', 'provider_value' => 2],
            ['name' => 'Grams', 'abbreviation' => 'g', 'provider_value' => 3],
            ['name' => 'International Units', 'abbreviation' => 'iu', 'provider_value' => 4],
            ['name' => 'Ounces', 'abbreviation' => 'oz', 'provider_value' => 5],
            ['name' => 'Pounds', 'abbreviation' => 'lb', 'provider_value' => 6],
            ['name' => 'Percent', 'abbreviation' => '%', 'provider_value' => 7],
            ['name' => 'Per Unit', 'abbreviation' => 'mg/unit', 'provider_value' => 8],
            ['name' => 'Per Application', 'abbreviation' => 'mg/application', 'provider_value' => 9],
        ];

        foreach ($units as $position => $unit) {
            MeasurementUnit::withTrashed()->firstOrCreate(
                ['abbreviation' => $unit['abbreviation']],
                [...$unit, 'position' => $position],
            );
        }
    }
}
