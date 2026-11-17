<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Providers\Provider;
use App\Http\Controllers\Controller;
use App\Registries\ProviderRegistry;
use App\Reports\ProviderCostReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const REPORT_DAYS = 30;

    public function __invoke(Request $request, ProviderRegistry $registry, ProviderCostReport $report): Response
    {
        $providers = $registry->all();
        $provider = $request->string('provider')->toString();

        if (! isset($providers[$provider])) {
            $provider = array_key_first($providers);
        }

        $to = CarbonImmutable::now('UTC')->startOfDay();
        $from = $to->subDays(self::REPORT_DAYS - 1);

        return Inertia::render('admin/Dashboard', [
            'providers' => array_values(array_map(fn (Provider $item): array => [
                'name' => $item->name(),
                'label' => $item->label(),
                'logoUrl' => $item->capabilities()->logoUrl(),
                'darkLogoUrl' => $item->capabilities()->darkLogoUrl(),
                'enabled' => $registry->isEnabled($item->name()),
            ], $providers)),
            'provider' => $provider,
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'report' => $report->build($provider, $from, $to),
        ]);
    }
}
