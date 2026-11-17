<script setup lang="ts">
import FrameGuides from '@/components/trance/FrameGuides.vue';
import GenerationCostSummary from '@/components/trance/GenerationCostSummary.vue';
import ProviderLogo from '@/components/trance/ProviderLogo.vue';
import SquareDots from '@/components/trance/SquareDots.vue';
import { useBalanceDialog } from '@/composables/useBalanceDialog';
import { useSettlingFrame } from '@/composables/useSettlingFrame';
import TranceLayout from '@/layouts/TranceLayout.vue';
import { formatClock } from '@/lib/time';
import type { GenerationStatus, GenerationView } from '@/types/generation';
import { router, usePoll } from '@inertiajs/vue3';
import { Download, RotateCcw } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type StreamedEvent = GenerationView['events'][number] & { enterOrder: number; vanishOrder: number };

const props = defineProps<{
    generation: GenerationView;
}>();

const pollInterval = 2000;
const maxVisibleEvents = 8;
const initialVisibleEvents = 4;

const statusLabels: Record<GenerationStatus, string> = {
    queued: 'Queued',
    processing: 'Generating',
    succeeded: 'Completed',
    failed: 'Failed',
};

const { phase: promptFramePhase, onDrawn: onPromptFrameDrawn } = useSettlingFrame();
const { phase: outputFramePhase, onDrawn: onOutputFrameDrawn } = useSettlingFrame();

const now = ref(Date.now());
const visibleEvents = ref<StreamedEvent[]>([]);
const promptElement = ref<HTMLElement | null>(null);
const isPromptExpanded = ref(false);
const isPromptTruncated = ref(false);

const seenEventIds = new Set<number>();

let clockTimer: ReturnType<typeof setInterval> | undefined;
let promptResizeObserver: ResizeObserver | undefined;

const { start: startPolling, stop: stopPolling } = usePoll(pollInterval, { only: ['generation', 'auth'] }, { autoStart: false });

const output = computed(() => props.generation.outputs[0] ?? null);
const isAwaitingOutput = computed(() => props.generation.status === 'succeeded' && output.value === null);
const isPending = computed(() => props.generation.status === 'queued' || props.generation.status === 'processing' || isAwaitingOutput.value);
const statusLabel = computed(() => (isAwaitingOutput.value ? 'Saving image' : statusLabels[props.generation.status]));
const newestFirstEvents = computed(() => [...visibleEvents.value].reverse());
const shortGenerationId = computed(() => props.generation.id.slice(0, 8));

const isRetrying = ref(false);
const retryError = ref<string | null>(null);
const { open: openBalanceDialog } = useBalanceDialog();

function retryGeneration(): void {
    if (isRetrying.value) {
        return;
    }

    isRetrying.value = true;
    retryError.value = null;

    router.post(
        route('generations.retry', { generation: props.generation.id }),
        {},
        {
            only: ['generation', 'auth'],
            preserveState: true,
            preserveScroll: true,
            onError: async (errors) => {
                retryError.value = errors.balance ?? errors.generation ?? 'The generation could not be retried.';

                if (errors.balance && (await openBalanceDialog(props.generation.cost.estimate)) === 'topped-up') {
                    isRetrying.value = false;
                    retryGeneration();
                }
            },
            onFinish: () => {
                isRetrying.value = false;
            },
        },
    );
}

const elapsedLabel = computed(() => {
    const startedAt = Date.parse(props.generation.createdAt);
    const endedAt = props.generation.completedAt === null ? now.value : Date.parse(props.generation.completedAt);

    return formatClock(endedAt - startedAt);
});

const outputPlaceholderStyle = computed(() => {
    const size =
        output.value?.width && output.value.height ? { width: output.value.width, height: output.value.height } : props.generation.expectedSize;
    const { width, height } = size ?? { width: 1, height: 1 };

    return {
        aspectRatio: `${width} / ${height}`,
        maxWidth: width > height ? '100%' : width === height ? '28rem' : '20rem',
    };
});

function streamNewEvents(events: GenerationView['events'], limit = maxVisibleEvents): void {
    const fresh = events.filter((event) => !seenEventIds.has(event.id)).slice(-limit);

    fresh.forEach((event) => seenEventIds.add(event.id));
    visibleEvents.value = [
        ...visibleEvents.value,
        ...fresh.map((event, index) => ({ ...event, enterOrder: fresh.length - 1 - index, vanishOrder: index })),
    ].slice(-maxVisibleEvents);
}

function removeEvent(eventId: number): void {
    visibleEvents.value = visibleEvents.value.filter((event) => event.id !== eventId);
}

function measurePromptTruncation(): void {
    const element = promptElement.value;

    if (element && !isPromptExpanded.value) {
        isPromptTruncated.value = element.scrollHeight > element.clientHeight + 1;
    }
}

watch(
    () => props.generation.events,
    (events) => streamNewEvents(events),
);

watch(isPending, (pending) => (pending ? startPolling() : stopPolling()));

onMounted(() => {
    streamNewEvents(props.generation.events, initialVisibleEvents);

    if (isPending.value) {
        startPolling();
    }

    clockTimer = setInterval(() => {
        now.value = Date.now();
    }, 1000);

    if (promptElement.value) {
        promptResizeObserver = new ResizeObserver(measurePromptTruncation);
        promptResizeObserver.observe(promptElement.value);
    }
});

onBeforeUnmount(() => {
    promptResizeObserver?.disconnect();
    clearInterval(clockTimer);
});
</script>

<template>
    <TranceLayout title="Generation">
        <main class="relative mx-auto flex w-full max-w-3xl flex-1 flex-col gap-20 px-6 py-20">
            <section class="relative" aria-label="Prompt">
                <FrameGuides :phase="promptFramePhase" @drawn="onPromptFrameDrawn" />

                <span class="absolute -top-10 left-0 text-[10px] uppercase tracking-[0.25em] text-neutral-500">Prompt</span>
                <span class="absolute -top-10 right-0 flex items-center gap-3 text-[10px] uppercase tabular-nums tracking-[0.25em] text-neutral-500">
                    <GenerationCostSummary :cost="generation.cost" interactive />
                    <span aria-hidden="true" class="h-2.5 w-px bg-[--line-strong]" />
                    <span>ID {{ shortGenerationId }}</span>
                </span>

                <div class="relative bg-neutral-50/80 backdrop-blur-[1px] dark:bg-neutral-950/80">
                    <div class="p-5">
                        <p
                            id="generation-prompt"
                            ref="promptElement"
                            class="whitespace-pre-wrap break-words text-[13px] uppercase leading-relaxed tracking-[0.2em]"
                            :class="{ 'line-clamp-5': !isPromptExpanded }"
                            v-text="generation.prompt"
                        />
                        <button
                            v-if="isPromptTruncated"
                            type="button"
                            aria-controls="generation-prompt"
                            :aria-expanded="isPromptExpanded"
                            class="mt-3 text-[9px] uppercase tracking-[0.25em] text-neutral-500 transition-colors hover:text-neutral-900 dark:hover:text-neutral-100"
                            @click="isPromptExpanded = !isPromptExpanded"
                        >
                            {{ isPromptExpanded ? 'View less' : 'View more' }}
                        </button>
                    </div>

                    <div class="space-y-4 px-5 pb-4">
                        <dl class="flex flex-wrap items-center gap-1.5 text-[9px] uppercase tracking-[0.2em]">
                            <div class="flex h-6 items-center gap-3 border border-[--line] px-2">
                                <dt class="text-neutral-400 dark:text-neutral-500">Provider</dt>
                                <dd class="flex items-center gap-2">
                                    <ProviderLogo :provider="generation.provider" />
                                    {{ generation.provider.label }}
                                </dd>
                            </div>
                            <div v-if="generation.model" class="flex h-6 items-center gap-3 border border-[--line] px-2">
                                <dt class="text-neutral-400 dark:text-neutral-500">Model</dt>
                                <dd>{{ generation.model.label }}</dd>
                            </div>
                            <div
                                v-for="option in generation.options"
                                :key="option.key"
                                class="flex h-6 items-center gap-3 border border-[--line] px-2"
                            >
                                <dt class="text-neutral-400 dark:text-neutral-500">{{ option.label }}</dt>
                                <dd>{{ option.value }}</dd>
                            </div>
                        </dl>

                        <div v-if="generation.references.length" class="space-y-2">
                            <p class="text-[9px] uppercase tracking-[0.2em] text-neutral-400 dark:text-neutral-500">Reference images</p>
                            <div class="flex flex-wrap gap-2">
                                <img
                                    v-for="reference in generation.references"
                                    :key="reference.id"
                                    :src="reference.url"
                                    alt=""
                                    class="size-14 border border-[--line] object-cover"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="relative" aria-label="Output">
                <FrameGuides :phase="outputFramePhase" @drawn="onOutputFrameDrawn" />

                <span class="absolute -top-10 left-0 text-[10px] uppercase tracking-[0.25em] text-neutral-500">Output</span>

                <div class="relative bg-neutral-50/80 backdrop-blur-[1px] dark:bg-neutral-950/80">
                    <div class="flex items-center justify-between gap-4 px-5 pt-4 text-[10px] uppercase tracking-[0.25em] text-neutral-500">
                        <Transition name="status-swap" mode="out-in">
                            <p :key="statusLabel" class="flex items-center gap-3" role="status">
                                <span
                                    :class="
                                        generation.status === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-neutral-900 dark:text-neutral-100'
                                    "
                                >
                                    {{ statusLabel }}
                                </span>
                                <SquareDots v-if="isPending" class="text-[--beam]" />
                            </p>
                        </Transition>
                        <span class="tabular-nums">{{ elapsedLabel }}</span>
                    </div>

                    <TransitionGroup
                        tag="ol"
                        name="webhook-message"
                        class="webhook-stream pointer-events-none absolute inset-x-5 top-12 z-0 flex h-56 flex-col overflow-hidden"
                        role="log"
                        aria-live="polite"
                    >
                        <li
                            v-for="event in newestFirstEvents"
                            :key="event.id"
                            class="webhook-message flex items-center gap-3 py-0.5 text-[9px] uppercase tracking-[0.2em]"
                            :style="{ '--enter-order': event.enterOrder, '--vanish-order': event.vanishOrder }"
                            @animationend.self="removeEvent(event.id)"
                        >
                            <span class="tabular-nums text-neutral-400 dark:text-neutral-600">{{ formatClock(event.offsetMs, true) }}</span>
                            <span class="w-[11ch] text-neutral-500">{{ event.status }}</span>
                            <span class="truncate text-neutral-900 dark:text-neutral-100">{{ event.message }}</span>
                        </li>
                    </TransitionGroup>

                    <div class="relative z-10 p-5 pt-20">
                        <div v-if="generation.status === 'failed'" class="space-y-4">
                            <p class="text-[10px] uppercase leading-relaxed tracking-[0.2em] text-red-600 dark:text-red-400" role="alert">
                                {{ generation.error?.message ?? 'The generation failed.' }} Your balance was not charged.
                            </p>
                            <p
                                v-if="retryError"
                                class="text-[10px] uppercase leading-relaxed tracking-[0.2em] text-red-600 dark:text-red-400"
                                role="alert"
                            >
                                {{ retryError }}
                            </p>
                            <div class="flex justify-center">
                                <button
                                    type="button"
                                    :disabled="isRetrying"
                                    :aria-busy="isRetrying"
                                    class="flex h-8 min-w-28 items-center justify-center gap-2 bg-neutral-900 px-4 text-[10px] uppercase tracking-[0.25em] text-neutral-50 transition hover:bg-neutral-700 disabled:cursor-wait dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-white"
                                    @click="retryGeneration"
                                >
                                    <SquareDots v-if="isRetrying" />
                                    <template v-else>
                                        <RotateCcw class="size-3" :stroke-width="2.25" aria-hidden="true" />
                                        Retry
                                    </template>
                                </button>
                            </div>
                        </div>
                        <div
                            v-else
                            class="relative mx-auto w-full overflow-hidden border border-[--line] bg-neutral-50 dark:bg-neutral-950"
                            :class="{ 'output-shimmer': isPending }"
                            :style="outputPlaceholderStyle"
                            :aria-busy="isPending"
                        >
                            <Transition name="output-reveal">
                                <img v-if="output" :src="output.url" :alt="generation.prompt" class="absolute inset-0 size-full object-cover" />
                            </Transition>
                        </div>

                        <Transition name="status-swap">
                            <div v-if="output" class="mt-4 flex justify-center">
                                <a
                                    :href="output.downloadUrl"
                                    download
                                    class="flex h-8 items-center gap-2 bg-neutral-900 px-4 text-[10px] uppercase tracking-[0.25em] text-neutral-50 transition hover:bg-neutral-700 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-white"
                                >
                                    <Download class="size-3" :stroke-width="2.25" aria-hidden="true" />
                                    Download
                                </a>
                            </div>
                        </Transition>
                    </div>
                </div>
            </section>
        </main>
    </TranceLayout>
</template>

<style scoped>
.output-shimmer {
    position: relative;
    overflow: hidden;
    background-image: linear-gradient(color-mix(in srgb, var(--line) 22%, transparent), color-mix(in srgb, var(--line) 22%, transparent));
}

/* A soft band of light sweeping across the placeholder while the image is pending. */
.output-shimmer::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        100deg,
        transparent 20%,
        color-mix(in srgb, var(--beam) 10%, transparent) 42%,
        color-mix(in srgb, var(--beam) 22%, transparent) 50%,
        color-mix(in srgb, var(--beam) 10%, transparent) 58%,
        transparent 80%
    );
    transform: translateX(-100%);
    animation: output-shimmer 2.2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

@keyframes output-shimmer {
    to {
        transform: translateX(100%);
    }
}

.status-swap-enter-active,
.status-swap-leave-active {
    transition:
        opacity 220ms ease-out,
        transform 220ms ease-out,
        filter 220ms ease-out;
}

.status-swap-enter-from {
    opacity: 0;
    transform: translateY(4px);
    filter: blur(2px);
}

.status-swap-leave-to {
    opacity: 0;
    transform: translateY(-4px);
    filter: blur(2px);
}

/* Newest messages sit at the top; older ones are pushed down, fade out and pass behind the output. */
.webhook-stream {
    -webkit-mask-image: linear-gradient(to bottom, #000 45%, transparent);
    mask-image: linear-gradient(to bottom, #000 45%, transparent);
}

/* A batch cascades in newest-first from the top; the oldest at the bottom vanish first. */
.webhook-message {
    animation: webhook-message-vanish 3200ms cubic-bezier(0.4, 0, 1, 1) both;
    animation-delay: calc(2600ms + var(--vanish-order) * 90ms);
}

@keyframes webhook-message-vanish {
    to {
        opacity: 0;
        filter: blur(3px);
    }
}

.webhook-message-enter-active {
    transition:
        opacity 360ms ease-out,
        transform 360ms cubic-bezier(0.2, 0.8, 0.2, 1),
        filter 360ms ease-out;
    transition-delay: calc(var(--enter-order) * 90ms);
}

.webhook-message-enter-from {
    opacity: 0;
    transform: translateY(-8px);
    filter: blur(3px);
}

.webhook-message-leave-active {
    display: none;
}

.webhook-message-move {
    transition: transform 360ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

/* The finished image develops out of the blur, echoing the page transitions. */
.output-reveal-enter-active {
    transition:
        filter 1400ms cubic-bezier(0.16, 1, 0.3, 1),
        opacity 1400ms cubic-bezier(0.33, 1, 0.68, 1),
        transform 1400ms cubic-bezier(0.16, 1, 0.3, 1);
}

.output-reveal-enter-from {
    filter: blur(16px);
    opacity: 0;
    transform: scale(1.03);
}

@media (prefers-reduced-motion: reduce) {
    .output-shimmer::after {
        animation: none;
    }

    .output-reveal-enter-active,
    .webhook-message-enter-active,
    .webhook-message-move {
        transition: none;
    }
}
</style>
