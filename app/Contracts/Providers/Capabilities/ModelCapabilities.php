<?php

namespace App\Contracts\Providers\Capabilities;

use App\Enums\Modality;
use JsonSerializable;

interface ModelCapabilities extends JsonSerializable
{
    public function id(): ?string;

    public function label(): string;

    public function modality(): Modality;

    public function prompt(): PromptConstraints;

    /**
     * @return list<OptionDefinition>
     */
    public function options(): array;

    public function option(string $key): ?OptionDefinition;

    public function references(): ?ReferenceInputs;

    public function outputsPerRequest(): int;
}
