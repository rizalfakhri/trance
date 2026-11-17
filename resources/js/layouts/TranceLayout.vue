<script setup lang="ts">
import BalanceDialog from '@/components/trance/BalanceDialog.vue';
import GridBackground from '@/components/trance/GridBackground.vue';
import { useBalanceDialog } from '@/composables/useBalanceDialog';
import { useCreditChanges } from '@/composables/useCreditChanges';
import { formatNanoDollars } from '@/lib/money';
import { randomBetween, randomInteger } from '@/lib/trance/beams';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{
    title: string;
}>();

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
const credits = computed(() => page.props.auth.credits);

const menuItems = computed(() => [
    { route: 'gallery', label: 'Gallery' },
    { route: 'transactions', label: 'Transactions' },
    ...(page.props.auth.isAdmin ? [{ route: 'admin.dashboard', label: 'Admin' }] : []),
]);

function isCurrentRoute(name: string): boolean {
    return page.url.split('?')[0] === route(name, undefined, false);
}
const { open: openBalanceDialog } = useBalanceDialog();
const { change: creditChange, clear: clearCreditChange } = useCreditChanges(credits);
const creditSplashColors = ['#C8D2FE', '#4F46E5', '#F87171', '#FBBF24', '#FAFAFA'];

const creditSplashParticles = computed(() =>
    creditChange.value
        ? Array.from({ length: 6 }, (_, index) => ({
              id: `${creditChange.value?.id}-${index}`,
              angle: randomBetween(0, 360),
              distance: randomBetween(36, 64),
              size: Math.round(randomBetween(2, 3)),
              color: creditSplashColors[randomInteger(0, creditSplashColors.length - 1)],
          }))
        : [],
);

const formattedCredits = computed(() => (credits.value ? formatNanoDollars(credits.value.available, credits.value.currency, 'floor') : null));

/** The page blurs in when it mounts and blurs out when a full page visit starts. */
const isEntering = ref(true);
const isLeaving = ref(false);

let stopListeningForVisitStart: VoidFunction | undefined;
let stopListeningForVisitFinish: VoidFunction | undefined;

/**
 * Clear the entry class once it has played or been replaced (e.g. by a shake),
 * so it cannot replay when the other animation's class is removed.
 */
function onSurfaceAnimationEnd(event: AnimationEvent): void {
    if (event.target === event.currentTarget && event.animationName === 'screen-blur-in') {
        isEntering.value = false;
    }
}

onMounted(() => {
    stopListeningForVisitStart = router.on('start', (event) => {
        const { visit } = event.detail;

        // Partial reloads keep the current page, so they should not blur it away.
        if (visit.only.length === 0 && visit.except.length === 0) {
            isLeaving.value = true;
        }
    });

    // Normally this page unmounts before a visit finishes. If it is still here (a
    // cancelled, failed or state-preserving visit), bring it back into focus.
    stopListeningForVisitFinish = router.on('finish', () => {
        if (isLeaving.value) {
            isLeaving.value = false;
            isEntering.value = true;
        }
    });
});

onBeforeUnmount(() => {
    stopListeningForVisitStart?.();
    stopListeningForVisitFinish?.();
});
</script>

<template>
    <div
        class="trance-surface relative flex min-h-screen flex-col overflow-hidden bg-neutral-50 font-mono text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
        :class="{ 'screen-blur-in': isEntering && !isLeaving, 'screen-blur-out': isLeaving }"
        @animationend="onSurfaceAnimationEnd"
        @animationcancel="onSurfaceAnimationEnd"
    >
        <Head :title="title" />

        <GridBackground />

        <header
            class="relative z-10 mx-auto flex w-full max-w-2xl items-center justify-between px-6 pt-8 text-[11px] uppercase tracking-[0.2em] sm:px-0"
        >
            <div class="flex items-center gap-3">
                <Link :href="route('home')">Trance</Link>
                <template v-if="user">
                    <span aria-hidden="true" class="text-neutral-300 dark:text-neutral-700">/</span>
                    <nav aria-label="Main" class="flex items-center gap-5">
                        <Link
                            v-for="item in menuItems"
                            :key="item.route"
                            :href="route(item.route)"
                            :aria-current="isCurrentRoute(item.route) ? 'page' : undefined"
                            class="transition hover:text-neutral-900 dark:hover:text-white"
                            :class="isCurrentRoute(item.route) ? 'text-neutral-900 dark:text-neutral-100' : 'text-neutral-500'"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </template>
            </div>
            <div class="flex items-center gap-6">
                <template v-if="user">
                    <button
                        v-if="formattedCredits"
                        type="button"
                        class="relative flex items-center gap-2 uppercase tracking-[0.2em] text-neutral-500 transition-colors hover:text-neutral-900 dark:hover:text-white"
                        @click="openBalanceDialog()"
                    >
                        <span aria-hidden="true" class="size-1.5 bg-[--beam] shadow-[0_0_6px_var(--beam-glow)]" />
                        <span class="sr-only">Credits</span>
                        <span class="tabular-nums text-neutral-900 dark:text-neutral-100">{{ formattedCredits }}</span>
                        <span
                            v-if="creditChange"
                            :key="creditChange.id"
                            class="pointer-events-none absolute -inset-x-2 -inset-y-1 z-20"
                            role="status"
                        >
                            <span
                                class="credit-change absolute inset-0 flex items-center justify-center"
                                :class="creditChange.direction === 'decrease' ? 'bg-red-600 text-white' : 'bg-[#C8D2FE] text-neutral-900'"
                                @animationend.self="clearCreditChange(creditChange.id)"
                            >
                                <span class="credit-change-amount font-bold tabular-nums">{{ creditChange.label }}</span>
                            </span>
                            <span
                                v-for="particle in creditSplashParticles"
                                :key="particle.id"
                                aria-hidden="true"
                                class="credit-change-particle absolute left-1/2 top-1/2"
                                :style="{
                                    '--angle': `${particle.angle}deg`,
                                    '--distance': `${particle.distance}px`,
                                    width: `${particle.size}px`,
                                    height: `${particle.size}px`,
                                    backgroundColor: particle.color,
                                }"
                            />
                        </span>
                    </button>
                    <Link
                        :href="route('password.edit')"
                        class="max-w-[16ch] truncate text-neutral-500 transition hover:text-neutral-900 dark:hover:text-white"
                    >
                        {{ user.name }}
                    </Link>
                </template>
                <template v-else>
                    <Link :href="route('login')" class="text-neutral-500 transition hover:text-neutral-900 dark:hover:text-white">Log in</Link>
                    <Link :href="route('register')" class="text-neutral-500 transition hover:text-neutral-900 dark:hover:text-white">Register</Link>
                </template>
            </div>
        </header>

        <slot />

        <BalanceDialog v-if="user" />
    </div>
</template>

<style scoped>
.credit-change {
    animation: credit-change-overlay 900ms both;
}

.credit-change-amount {
    display: inline-block;
    animation: credit-change-amount 900ms both;
}

.credit-change-particle {
    opacity: 0;
    animation: credit-change-particle 900ms both;
}

/* Pop in (~200ms), confetti at the peak, hold ~500ms, then gone. */
@keyframes credit-change-overlay {
    0% {
        opacity: 0;
        transform: scale(0.8);
        animation-timing-function: cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    22%,
    78% {
        opacity: 1;
        transform: scale(1);
    }
    90%,
    100% {
        opacity: 0;
        transform: scale(1);
    }
}

@keyframes credit-change-amount {
    0% {
        transform: scale(0.4);
        animation-timing-function: cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    22%,
    100% {
        transform: scale(1);
    }
}

@keyframes credit-change-particle {
    0%,
    21% {
        opacity: 0;
        transform: translate(-50%, -50%) rotate(var(--angle)) translateX(0);
    }
    22% {
        opacity: 1;
        animation-timing-function: cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    100% {
        opacity: 0;
        transform: translate(-50%, -50%) rotate(var(--angle)) translateX(var(--distance));
    }
}

@media (prefers-reduced-motion: reduce) {
    .credit-change {
        animation-name: credit-change-overlay-fade;
    }

    @keyframes credit-change-overlay-fade {
        0%,
        90%,
        100% {
            opacity: 0;
        }
        22%,
        78% {
            opacity: 1;
        }
    }

    .credit-change-amount {
        animation: none;
    }

    .credit-change-particle {
        display: none;
    }
}
</style>
