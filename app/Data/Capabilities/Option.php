<?php

namespace App\Data\Capabilities;

use App\Contracts\Providers\Capabilities\OptionChoice;
use App\Contracts\Providers\Capabilities\OptionDefinition;

abstract readonly class Option implements OptionDefinition
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function choices(): array
    {
        return [];
    }

    public function min(): ?int
    {
        return null;
    }

    public function max(): ?int
    {
        return null;
    }

    public function step(): ?int
    {
        return null;
    }

    /**
     * @return array{key: string, label: string, type: string, choices: list<OptionChoice>, default: string|int|bool|null, min: int|null, max: int|null, step: int|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'type' => $this->type()->value,
            'choices' => $this->choices(),
            'default' => $this->default(),
            'min' => $this->min(),
            'max' => $this->max(),
            'step' => $this->step(),
        ];
    }
}
