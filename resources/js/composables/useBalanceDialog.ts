import { readonly, ref } from 'vue';

export type BalanceDialogOutcome = 'topped-up' | 'dismissed';

const isOpen = ref(false);
const requiredAmount = ref<number | null>(null);

let resolveOutcome: ((outcome: BalanceDialogOutcome) => void) | null = null;

function close(outcome: BalanceDialogOutcome): void {
    isOpen.value = false;
    resolveOutcome?.(outcome);
    resolveOutcome = null;
}

/**
 * @param required Nano-dollars the pending action needs, so the dialog can show the shortfall.
 */
function open(required: number | null = null): Promise<BalanceDialogOutcome> {
    resolveOutcome?.('dismissed');
    requiredAmount.value = required;
    isOpen.value = true;

    return new Promise((resolve) => {
        resolveOutcome = resolve;
    });
}

export function useBalanceDialog() {
    return {
        isOpen: readonly(isOpen),
        requiredAmount: readonly(requiredAmount),
        open,
        close,
    };
}
