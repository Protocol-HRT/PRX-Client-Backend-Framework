<?php

namespace App\Console\Commands\PrescribeRx;

use App\Services\PrescribeRx\Client;
use App\Services\PrescribeRx\Exceptions\PrescribeRxException;
use App\Settings\IntegrationSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('prescribe-rx:ping {--type-id= : Optionally fetch the schema for a specific encounter type id}')]
#[Description('Probe the prescribe-rx API: list encounter types, optionally fetch one schema, and report counts.')]
class Ping extends Command
{
    public function handle(IntegrationSettings $settings, Client $client): int
    {
        $this->line('');
        $this->info('PrescribeRx connectivity check');
        $this->line(str_repeat('─', 60));

        $this->line(sprintf('  Enabled:         %s', $settings->prescribe_rx_enabled ? 'yes' : 'no'));
        $this->line(sprintf('  Environment:     %s', $settings->prescribe_rx_environment));
        $this->line(sprintf(
            '  Token:           %s',
            $settings->prescribe_rx_api_token
                ? str_repeat('•', 8).' (set, '.strlen($settings->prescribe_rx_api_token).' chars)'
                : 'not set',
        ));
        $this->line(sprintf('  Stub mode:       %s', config('prescribe-rx.stub') ? 'on' : 'off'));
        $this->line(sprintf('  Base URL:        %s', config('prescribe-rx.urls.'.$settings->prescribe_rx_environment)));
        $this->line('');

        try {
            $types = $client->listEncounterTypes();
            $this->info(sprintf('✓ listEncounterTypes returned %d type(s)', count($types)));
            foreach (array_slice($types, 0, 5) as $t) {
                $this->line(sprintf('    - %s  (slug: %s, id: %s)', $t->name, $t->slug, $t->id));
            }
        } catch (PrescribeRxException $e) {
            $this->error('✗ listEncounterTypes failed: '.$e->getMessage());
            if ($e->errors !== null) {
                $this->line('  errors: '.json_encode($e->errors));
            }

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('✗ Unexpected: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($typeId = (string) $this->option('type-id')) {
            try {
                $schema = $client->getEncounterTypeSchema($typeId);
                $this->info(sprintf('✓ getEncounterTypeSchema(%s)', $typeId));
                $this->line(sprintf('    encounter_type:  %s', $schema->encounter_type['name'] ?? '?'));
                $this->line(sprintf('    total_steps:     %d', $schema->meta['total_steps'] ?? count($schema->steps)));
                $this->line(sprintf('    total_fields:    %d', $schema->meta['total_fields'] ?? count($schema->field_slugs)));
                $this->line(sprintf('    required_slugs:  %s', implode(', ', array_slice($schema->required_slugs, 0, 6))));
            } catch (PrescribeRxException $e) {
                $this->error('✗ getEncounterTypeSchema failed: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->line('');
        $this->info('All checks passed.');

        return self::SUCCESS;
    }
}
