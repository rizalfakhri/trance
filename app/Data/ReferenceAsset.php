<?php

namespace App\Data;

final readonly class ReferenceAsset
{
    public function __construct(
        public string $url,
        public string $mimeType,
    ) {}
}
