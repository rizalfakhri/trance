<?php

namespace App\Data\Capabilities;

use App\Contracts\Providers\Capabilities\PromptConstraints;

final readonly class PromptConstraintsData implements PromptConstraints
{
    public function __construct(public int $maxLength) {}

    public function maxLength(): int
    {
        return $this->maxLength;
    }

    /**
     * @return array{maxLength: int}
     */
    public function jsonSerialize(): array
    {
        return ['maxLength' => $this->maxLength];
    }
}
