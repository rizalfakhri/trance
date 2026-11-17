<?php

namespace App\Data\Capabilities;

use App\Contracts\Providers\Capabilities\ReferenceInputs;
use App\Enums\Modality;

final readonly class ReferenceInputsData implements ReferenceInputs
{
    /**
     * @param  list<string>  $acceptedMimeTypes
     */
    public function __construct(
        public int $maxCount,
        public array $acceptedMimeTypes,
        public Modality $modality = Modality::Image,
    ) {}

    public function maxCount(): int
    {
        return $this->maxCount;
    }

    public function acceptedMimeTypes(): array
    {
        return $this->acceptedMimeTypes;
    }

    public function modality(): Modality
    {
        return $this->modality;
    }

    /**
     * @return array{maxCount: int, acceptedMimeTypes: list<string>, modality: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'maxCount' => $this->maxCount,
            'acceptedMimeTypes' => $this->acceptedMimeTypes,
            'modality' => $this->modality->value,
        ];
    }
}
