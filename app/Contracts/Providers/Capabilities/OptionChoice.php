<?php

namespace App\Contracts\Providers\Capabilities;

use JsonSerializable;

interface OptionChoice extends JsonSerializable
{
    public function value(): string|int;

    public function label(): string;

    public function hint(): ?string;
}
