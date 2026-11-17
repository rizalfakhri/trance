<?php

namespace App\Data\Capabilities;

use App\Contracts\Providers\Capabilities\OptionChoice;

final readonly class OptionChoiceData implements OptionChoice
{
    public function __construct(
        public string|int $value,
        public string $label,
        public ?string $hint = null,
    ) {}

    public function value(): string|int
    {
        return $this->value;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function hint(): ?string
    {
        return $this->hint;
    }

    /**
     * @return array{value: string|int, label: string, hint: string|null}
     */
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'label' => $this->label, 'hint' => $this->hint];
    }
}
