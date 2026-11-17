<script setup lang="ts">
import FrameGuides from '@/components/trance/FrameGuides.vue';
import SquareDots from '@/components/trance/SquareDots.vue';
import { useSettlingFrame } from '@/composables/useSettlingFrame';
import TranceLayout from '@/layouts/TranceLayout.vue';
import { formatNanoDollars } from '@/lib/money';
import type { TransactionItem, TransactionSummary } from '@/types/transactions';
import { Link, WhenVisible } from '@inertiajs/vue3';

const props = defineProps<{
    summary: TransactionSummary;
    transactions: TransactionItem[];
    nextPage: number | null;
}>();

const { phase: framePhase, onDrawn: onFrameDrawn } = useSettlingFrame();

const dateFormatter = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const summaryItems = [
    { label: 'Available', amount: () => props.summary.available },
    { label: 'Reserved', amount: () => props.summary.held },
    { label: 'Added', amount: () => props.summary.topUps },
    { label: 'Spent', amount: () => props.summary.spent },
];

const badgeClasses: Record<TransactionItem['type'], string> = {
    top_up: 'border-[#C8D2FE] bg-[#C8D2FE] text-neutral-900',
    credit: 'border-[#C8D2FE] text-indigo-700 dark:text-[#C8D2FE]',
    usage: 'border-neutral-900 bg-neutral-900 text-neutral-50 dark:border-neutral-100 dark:bg-neutral-100 dark:text-neutral-900',
    hold: 'border-dashed border-neutral-400 text-neutral-500 dark:border-neutral-600',
    release: 'border-neutral-300 text-neutral-500 dark:border-neutral-700',
    refund: 'border-indigo-600 text-indigo-700 dark:border-[#C8D2FE] dark:text-[#C8D2FE]',
    adjustment: 'border-amber-500 text-amber-700 dark:text-amber-400',
};

function formatAmount(transaction: TransactionItem): string {
    const sign = transaction.amount > 0 ? '+' : transaction.amount < 0 ? '-' : '';

    return `${sign}${formatNanoDollars(Math.abs(transaction.amount), transaction.currency, 'ceil')}`;
}
</script>

<template>
    <TranceLayout title="Transactions">
        <main class="relative mx-auto flex w-full max-w-3xl flex-1 flex-col px-6 py-20">
            <section class="relative" aria-label="Transactions">
                <FrameGuides :phase="framePhase" @drawn="onFrameDrawn" />

                <span class="absolute -top-10 left-0 text-[10px] uppercase tracking-[0.25em] text-neutral-500">Transactions</span>

                <div class="relative bg-neutral-50/80 backdrop-blur-[1px] dark:bg-neutral-950/80">
                    <dl class="grid grid-cols-2 border-b border-[--line] sm:grid-cols-4">
                        <div
                            v-for="item in summaryItems"
                            :key="item.label"
                            class="space-y-2 border-[--line] p-5 [&:not(:last-child)]:border-r max-sm:[&:nth-child(-n+2)]:border-b max-sm:[&:nth-child(2)]:border-r-0"
                        >
                            <dt class="text-[9px] uppercase tracking-[0.25em] text-neutral-500">{{ item.label }}</dt>
                            <dd class="text-lg tabular-nums tracking-[0.05em]">{{ formatNanoDollars(item.amount(), summary.currency, 'floor') }}</dd>
                        </div>
                    </dl>

                    <p v-if="transactions.length === 0" class="px-5 py-16 text-center text-[10px] uppercase tracking-[0.25em] text-neutral-500">
                        No transactions yet
                    </p>

                    <ol v-else class="divide-y divide-[--line]">
                        <li
                            v-for="transaction in transactions"
                            :key="transaction.id"
                            class="flex items-center gap-4 px-5 py-3 text-[10px] uppercase tracking-[0.2em]"
                        >
                            <span class="flex w-28 shrink-0">
                                <span
                                    class="inline-flex h-5 items-center border px-1.5 text-[9px] uppercase tracking-[0.2em]"
                                    :class="badgeClasses[transaction.type]"
                                >
                                    {{ transaction.label }}
                                </span>
                            </span>
                            <div class="flex min-w-0 flex-1 items-center gap-2 text-[9px]">
                                <Link
                                    v-if="transaction.generation"
                                    :href="transaction.generation.url"
                                    class="truncate text-neutral-500 transition-colors hover:text-neutral-900 dark:hover:text-neutral-100"
                                    :title="transaction.generation.prompt"
                                >
                                    {{ transaction.generation.prompt }}
                                </Link>
                                <span v-else-if="transaction.description" class="truncate text-neutral-500">{{ transaction.description }}</span>
                                <span v-if="transaction.isPending" class="shrink-0 text-neutral-400 dark:text-neutral-500">· Pending</span>
                            </div>
                            <time
                                :datetime="transaction.createdAt"
                                class="hidden shrink-0 text-[9px] text-neutral-400 dark:text-neutral-500 sm:block"
                            >
                                {{ dateFormatter.format(new Date(transaction.createdAt)) }}
                            </time>
                            <span
                                class="w-24 shrink-0 text-right tabular-nums"
                                :class="transaction.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-neutral-900 dark:text-neutral-100'"
                            >
                                {{ formatAmount(transaction) }}
                            </span>
                        </li>
                    </ol>

                    <WhenVisible
                        v-if="nextPage"
                        :key="nextPage"
                        :data="['transactions', 'nextPage']"
                        :params="{ data: { page: nextPage }, preserveUrl: true }"
                    >
                        <template #fallback>
                            <div class="flex justify-center py-4 text-neutral-500">
                                <SquareDots />
                            </div>
                        </template>
                    </WhenVisible>
                </div>
            </section>
        </main>
    </TranceLayout>
</template>
