<?php

namespace App\Contracts\Providers\Capabilities;

use App\Enums\Modality;
use JsonSerializable;

interface ReferenceInputs extends JsonSerializable
{
    public function maxCount(): int;

    /**
     * @return list<string>
     */
    public function acceptedMimeTypes(): array;

    public function modality(): Modality;
}
