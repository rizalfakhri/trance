<?php

namespace App\Data\Capabilities;

use App\Contracts\Providers\Capabilities\ModelCapabilities;
use App\Contracts\Providers\Capabilities\OptionDefinition;
use App\Contracts\Providers\Capabilities\PromptConstraints;
use App\Contracts\Providers\Capabilities\ReferenceInputs;
use App\Enums\Modality;

final readonly class ModelCapabilitiesData implements ModelCapabilities
{
    /**
     * @param  list<OptionDefinition>  $options
     */
    public function __construct(
        public ?string $id,
        public string $label,
        public Modality $modality,
        public PromptConstraints $prompt,
        public array $options = [],
        public ?ReferenceInputs $references = null,
        public int $outputsPerRequest = 1,
    ) {}

    public function id(): ?string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function modality(): Modality
    {
        return $this->modality;
    }

    public function prompt(): PromptConstraints
    {
        return $this->prompt;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function option(string $key): ?OptionDefinition
    {
        foreach ($this->options as $option) {
            if ($option->key() === $key) {
                return $option;
            }
        }

        return null;
    }

    public function references(): ?ReferenceInputs
    {
        return $this->references;
    }

    public function outputsPerRequest(): int
    {
        return $this->outputsPerRequest;
    }

    /**
     * @return array{id: string|null, label: string, modality: string, prompt: PromptConstraints, options: list<OptionDefinition>, references: ReferenceInputs|null, outputsPerRequest: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'modality' => $this->modality->value,
            'prompt' => $this->prompt,
            'options' => $this->options,
            'references' => $this->references,
            'outputsPerRequest' => $this->outputsPerRequest,
        ];
    }
}
