<script setup lang="ts">
import { formatNanoDollars, formatNanoDollarsPrecisely } from '@/lib/money';
import type { GenerationCost } from '@/types/generation';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'radix-vue';

const props = withDefaults(
    defineProps<{
        cost: GenerationCost;
        /** Opens a breakdown of the estimated and actual cost when clicked. */
        interactive?: boolean;
    }>(),
    { interactive: false },
);

function format(amount: number): string {
    return formatNanoDollars(amount, props.cost.currency, 'ceil');
}

function formatPrecisely(amount: number): string {
    return formatNanoDollarsPrecisely(amount, props.cost.currency);
}
</script>

<template>
    <PopoverRoot>
        <component
            :is="interactive ? PopoverTrigger : 'span'"
            class="flex items-center gap-2 uppercase tabular-nums"
            :class="{ 'cursor-pointer outline-none transition-colors hover:text-neutral-900 dark:hover:text-white': interactive }"
        >
            <span>Cost</span>
            <template v-if="cost.actual === null">
                <span v-if="cost.estimate !== null" class="text-neutral-900 dark:text-neutral-100">~{{ format(cost.estimate) }}</span>
            </template>
            <template v-else>
                <span v-if="cost.estimate !== null" class="line-through">
                    <span class="sr-only">Estimated</span>
                    ~{{ format(cost.estimate) }}
                </span>
                <span class="font-bold text-neutral-900 dark:text-neutral-100">
                    <span class="sr-only">Actual</span>
                    {{ format(cost.actual) }}
                </span>
            </template>
        </component>

        <PopoverPortal v-if="interactive">
            <PopoverContent
                align="end"
                :side-offset="8"
                class="z-50 w-64 border border-neutral-300 bg-neutral-50 font-mono text-[10px] uppercase tracking-[0.2em] text-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
            >
                <dl class="divide-y divide-neutral-300 dark:divide-neutral-700">
                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">Estimated</dt>
                        <dd class="tabular-nums">{{ cost.estimate === null ? '—' : `~${formatPrecisely(cost.estimate)}` }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">Actual</dt>
                        <dd class="font-bold tabular-nums">{{ cost.actual === null ? 'Pending' : formatPrecisely(cost.actual) }}</dd>
                    </div>
                </dl>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
