<?php

namespace App\Data\Capabilities;

use App\Enums\OptionType;

final readonly class BooleanOption extends Option
{
    public function __construct(
        string $key,
        string $label,
        public ?bool $default = null,
    ) {
        parent::__construct($key, $label);
    }

    public function type(): OptionType
    {
        return OptionType::Boolean;
    }

    public function default(): ?bool
    {
        return $this->default;
    }

    public function rules(): array
    {
        return ['sometimes', 'boolean'];
    }
}
