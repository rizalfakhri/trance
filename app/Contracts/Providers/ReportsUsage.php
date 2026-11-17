<?php

namespace App\Contracts\Providers;

use App\Data\ProviderUsageDay;
use App\Exceptions\Providers\ProviderException;
use Carbon\CarbonImmutable;

interface ReportsUsage
{
    /**
     * What the provider says it billed per UTC day, inclusive of both dates.
     *
     * @return list<ProviderUsageDay>
     *
     * @throws ProviderException
     */
    public function usage(CarbonImmutable $from, CarbonImmutable $to): array;
}
