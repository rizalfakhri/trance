<?php

use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    usePixelForgeTestConfig();
    $this->travelTo(now()->setDate(2026, 11, 17)->setTime(12, 0));
    $this->admin = User::factory()->admin()->create();
});

function completedGeneration(User $user, string $provider, string $completedAt, int $cost, int $charge): Generation
{
    return Generation::factory()->for($user)->succeeded()->create([
        'provider' => $provider,
        'cost_total' => $cost,
        'fee_total' => $charge - $cost,
        'charge_total' => $charge,
        'output_tokens' => 1100,
        'completed_at' => $completedAt,
    ]);
}

function fakeUsageReport(array $days): void
{
    Http::fake([PIXELFORGE_TEST_URL.'/usage*' => Http::response(['object' => 'usage', 'data' => $days, 'total' => []])]);
}

test('guests are sent to log in and regular users are forbidden', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
});

test('the report totals charges, provider cost and profit for the selected provider only', function () {
    fakeUsageReport([]);
    $user = User::factory()->create();
    completedGeneration($user, 'pixelforge', '2026-11-17 09:00:00', 51_107_500, 63_884_375);
    completedGeneration($user, 'pixelforge', '2026-11-16 09:00:00', 323_120_000, 403_900_000);
    completedGeneration($user, 'openai', '2026-11-17 09:00:00', 100_000_000, 125_000_000);
    Generation::factory()->for($user)->failed()->create(['provider' => 'pixelforge']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['provider' => 'pixelforge']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('provider', 'pixelforge')
            ->where('report.summary.charged', 467_784_375)
            ->where('report.summary.cost', 374_227_500)
            ->where('report.summary.profit', 93_556_875)
            ->where('report.summary.succeeded', 2)
            ->where('report.summary.failed', 1)
            ->where('report.daily.0.date', '2026-11-17')
            ->where('report.daily.0.charged', 63_884_375)
            ->where('report.daily.1.date', '2026-11-16')
            ->where('report.users.0.email', $user->email)
            ->where('report.users.0.profit', 93_556_875));
});

test('daily provider costs are cross checked against the provider usage report', function () {
    fakeUsageReport([
        ['date' => '2026-11-17', 'generations' => 1, 'input_text_tokens' => 4, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 567, 'cost_usd' => '0.051107500'],
        ['date' => '2026-11-16', 'generations' => 1, 'input_text_tokens' => 0, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'cost_usd' => '0.400000000'],
    ]);
    $user = User::factory()->create();
    completedGeneration($user, 'pixelforge', '2026-11-17 09:00:00', 51_107_500, 63_884_375);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['provider' => 'pixelforge']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.providerUsage', ['supported' => true, 'error' => null])
            ->where('report.daily.0.date', '2026-11-17')
            ->where('report.daily.0.reportedCost', 51_107_500)
            ->where('report.daily.0.cost', 51_107_500)
            ->where('report.daily.1.date', '2026-11-16')
            ->where('report.daily.1.cost', 0)
            ->where('report.daily.1.reportedCost', 400_000_000));
});

test('an unavailable usage report is shown as an error instead of failing the page', function () {
    Http::fake([PIXELFORGE_TEST_URL.'/usage*' => Http::response(['error' => ['code' => 'overloaded', 'message' => 'Busy']], 503)]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['provider' => 'pixelforge']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('report.providerUsage', ['supported' => true, 'error' => 'Busy']));
});

test('providers without a usage report skip the cross check', function () {
    Http::fake();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['provider' => 'openai']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('provider', 'openai')
            ->where('report.providerUsage', ['supported' => false, 'error' => null]));

    Http::assertNothingSent();
});

test('admins see the admin link in the shared props', function () {
    $this->actingAs($this->admin)
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', true));
});
