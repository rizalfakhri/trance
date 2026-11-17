<?php

namespace App\Data\Capabilities;

use App\Enums\OptionType;

final readonly class NumberOption extends Option
{
    public function __construct(
        string $key,
        string $label,
        public int $minimum,
        public int $maximum,
        public int $increment = 1,
        public ?int $default = null,
    ) {
        parent::__construct($key, $label);
    }

    public function type(): OptionType
    {
        return OptionType::Number;
    }

    public function default(): ?int
    {
        return $this->default;
    }

    public function min(): int
    {
        return $this->minimum;
    }

    public function max(): int
    {
        return $this->maximum;
    }

    public function step(): int
    {
        return $this->increment;
    }

    public function rules(): array
    {
        return ['sometimes', 'integer', "min:{$this->minimum}", "max:{$this->maximum}", "multiple_of:{$this->increment}"];
    }
}
