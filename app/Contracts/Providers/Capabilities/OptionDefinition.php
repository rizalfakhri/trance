<?php

namespace App\Contracts\Providers\Capabilities;

use App\Enums\OptionType;
use JsonSerializable;

interface OptionDefinition extends JsonSerializable
{
    public function key(): string;

    public function label(): string;

    public function type(): OptionType;

    /**
     * @return list<OptionChoice>
     */
    public function choices(): array;

    public function default(): string|int|bool|null;

    public function min(): ?int;

    public function max(): ?int;

    public function step(): ?int;

    /**
     * @return list<mixed>
     */
    public function rules(): array;
}
