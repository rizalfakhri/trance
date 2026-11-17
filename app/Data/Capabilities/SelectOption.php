<?php

namespace App\Data\Capabilities;

use App\Contracts\Providers\Capabilities\OptionChoice;
use App\Enums\OptionType;
use Illuminate\Validation\Rule;

final readonly class SelectOption extends Option
{
    /**
     * @param  list<OptionChoice>  $choices
     */
    public function __construct(
        string $key,
        string $label,
        public array $choices,
        public string|int|null $default = null,
    ) {
        parent::__construct($key, $label);
    }

    public function type(): OptionType
    {
        return OptionType::Select;
    }

    public function choices(): array
    {
        return $this->choices;
    }

    public function default(): string|int|null
    {
        return $this->default;
    }

    public function rules(): array
    {
        $values = array_map(fn (OptionChoice $choice): string|int => $choice->value(), $this->choices);

        return ['sometimes', Rule::in($values)];
    }
}
