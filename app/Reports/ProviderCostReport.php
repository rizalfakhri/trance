<?php

namespace App\Reports;

use App\Contracts\Providers\ReportsUsage;
use App\Data\ProviderUsageDay;
use App\Enums\GenerationStatus;
use App\Exceptions\Providers\ProviderException;
use App\Models\Generation;
use App\Registries\ProviderRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * What users were charged, what the provider cost us and the profit, from the costs recorded on
 * each generation. Days are UTC completion days, the same days providers bill on.
 */
final readonly class ProviderCostReport
{
    private const PROVIDER_USAGE_CACHE_SECONDS = 60;

    public function __construct(private ProviderRegistry $registry) {}

    /**
     * @return array{
     *     summary: array{charged: int, cost: int, profit: int, succeeded: int, failed: int, pending: int, inputTextTokens: int, inputImageTokens: int, outputTokens: int, reasoningTokens: int},
     *     daily: list<array{date: string, generations: int, charged: int, cost: int, profit: int, reportedCost: int|null, reportedGenerations: int|null}>,
     *     users: list<array{id: int, name: string, email: string, generations: int, charged: int, cost: int, profit: int}>,
     *     providerUsage: array{supported: bool, error: string|null}
     * }
     */
    public function build(string $provider, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $completed = fn (): Builder => Generation::query()
            ->where('provider', $provider)
            ->where('status', GenerationStatus::Succeeded)
            ->whereBetween('completed_at', [$from->startOfDay(), $to->endOfDay()]);

        $totals = $completed()->selectRaw(
            'count(*) as generations, coalesce(sum(charge_total), 0) as charged, coalesce(sum(cost_total), 0) as cost,
            coalesce(sum(input_text_tokens), 0) as input_text_tokens, coalesce(sum(input_image_tokens), 0) as input_image_tokens,
            coalesce(sum(output_tokens), 0) as output_tokens, coalesce(sum(reasoning_tokens), 0) as reasoning_tokens'
        )->first();

        $statusCounts = Generation::query()
            ->where('provider', $provider)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        [$providerUsage, $providerUsageError] = $this->providerUsage($provider, $from, $to);
        $reportedByDate = collect($providerUsage ?? [])->keyBy(fn (ProviderUsageDay $day): string => $day->date);

        $daily = $completed()
            ->selectRaw('date(completed_at) as day, count(*) as generations, sum(charge_total) as charged, sum(cost_total) as cost')
            ->groupBy(DB::raw('date(completed_at)'))
            ->orderByDesc('day')
            ->get()
            ->keyBy('day');

        $dates = $daily->keys()->merge($reportedByDate->filter(fn (ProviderUsageDay $day): bool => $day->generations > 0)->keys())->unique()->sortDesc()->values();

        return [
            'summary' => [
                'charged' => (int) $totals->charged,
                'cost' => (int) $totals->cost,
                'profit' => (int) $totals->charged - (int) $totals->cost,
                'succeeded' => (int) $totals->generations,
                'failed' => (int) ($statusCounts[GenerationStatus::Failed->value] ?? 0),
                'pending' => (int) ($statusCounts[GenerationStatus::Queued->value] ?? 0) + (int) ($statusCounts[GenerationStatus::Processing->value] ?? 0),
                'inputTextTokens' => (int) $totals->input_text_tokens,
                'inputImageTokens' => (int) $totals->input_image_tokens,
                'outputTokens' => (int) $totals->output_tokens,
                'reasoningTokens' => (int) $totals->reasoning_tokens,
            ],
            'daily' => $dates->map(function (string $date) use ($daily, $reportedByDate): array {
                $day = $daily->get($date);
                $reported = $reportedByDate->get($date);

                return [
                    'date' => $date,
                    'generations' => (int) ($day->generations ?? 0),
                    'charged' => (int) ($day->charged ?? 0),
                    'cost' => (int) ($day->cost ?? 0),
                    'profit' => (int) ($day->charged ?? 0) - (int) ($day->cost ?? 0),
                    'reportedCost' => $reported?->cost,
                    'reportedGenerations' => $reported?->generations,
                ];
            })->all(),
            'users' => $completed()
                ->join('users', 'users.id', '=', 'generations.user_id')
                ->selectRaw('users.id, users.name, users.email, count(*) as generations, sum(charge_total) as charged, sum(cost_total) as cost')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderByDesc('charged')
                ->get()
                ->map(fn (Generation $row): array => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'generations' => (int) $row->generations,
                    'charged' => (int) $row->charged,
                    'cost' => (int) $row->cost,
                    'profit' => (int) $row->charged - (int) $row->cost,
                ])
                ->all(),
            'providerUsage' => [
                'supported' => $providerUsage !== null || $providerUsageError !== null,
                'error' => $providerUsageError,
            ],
        ];
    }

    /**
     * @return array{0: list<ProviderUsageDay>|null, 1: string|null}
     */
    private function providerUsage(string $provider, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $reporter = $this->registry->has($provider) ? $this->registry->get($provider) : null;

        if (! $reporter instanceof ReportsUsage) {
            return [null, null];
        }

        try {
            $usage = Cache::remember(
                "admin.provider-usage.{$provider}.{$from->toDateString()}.{$to->toDateString()}",
                self::PROVIDER_USAGE_CACHE_SECONDS,
                fn (): array => $reporter->usage($from, $to),
            );

            return [$usage, null];
        } catch (ProviderException $exception) {
            return [null, $exception->getMessage()];
        }
    }
}
