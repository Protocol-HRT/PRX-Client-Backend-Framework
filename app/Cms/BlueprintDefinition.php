<?php

namespace App\Cms;

use App\Cms\Sections\SectionBlueprint;
use App\Contracts\Cms\SectionDefinition;

/**
 * Adapts a code-defined SectionBlueprint to the SectionDefinition contract
 * so code and admin-defined (flexible) section types share one registry.
 */
class BlueprintDefinition implements SectionDefinition
{
    public function __construct(private readonly SectionBlueprint $blueprint) {}

    public function type(): string
    {
        return $this->blueprint->type()->value;
    }

    public function label(): string
    {
        return $this->blueprint->label();
    }

    public function icon(): string
    {
        return $this->blueprint->icon();
    }

    public function description(): ?string
    {
        return $this->blueprint->description();
    }

    public function defaults(): array
    {
        return $this->blueprint->defaults();
    }

    /**
     * @return array<string, string>
     */
    public function layoutDefaults(): array
    {
        return $this->blueprint->layoutDefaults();
    }

    public function formSchema(): array
    {
        return $this->blueprint->formSchema();
    }

    public function fieldKinds(): array
    {
        return $this->blueprint->fieldKinds();
    }

    public function resolveData(array $data): array
    {
        return $this->blueprint->resolveData($data);
    }

    /**
     * @return list<string>
     */
    public function presentationKeys(): array
    {
        return $this->blueprint->presentationKeys();
    }

    public function isFlexible(): bool
    {
        return false;
    }
}
