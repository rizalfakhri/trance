import { formatCents, nanoDollarsToCents } from '@/lib/money';
import type { Credits } from '@/types';
import { onMounted, ref, watch, type Ref } from 'vue';

export interface CreditChange {
    id: number;
    direction: 'increase' | 'decrease';
    label: string;
}

let lastSeenCents: number | null = null;
let nextChangeId = 0;

/**
 * Emits a change whenever the displayed balance moves, including across page visits: the layout
 * remounts per page, so the last seen amount lives at module level.
 */
export function useCreditChanges(credits: Ref<Credits | null>) {
    const change = ref<CreditChange | null>(null);

    function observe(current: Credits | null): void {
        if (current === null) {
            lastSeenCents = null;

            return;
        }

        const cents = nanoDollarsToCents(current.available, 'floor');
        const difference = lastSeenCents === null ? 0 : cents - lastSeenCents;

        lastSeenCents = cents;

        if (difference !== 0) {
            change.value = {
                id: nextChangeId++,
                direction: difference > 0 ? 'increase' : 'decrease',
                label: `${difference > 0 ? '+' : '-'}${formatCents(Math.abs(difference), current.currency)}`,
            };
        }
    }

    function clear(changeId: number): void {
        if (change.value?.id === changeId) {
            change.value = null;
        }
    }

    onMounted(() => observe(credits.value));
    watch(credits, (current) => observe(current), { deep: true });

    return { change, clear };
}
