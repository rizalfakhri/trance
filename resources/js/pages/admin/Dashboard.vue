<script setup lang="ts">
import PromptOption from '@/components/PromptOption.vue';
import FrameGuides from '@/components/trance/FrameGuides.vue';
import ProviderLogo from '@/components/trance/ProviderLogo.vue';
import { DropdownMenuRadioGroup, DropdownMenuRadioItem } from '@/components/ui/dropdown-menu';
import { useSettlingFrame } from '@/composables/useSettlingFrame';
import TranceLayout from '@/layouts/TranceLayout.vue';
import { formatNanoDollarsPrecisely } from '@/lib/money';
import type { AdminCostReport, AdminProviderOption } from '@/types/admin';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    providers: AdminProviderOption[];
    provider: string;
    range: { from: string; to: string };
    report: AdminCostReport;
}>();

const { phase: framePhase, onDrawn: onFrameDrawn } = useSettlingFrame();

const selectedProvider = computed(() => props.providers.find((provider) => provider.name === props.provider) ?? props.providers[0]);
const numberFormatter = new Intl.NumberFormat('en-US');

const cards = computed(() => {
    const { summary } = props.report;
    const margin = summary.cost > 0 ? `${((summary.profit / summary.cost) * 100).toFixed(1)}% on cost` : '—';

    return [
        { label: 'Charged to users', value: money(summary.charged), hint: `${summary.succeeded} succeeded generations` },
        { label: 'Provider cost', value: money(summary.cost), hint: `${selectedProvider.value.label} billed us` },
        { label: 'Profit', value: money(summary.profit), hint: margin },
        { label: 'Generations', value: numberFormatter.format(summary.succeeded), hint: `${summary.failed} failed · ${summary.pending} pending` },
    ];
});

const tokenRows = computed(() => [
    { label: 'Input text', value: props.report.summary.inputTextTokens },
    { label: 'Input image', value: props.report.summary.inputImageTokens },
    { label: 'Output', value: props.report.summary.outputTokens },
    { label: 'Reasoning', value: props.report.summary.reasoningTokens },
]);

const promptOptionItemClass =
    'cursor-pointer gap-6 rounded-none py-2.5 pl-8 pr-4 text-[10px] focus:bg-neutral-900 focus:text-neutral-50 dark:focus:bg-neutral-100 dark:focus:text-neutral-900';

function money(amount: number): string {
    return formatNanoDollarsPrecisely(amount, 'usd');
}

function selectProvider(name: string): void {
    router.get(route('admin.dashboard'), { provider: name }, { preserveScroll: true, only: ['provider', 'report'] });
}
</script>

<template>
    <TranceLayout title="Admin">
        <main class="relative mx-auto flex w-full max-w-5xl flex-1 flex-col gap-20 px-6 py-20">
            <section class="relative" aria-label="Cost overview">
                <FrameGuides :phase="framePhase" @drawn="onFrameDrawn" />

                <span class="absolute -top-10 left-0 text-[10px] uppercase tracking-[0.25em] text-neutral-500">
                    Admin · {{ range.from }} → {{ range.to }} UTC
                </span>
                <div class="absolute -top-12 right-0">
                    <PromptOption label="Provider">
                        <template #value>
                            <ProviderLogo :provider="selectedProvider" />
                            {{ selectedProvider.label }}
                        </template>
                        <DropdownMenuRadioGroup :model-value="provider" @update:model-value="selectProvider">
                            <DropdownMenuRadioItem v-for="item in providers" :key="item.name" :value="item.name" :class="promptOptionItemClass">
                                <span class="flex items-center gap-2">
                                    <ProviderLogo :provider="item" />
                                    {{ item.label }}
                                </span>
                                <span v-if="!item.enabled" class="ml-auto opacity-50">Disabled</span>
                            </DropdownMenuRadioItem>
                        </DropdownMenuRadioGroup>
                    </PromptOption>
                </div>

                <div class="relative bg-neutral-50/80 backdrop-blur-[1px] dark:bg-neutral-950/80">
                    <dl class="grid grid-cols-1 border-b border-[--line] sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="card in cards"
                            :key="card.label"
                            class="space-y-3 border-b border-[--line] p-5 lg:border-b-0 lg:border-r lg:last:border-r-0 sm:[&:nth-child(odd)]:border-r"
                        >
                            <dt class="text-[9px] uppercase tracking-[0.25em] text-neutral-500">{{ card.label }}</dt>
                            <dd class="text-lg tabular-nums tracking-[0.02em]">{{ card.value }}</dd>
                            <p class="text-[9px] uppercase tracking-[0.2em] text-neutral-400 dark:text-neutral-500">{{ card.hint }}</p>
                        </div>
                    </dl>

                    <dl class="grid grid-cols-2 sm:grid-cols-4">
                        <div v-for="row in tokenRows" :key="row.label" class="space-y-1 px-5 py-3">
                            <dt class="text-[9px] uppercase tracking-[0.25em] text-neutral-500">{{ row.label }} tokens</dt>
                            <dd class="text-[11px] tabular-nums">{{ numberFormatter.format(row.value) }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="space-y-3" aria-label="Daily breakdown">
                <div class="flex items-center justify-between text-[10px] uppercase tracking-[0.25em] text-neutral-500">
                    <h2>By day</h2>
                    <p v-if="report.providerUsage.error" class="text-red-600 dark:text-red-400">
                        {{ selectedProvider.label }} usage unavailable: {{ report.providerUsage.error }}
                    </p>
                </div>
                <div class="overflow-x-auto border border-[--line] bg-neutral-50/80 dark:bg-neutral-950/80">
                    <table class="w-full text-left text-[10px] uppercase tracking-[0.15em]">
                        <thead class="border-b border-[--line] text-[9px] text-neutral-500">
                            <tr>
                                <th class="px-4 py-3 font-normal">Day</th>
                                <th class="px-4 py-3 text-right font-normal">Gens</th>
                                <th class="px-4 py-3 text-right font-normal">Charged</th>
                                <th class="px-4 py-3 text-right font-normal">Cost</th>
                                <th v-if="report.providerUsage.supported" class="px-4 py-3 text-right font-normal">Reported</th>
                                <th class="px-4 py-3 text-right font-normal">Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[--line] tabular-nums">
                            <tr v-if="report.daily.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-neutral-500">No completed generations in this period</td>
                            </tr>
                            <tr v-for="day in report.daily" :key="day.date">
                                <td class="px-4 py-3">{{ day.date }}</td>
                                <td class="px-4 py-3 text-right">{{ day.generations }}</td>
                                <td class="px-4 py-3 text-right">{{ money(day.charged) }}</td>
                                <td class="px-4 py-3 text-right">{{ money(day.cost) }}</td>
                                <td v-if="report.providerUsage.supported" class="px-4 py-3 text-right">
                                    <template v-if="day.reportedCost === null">—</template>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-2"
                                        :class="day.reportedCost === day.cost ? '' : 'text-red-600 dark:text-red-400'"
                                        :title="day.reportedCost === day.cost ? 'Matches our records' : 'Differs from our records'"
                                    >
                                        {{ money(day.reportedCost) }}
                                        <span aria-hidden="true">{{ day.reportedCost === day.cost ? '✓' : '≠' }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-bold">{{ money(day.profit) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-3" aria-label="Breakdown by user">
                <h2 class="text-[10px] uppercase tracking-[0.25em] text-neutral-500">By user</h2>
                <div class="overflow-x-auto border border-[--line] bg-neutral-50/80 dark:bg-neutral-950/80">
                    <table class="w-full text-left text-[10px] uppercase tracking-[0.15em]">
                        <thead class="border-b border-[--line] text-[9px] text-neutral-500">
                            <tr>
                                <th class="px-4 py-3 font-normal">User</th>
                                <th class="px-4 py-3 text-right font-normal">Gens</th>
                                <th class="px-4 py-3 text-right font-normal">Charged</th>
                                <th class="px-4 py-3 text-right font-normal">Cost</th>
                                <th class="px-4 py-3 text-right font-normal">Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[--line] tabular-nums">
                            <tr v-if="report.users.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-neutral-500">No users with completed generations</td>
                            </tr>
                            <tr v-for="user in report.users" :key="user.id">
                                <td class="px-4 py-3">
                                    <span class="block">{{ user.name }}</span>
                                    <span class="block normal-case tracking-normal text-neutral-500">{{ user.email }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">{{ user.generations }}</td>
                                <td class="px-4 py-3 text-right">{{ money(user.charged) }}</td>
                                <td class="px-4 py-3 text-right">{{ money(user.cost) }}</td>
                                <td class="px-4 py-3 text-right font-bold">{{ money(user.profit) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </TranceLayout>
</template>
