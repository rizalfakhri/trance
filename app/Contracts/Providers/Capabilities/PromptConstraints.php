<?php

namespace App\Contracts\Providers\Capabilities;

use JsonSerializable;

interface PromptConstraints extends JsonSerializable
{
    public function maxLength(): int;
}
