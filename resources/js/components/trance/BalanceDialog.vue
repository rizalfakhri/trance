<script setup lang="ts">
import SquareDots from '@/components/trance/SquareDots.vue';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { useBalanceDialog } from '@/composables/useBalanceDialog';
import { formatNanoDollars } from '@/lib/money';
import { createUuid } from '@/lib/uuid';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const nanoDollarsPerDollar = 1_000_000_000;

const page = usePage<SharedData>();
const { isOpen, requiredAmount, close } = useBalanceDialog();

const selectedAmount = ref<number | null>(null);
const isToppingUp = ref(false);
const errorMessage = ref<string | null>(null);

const credits = computed(() => page.props.auth.credits);
const currency = computed(() => credits.value?.currency ?? 'usd');
const available = computed(() => credits.value?.available ?? 0);
const shortfall = computed(() => (requiredAmount.value === null ? 0 : Math.max(0, requiredAmount.value - available.value)));

function format(amount: number, rounding: 'floor' | 'ceil'): string {
    return formatNanoDollars(amount, currency.value, rounding);
}

function suggestedAmount(): number | null {
    const amounts = credits.value?.topUpAmounts ?? [];

    return amounts.find((amount) => amount * nanoDollarsPerDollar >= shortfall.value) ?? amounts.at(-1) ?? null;
}

function onOpenChange(open: boolean): void {
    if (!open && !isToppingUp.value) {
        close('dismissed');
    }
}

function topUp(): void {
    if (selectedAmount.value === null || isToppingUp.value) {
        return;
    }

    isToppingUp.value = true;
    errorMessage.value = null;

    router.post(
        route('balance.top-ups.store'),
        { amount: selectedAmount.value, idempotency_key: createUuid() },
        {
            only: ['auth'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => close('topped-up'),
            onError: () => {
                errorMessage.value = 'The top-up could not be completed.';
            },
            onFinish: () => {
                isToppingUp.value = false;
            },
        },
    );
}

watch(isOpen, (open) => {
    if (open) {
        selectedAmount.value = suggestedAmount();
        errorMessage.value = null;
    }
});
</script>

<template>
    <Dialog :open="isOpen" @update:open="onOpenChange">
        <DialogContent
            class="trance-surface max-w-sm gap-0 rounded-none border-neutral-300 bg-neutral-50 p-0 font-mono text-neutral-900 shadow-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 sm:rounded-none"
        >
            <div class="space-y-6 p-6">
                <div class="space-y-2">
                    <DialogTitle class="text-[10px] font-normal uppercase tracking-[0.25em] text-neutral-500">Balance</DialogTitle>
                    <p class="text-2xl tabular-nums tracking-[0.05em]">{{ format(available, 'floor') }}</p>
                    <DialogDescription
                        v-if="shortfall > 0"
                        class="text-[10px] uppercase leading-relaxed tracking-[0.2em] text-red-600 dark:text-red-400"
                        role="alert"
                    >
                        You don't have enough balance to generate this image. Top up your credits to continue.
                    </DialogDescription>
                    <DialogDescription v-else class="text-[10px] uppercase leading-relaxed tracking-[0.2em] text-neutral-500">
                        Add credits to your balance.
                    </DialogDescription>
                </div>

                <div role="radiogroup" aria-label="Top-up amount" class="grid grid-cols-4 gap-1.5">
                    <button
                        v-for="amount in credits?.topUpAmounts ?? []"
                        :key="amount"
                        type="button"
                        role="radio"
                        :aria-checked="selectedAmount === amount"
                        class="h-10 border text-[11px] tabular-nums tracking-[0.1em] transition-colors"
                        :class="
                            selectedAmount === amount
                                ? 'border-neutral-900 bg-neutral-900 text-neutral-50 dark:border-neutral-100 dark:bg-neutral-100 dark:text-neutral-900'
                                : 'border-neutral-300 hover:border-neutral-500 dark:border-neutral-700'
                        "
                        @click="selectedAmount = amount"
                    >
                        {{ format(amount * nanoDollarsPerDollar, 'floor') }}
                    </button>
                </div>

                <p v-if="errorMessage" class="text-[9px] uppercase tracking-[0.2em] text-red-600 dark:text-red-400" role="alert">
                    {{ errorMessage }}
                </p>
            </div>

            <div class="flex items-center justify-end gap-1.5 border-t border-neutral-300 px-6 py-3 dark:border-neutral-700">
                <button
                    type="button"
                    class="h-8 px-4 text-[10px] uppercase tracking-[0.25em] text-neutral-500 transition-colors hover:text-neutral-900 disabled:opacity-30 dark:hover:text-neutral-100"
                    :disabled="isToppingUp"
                    @click="close('dismissed')"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="flex h-8 min-w-28 items-center justify-center bg-neutral-900 px-4 text-[10px] uppercase tracking-[0.25em] text-neutral-50 transition hover:bg-neutral-700 disabled:cursor-wait dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-white"
                    :disabled="selectedAmount === null || isToppingUp"
                    @click="topUp"
                >
                    <SquareDots v-if="isToppingUp" />
                    <template v-else>Top up</template>
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
