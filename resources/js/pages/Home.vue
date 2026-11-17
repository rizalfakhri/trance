<script setup lang="ts">
import PromptOption from '@/components/PromptOption.vue';
import FrameGuides, { type FramePhase } from '@/components/trance/FrameGuides.vue';
import ProviderLogo from '@/components/trance/ProviderLogo.vue';
import SquareDots from '@/components/trance/SquareDots.vue';
import { DropdownMenuRadioGroup, DropdownMenuRadioItem } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useBalanceDialog } from '@/composables/useBalanceDialog';
import TranceLayout from '@/layouts/TranceLayout.vue';
import { createCaretMirror, measureCaret, type CaretRect } from '@/lib/caret';
import { createGeneration, GenerationRequestError } from '@/lib/generationRequests';
import { clearPendingPrompt, continueGenerationQuery, savePendingPrompt, takePendingPrompt } from '@/lib/pendingPrompt';
import { isMacPlatform as detectMacPlatform } from '@/lib/platform';
import { defaultOptionValues, formatOptionValue, optionChoices, parseOptionValue } from '@/lib/providerOptions';
import { fetchGenerationQuote, formatQuoteTotal, type QuotePayload } from '@/lib/quotes';
import { wait } from '@/lib/time';
import { prefersReducedMotion, randomInteger } from '@/lib/trance/beams';
import { isHttpUrl } from '@/lib/url';
import type { SharedData } from '@/types';
import type { GenerationQuote, OptionDefinition, OptionValue, ProviderCapabilities } from '@/types/providers';
import { router, usePage } from '@inertiajs/vue3';
import { Command } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/** editing: prompt is interactive. submitting: waiting on the backend. dismissing: playing the hand-off before redirecting. */
type GenerationPhase = 'editing' | 'submitting' | 'awaitingBalance' | 'dismissing';

interface ReferenceImage {
    id: number;
    file: File;
    previewUrl: string;
}

const props = defineProps<{
    providers: ProviderCapabilities[];
    defaultProvider: string;
}>();

const quoteDebounceDuration = 300;
const tipHoldDuration = 12000;
/** Border trace on submit; matches the `frame-redraw` animation in app.css. */
const submitTraceDuration = 900;
/** Fade-out of the frame guides after the trace. */
const frameRemovalDuration = 200;
/** Length of the reverse reveal and screen blur before redirecting to the generation. */
const dismissDuration = 900;
const resumeDelay = 1400;

/** Keycap token rendered as the Lucide command icon instead of text. */
const commandKeyToken = 'command';

/** Shared styling for items inside the prompt option dropdowns. */
const promptOptionItemClass =
    'cursor-pointer gap-6 rounded-none py-2.5 pl-8 pr-4 text-[10px] focus:bg-neutral-900 focus:text-neutral-50 dark:focus:bg-neutral-100 dark:focus:text-neutral-900';

const prompt = ref('');
const selectedProviderName = ref(props.defaultProvider);
const selectedProvider = computed(() => props.providers.find((provider) => provider.provider === selectedProviderName.value) ?? props.providers[0]);
const selectedModelId = ref<string | null>(selectedProvider.value.models[0]?.id ?? null);
const selectedModel = computed(
    () => selectedProvider.value.models.find((model) => model.id === selectedModelId.value) ?? selectedProvider.value.models[0],
);
const optionValues = ref<Record<string, OptionValue>>(defaultOptionValues(selectedModel.value));
const quote = ref<GenerationQuote | null>(null);
const submitError = ref<string | null>(null);
const isQuoting = ref(false);
const referenceImages = ref<ReferenceImage[]>([]);
const referenceUrls = ref<string[]>([]);
const referenceUrlDraft = ref('');
const referenceUrlError = ref<string | null>(null);
const referenceImagesInput = ref<HTMLInputElement | null>(null);

const promptFrameElement = ref<HTMLFormElement | null>(null);
const promptTextarea = ref<HTMLTextAreaElement | null>(null);
const isPromptExpanded = ref(false);
const isPromptFocused = ref(false);
const isScreenShaking = ref(false);
const framePhase = ref<FramePhase>('idle');
const generationPhase = ref<GenerationPhase>('editing');
const isReducedMotion = ref(false);

const caret = ref<CaretRect | null>(null);
const caretBlinkKey = ref(0);

const isMacPlatform = ref(true);
const activeTipIndex = ref(0);

let nextReferenceImageId = 0;
let caretMirror: HTMLDivElement | null = null;
let tipRotationTimer: ReturnType<typeof setInterval> | undefined;
let quoteDebounceTimer: ReturnType<typeof setTimeout> | undefined;
let quoteAbortController: AbortController | undefined;
let lastQuotedPayloadKey: string | null = null;

const page = usePage<SharedData>();
const isAuthenticated = computed(() => Boolean(page.props.auth.user));
const isGenerating = computed(() => generationPhase.value !== 'editing');
const { open: openBalanceDialog } = useBalanceDialog();
const hasInsufficientBalance = computed(() => {
    const credits = page.props.auth.credits;

    return isAuthenticated.value && quote.value !== null && credits !== null && quote.value.total > credits.available;
});
const maxPromptLength = computed(() => selectedModel.value.prompt.maxLength);
const maxReferenceImages = computed(() => selectedModel.value.references?.maxCount ?? 0);
const acceptedReferenceTypes = computed(() => selectedModel.value.references?.acceptedMimeTypes ?? []);
const referenceCount = computed(() => referenceImages.value.length + referenceUrls.value.length);
const hasMaxReferenceImages = computed(() => referenceCount.value >= maxReferenceImages.value);
const remainingPromptCharacters = computed(() => maxPromptLength.value - prompt.value.length);
const optionSummary = computed(() =>
    [
        selectedProvider.value.models.length > 1 ? selectedModel.value.label : selectedProvider.value.label,
        ...selectedModel.value.options.map((option) => formatOptionValue(option, optionValues.value[option.key])),
    ].join(' / '),
);
const formattedQuote = computed(() => (quote.value ? formatQuoteTotal(quote.value) : null));

type GenerateButtonState = 'empty' | 'quoting' | 'ready' | 'submitting';

const generateButtonState = computed<GenerateButtonState>(() => {
    if (isGenerating.value) {
        return 'submitting';
    }

    if (!prompt.value.trim()) {
        return 'empty';
    }

    return isQuoting.value ? 'quoting' : 'ready';
});

const generateButtonClasses: Record<GenerateButtonState, string> = {
    empty: 'cursor-not-allowed opacity-30',
    quoting: 'cursor-wait bg-neutral-700 dark:bg-neutral-300',
    ready: 'hover:bg-neutral-700 dark:hover:bg-white',
    submitting: 'cursor-wait',
};
const modifierKeyLabel = computed(() => (isMacPlatform.value ? commandKeyToken : 'Ctrl'));

/** Tips shown in the footer while the prompt is open; `keys` render as keycaps before the text. */
const promptTips = computed<Array<{ keys: string[]; text: string }>>(() => [
    { keys: [modifierKeyLabel.value, 'Enter'], text: 'to generate' },
    { keys: ['Esc'], text: 'to step out of the prompt' },
    { keys: [], text: 'Name the subject, mood, light and lens' },
    { keys: [], text: 'Reference images steer style and composition' },
    { keys: [], text: 'Low quality renders fastest for drafts' },
]);

const activeTip = computed(() => promptTips.value[activeTipIndex.value]);

/** Switches to a random tip other than the one currently shown. */
function showRandomTip(): void {
    const offset = randomInteger(1, promptTips.value.length - 1);

    activeTipIndex.value = (activeTipIndex.value + offset) % promptTips.value.length;
}

function selectProvider(providerName: string): void {
    if (providerName === selectedProviderName.value || !props.providers.find((provider) => provider.provider === providerName)?.enabled) {
        return;
    }

    selectedProviderName.value = providerName;
    selectedModelId.value = selectedProvider.value.models[0]?.id ?? null;
    applyModelDefaults();
}

function selectModel(modelId: string): void {
    if (modelId === selectedModelId.value) {
        return;
    }

    selectedModelId.value = modelId;
    applyModelDefaults();
}

function applyModelDefaults(): void {
    optionValues.value = defaultOptionValues(selectedModel.value);
    referenceImages.value.slice(maxReferenceImages.value).forEach((image) => URL.revokeObjectURL(image.previewUrl));
    referenceImages.value = referenceImages.value.slice(0, maxReferenceImages.value);
    referenceUrls.value = referenceUrls.value.slice(0, Math.max(0, maxReferenceImages.value - referenceImages.value.length));
}

function addReferenceUrl(): void {
    const url = referenceUrlDraft.value.trim();

    if (url === '') {
        return;
    }

    if (!isHttpUrl(url)) {
        referenceUrlError.value = 'Enter an http(s) image URL.';

        return;
    }

    if (hasMaxReferenceImages.value) {
        referenceUrlError.value = `Up to ${maxReferenceImages.value} reference images.`;

        return;
    }

    if (!referenceUrls.value.includes(url)) {
        referenceUrls.value = [...referenceUrls.value, url];
    }

    referenceUrlDraft.value = '';
    referenceUrlError.value = null;
}

function removeReferenceUrl(url: string): void {
    referenceUrls.value = referenceUrls.value.filter((candidate) => candidate !== url);
}

function setOptionValue(option: OptionDefinition, value: string): void {
    optionValues.value = { ...optionValues.value, [option.key]: parseOptionValue(option, value) };
}

function scheduleQuote(): void {
    clearTimeout(quoteDebounceTimer);

    if (isAuthenticated.value) {
        quoteDebounceTimer = setTimeout(refreshQuote, quoteDebounceDuration);
    }
}

async function refreshQuote(): Promise<void> {
    const payload: QuotePayload = {
        provider: selectedProvider.value.provider,
        model: selectedModel.value.id,
        prompt: prompt.value,
        options: optionValues.value,
        reference_types: referenceImages.value.map((image) => image.file.type),
        reference_urls: referenceUrls.value,
    };
    const payloadKey = JSON.stringify(payload);

    if (payloadKey === lastQuotedPayloadKey) {
        return;
    }

    quoteAbortController?.abort();
    quoteAbortController = new AbortController();
    isQuoting.value = true;

    try {
        quote.value = await fetchGenerationQuote(payload, quoteAbortController.signal);
        lastQuotedPayloadKey = payloadKey;
        isQuoting.value = false;
    } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
            quote.value = null;
            lastQuotedPayloadKey = null;
            isQuoting.value = false;
        }
    }
}

function setPromptExpanded(expanded: boolean): void {
    if (isPromptExpanded.value === expanded) {
        return;
    }

    isPromptExpanded.value = expanded;

    if (!isReducedMotion.value) {
        framePhase.value = 'hidden';
    }
}

function updateCaret(): void {
    const textarea = promptTextarea.value;

    if (!textarea || !isPromptFocused.value || textarea.selectionStart !== textarea.selectionEnd) {
        caret.value = null;

        return;
    }

    caretMirror ??= createCaretMirror(textarea);
    caret.value = measureCaret(textarea, caretMirror);
    caretBlinkKey.value++;
}

function onDocumentSelectionChange(): void {
    if (document.activeElement === promptTextarea.value) {
        updateCaret();
    }
}

function onPromptFocus(): void {
    isPromptFocused.value = true;
    updateCaret();

    if (!isReducedMotion.value && !isPromptExpanded.value) {
        isScreenShaking.value = true;
    }

    setPromptExpanded(true);
}

function onPromptKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        submitGeneration();

        return;
    }

    if (event.key === 'Escape' && !isGenerating.value) {
        event.preventDefault();
        promptTextarea.value?.blur();
        setPromptExpanded(false);
    }
}

function onPromptBlur(): void {
    isPromptFocused.value = false;
    caret.value = null;
}

/**
 * Collapse the prompt once interaction moves outside it (option dropdowns
 * render in a portal, so they count as inside), unless it holds input.
 */
function collapsePromptIfIdle(target: EventTarget | null): void {
    const element = promptFrameElement.value;

    if (!element || isGenerating.value || !(target instanceof Element)) {
        return;
    }

    if (element.contains(target) || target.closest('[data-radix-popper-content-wrapper]')) {
        return;
    }

    if (!prompt.value.trim() && referenceCount.value === 0) {
        setPromptExpanded(false);
    }
}

function onDocumentPointerDown(event: PointerEvent): void {
    collapsePromptIfIdle(event.target);
}

function onDocumentFocusIn(event: FocusEvent): void {
    collapsePromptIfIdle(event.target);
}

/** Adds picked images up to the reference limit; extra files are ignored. */
function onReferenceImagesChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const remainingSlots = maxReferenceImages.value - referenceCount.value;
    const files = Array.from(input.files ?? [])
        .filter((file) => acceptedReferenceTypes.value.includes(file.type))
        .slice(0, remainingSlots);

    referenceImages.value.push(
        ...files.map((file) => ({
            id: nextReferenceImageId++,
            file,
            previewUrl: URL.createObjectURL(file),
        })),
    );

    input.value = '';
}

function openReferenceImagePicker(): void {
    if (!hasMaxReferenceImages.value) {
        referenceImagesInput.value?.click();
    }
}

function removeReferenceImage(imageId: number): void {
    const image = referenceImages.value.find((referenceImage) => referenceImage.id === imageId);

    if (image) {
        URL.revokeObjectURL(image.previewUrl);
    }

    referenceImages.value = referenceImages.value.filter((referenceImage) => referenceImage.id !== imageId);
}

function clearReferenceImages(): void {
    referenceImages.value.forEach((image) => URL.revokeObjectURL(image.previewUrl));
    referenceImages.value = [];
}

/** Once the prompt finishes resizing, redraw the frame around its new size. */
function onPromptFrameTransitionEnd(event: TransitionEvent): void {
    if (event.target !== promptFrameElement.value || event.propertyName !== 'max-width' || framePhase.value !== 'hidden') {
        return;
    }

    framePhase.value = 'drawing';
}

function onFrameDrawn(): void {
    if (framePhase.value === 'drawing') {
        framePhase.value = 'idle';
    }
}

function savePromptForLater(): Promise<void> {
    return savePendingPrompt({
        prompt: prompt.value,
        provider: selectedProvider.value.provider,
        model: selectedModel.value.id,
        options: { ...optionValues.value },
        referenceImages: referenceImages.value.map((image) => image.file),
        referenceUrls: [...referenceUrls.value],
    }).catch(() => undefined);
}

async function startGeneration(): Promise<string> {
    const url = await createGeneration({
        provider: selectedProvider.value.provider,
        model: selectedModel.value.id,
        prompt: prompt.value.trim(),
        options: optionValues.value,
        references: referenceImages.value.map((image) => image.file),
        referenceUrls: referenceUrls.value,
    });

    await clearPendingPrompt().catch(() => undefined);

    return url;
}

async function deferGenerationUntilAuthenticated(): Promise<string> {
    await savePromptForLater();

    return route('login', continueGenerationQuery);
}

function startSubmitTrace(): void {
    generationPhase.value = 'submitting';

    if (!isReducedMotion.value) {
        framePhase.value = 'tracing';
    }
}

/**
 * Submit sequence: the button shows loading dots while a light traces the
 * border, then the frame is removed, the prompt un-reveals in reverse under a
 * screen blur, and the page hands off to the generation. Without enough
 * balance the prompt is saved and the balance dialog opens; a top-up resumes
 * the sequence, dismissing it returns to editing.
 */
async function submitGeneration(): Promise<void> {
    if (isGenerating.value || isQuoting.value || !prompt.value.trim()) {
        return;
    }

    promptTextarea.value?.blur();
    submitError.value = null;
    startSubmitTrace();

    while (hasInsufficientBalance.value) {
        await Promise.all([savePromptForLater(), wait(isReducedMotion.value ? 0 : submitTraceDuration)]);
        framePhase.value = 'idle';
        generationPhase.value = 'awaitingBalance';

        if ((await openBalanceDialog(quote.value?.total ?? null)) === 'dismissed') {
            generationPhase.value = 'editing';

            return;
        }

        startSubmitTrace();
    }

    let destination: string;

    try {
        [destination] = await Promise.all([
            isAuthenticated.value ? startGeneration() : deferGenerationUntilAuthenticated(),
            wait(isReducedMotion.value ? 0 : submitTraceDuration),
        ]);
    } catch (error) {
        framePhase.value = 'idle';
        generationPhase.value = 'editing';
        submitError.value = error instanceof GenerationRequestError ? error.message : 'The generation could not be started. Please try again.';

        if (error instanceof GenerationRequestError && error.isInsufficientBalance) {
            await savePromptForLater();

            if ((await openBalanceDialog(quote.value?.total ?? null)) === 'topped-up') {
                await submitGeneration();
            }
        }

        return;
    }

    if (!isReducedMotion.value) {
        framePhase.value = 'hidden';
        await wait(frameRemovalDuration);
    }

    generationPhase.value = 'dismissing';
    await wait(isReducedMotion.value ? 0 : dismissDuration);

    router.visit(destination);
}

async function resumePendingGeneration(): Promise<void> {
    const pendingPrompt = await takePendingPrompt().catch(() => null);

    if (!pendingPrompt) {
        return;
    }

    prompt.value = pendingPrompt.prompt;
    selectProvider(pendingPrompt.provider);

    if (pendingPrompt.model) {
        selectModel(pendingPrompt.model);
    }

    optionValues.value = { ...optionValues.value, ...pendingPrompt.options };
    referenceImages.value = pendingPrompt.referenceImages.slice(0, maxReferenceImages.value).map((file) => ({
        id: nextReferenceImageId++,
        file,
        previewUrl: URL.createObjectURL(file),
    }));
    referenceUrls.value = (pendingPrompt.referenceUrls ?? []).slice(0, Math.max(0, maxReferenceImages.value - referenceImages.value.length));
    setPromptExpanded(true);

    await wait(isReducedMotion.value ? 0 : resumeDelay);
    await submitGeneration();
}

watch([selectedProviderName, selectedModelId, optionValues, prompt, referenceUrls, () => referenceImages.value.length], () => {
    submitError.value = null;
    scheduleQuote();
});

watch(isPromptExpanded, (isExpanded) => {
    if (isExpanded) {
        showRandomTip();
    }
});

onMounted(() => {
    isReducedMotion.value = prefersReducedMotion();

    if (!isReducedMotion.value) {
        framePhase.value = 'drawing';
    }
    isMacPlatform.value = detectMacPlatform();
    tipRotationTimer = setInterval(() => {
        if (isPromptExpanded.value && !isGenerating.value) {
            showRandomTip();
        }
    }, tipHoldDuration);
    document.addEventListener('selectionchange', onDocumentSelectionChange);
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('focusin', onDocumentFocusIn);
    scheduleQuote();

    if (isAuthenticated.value) {
        resumePendingGeneration();
    }
});

onBeforeUnmount(() => {
    clearInterval(tipRotationTimer);
    clearTimeout(quoteDebounceTimer);
    quoteAbortController?.abort();
    document.removeEventListener('selectionchange', onDocumentSelectionChange);
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('focusin', onDocumentFocusIn);
    clearReferenceImages();
    caretMirror?.remove();
});
</script>

<template>
    <TranceLayout
        title="Home"
        :class="{ 'screen-shake': isScreenShaking, 'screen-blur-out': generationPhase === 'dismissing' }"
        @animationend.self="isScreenShaking = false"
    >
        <main class="relative flex flex-1 items-center justify-center px-6 py-16">
            <form
                ref="promptFrameElement"
                class="relative w-full transition-[max-width] duration-500 [transition-timing-function:cubic-bezier(0.2,0.8,0.2,1)] motion-reduce:transition-none"
                :class="[isPromptExpanded ? 'max-w-3xl' : 'max-w-2xl', { 'is-dismissing': generationPhase === 'dismissing' }]"
                :aria-busy="isGenerating"
                @submit.prevent="submitGeneration"
                @transitionend="onPromptFrameTransitionEnd"
            >
                <FrameGuides :phase="framePhase" ambient-beams data-outro style="--outro-index: 4" @drawn="onFrameDrawn" />

                <span class="absolute -top-10 left-0 text-[10px] uppercase tracking-[0.25em] text-neutral-500" data-outro style="--outro-index: 3">
                    Prompt
                </span>

                <div class="relative bg-neutral-50/80 backdrop-blur-[1px] dark:bg-neutral-950/80">
                    <label for="prompt" class="sr-only">Image generation prompt</label>
                    <div class="relative" data-outro style="--outro-index: 2">
                        <textarea
                            id="prompt"
                            ref="promptTextarea"
                            v-model="prompt"
                            rows="5"
                            :readonly="isGenerating"
                            :maxlength="maxPromptLength"
                            placeholder="Describe the image you want to generate…"
                            :class="isPromptExpanded ? 'h-44 text-[13px]' : 'h-36 text-xs'"
                            class="block w-full resize-none border-0 bg-transparent p-5 uppercase leading-relaxed tracking-[0.2em] caret-transparent transition-[height,font-size,padding] duration-500 [transition-timing-function:cubic-bezier(0.2,0.8,0.2,1)] placeholder:text-neutral-400 focus:outline-none focus:ring-0 motion-reduce:transition-none dark:placeholder:text-neutral-600"
                            @focus="onPromptFocus"
                            @blur="onPromptBlur"
                            @keydown="onPromptKeydown"
                            @input="updateCaret"
                            @keyup="updateCaret"
                            @click="updateCaret"
                            @scroll="updateCaret"
                            @transitionend.self="updateCaret"
                        />
                        <span
                            v-if="caret && framePhase !== 'hidden'"
                            :key="caretBlinkKey"
                            aria-hidden="true"
                            class="prompt-caret"
                            :style="{ top: `${caret.top}px`, left: `${caret.left}px`, width: `${caret.width}px`, height: `${caret.height}px` }"
                        />
                    </div>
                    <div v-if="isPromptExpanded" class="space-y-4 px-5 pb-4" :inert="isGenerating">
                        <TransitionGroup
                            tag="div"
                            name="option-reveal"
                            appear
                            class="flex flex-wrap items-center gap-1.5"
                            data-outro
                            style="--outro-index: 1"
                        >
                            <div v-for="(option, index) in selectedModel.options" :key="`option-${option.key}`" :style="{ '--reveal-index': index }">
                                <PromptOption :label="option.label">
                                    <template #value>{{ formatOptionValue(option, optionValues[option.key]) }}</template>
                                    <DropdownMenuRadioGroup
                                        :model-value="String(optionValues[option.key] ?? '')"
                                        @update:model-value="setOptionValue(option, $event)"
                                    >
                                        <DropdownMenuRadioItem
                                            v-for="choice in optionChoices(option)"
                                            :key="String(choice.value)"
                                            :value="String(choice.value)"
                                            :class="promptOptionItemClass"
                                        >
                                            <span class="tabular-nums">{{ choice.label }}</span>
                                            <span v-if="choice.hint" class="ml-auto opacity-50">{{ choice.hint }}</span>
                                        </DropdownMenuRadioItem>
                                    </DropdownMenuRadioGroup>
                                </PromptOption>
                            </div>

                            <span
                                v-if="prompt.length"
                                key="remaining"
                                class="ml-auto text-[9px] uppercase tabular-nums tracking-[0.2em] transition-colors"
                                :class="
                                    remainingPromptCharacters === 0
                                        ? 'text-red-600 dark:text-red-400'
                                        : remainingPromptCharacters <= 200
                                          ? 'text-amber-600 dark:text-amber-400'
                                          : 'text-neutral-400 dark:text-neutral-500'
                                "
                                style="--reveal-index: 0"
                            >
                                {{ remainingPromptCharacters.toLocaleString() }} left
                            </span>

                            <div
                                v-if="selectedProvider.models.length > 1"
                                key="model"
                                :class="{ 'ml-auto': !prompt.length }"
                                :style="{ '--reveal-index': selectedModel.options.length }"
                            >
                                <PromptOption label="Model">
                                    <template #value>{{ selectedModel.label }}</template>
                                    <DropdownMenuRadioGroup :model-value="selectedModel.id ?? ''" @update:model-value="selectModel">
                                        <DropdownMenuRadioItem
                                            v-for="model in selectedProvider.models"
                                            :key="model.id ?? model.label"
                                            :value="model.id ?? ''"
                                            :class="promptOptionItemClass"
                                        >
                                            {{ model.label }}
                                        </DropdownMenuRadioItem>
                                    </DropdownMenuRadioGroup>
                                </PromptOption>
                            </div>

                            <div
                                key="provider"
                                :class="{ 'ml-auto': !prompt.length && selectedProvider.models.length <= 1 }"
                                :style="{ '--reveal-index': selectedModel.options.length + 1 }"
                            >
                                <PromptOption label="Provider">
                                    <template #value>
                                        <ProviderLogo :provider="selectedProvider" />
                                        {{ selectedProvider.label }}
                                    </template>
                                    <DropdownMenuRadioGroup :model-value="selectedProviderName" @update:model-value="selectProvider">
                                        <DropdownMenuRadioItem
                                            v-for="provider in providers"
                                            :key="provider.provider"
                                            :value="provider.provider"
                                            :disabled="!provider.enabled"
                                            :class="promptOptionItemClass"
                                        >
                                            <span class="flex items-center gap-2">
                                                <ProviderLogo :provider="provider" />
                                                {{ provider.label }}
                                            </span>
                                            <span v-if="!provider.enabled" class="ml-auto opacity-50">Unavailable</span>
                                        </DropdownMenuRadioItem>
                                    </DropdownMenuRadioGroup>
                                </PromptOption>
                            </div>
                        </TransitionGroup>

                        <Transition name="option-reveal" appear>
                            <section
                                v-if="maxReferenceImages > 0"
                                class="space-y-2"
                                style="--reveal-index: 2; --outro-index: 0"
                                data-outro
                                aria-label="Reference images"
                            >
                                <input
                                    ref="referenceImagesInput"
                                    type="file"
                                    :accept="acceptedReferenceTypes.join(',')"
                                    multiple
                                    class="hidden"
                                    @change="onReferenceImagesChange"
                                />
                                <p class="text-[9px] uppercase tracking-[0.2em] text-neutral-400 dark:text-neutral-500">Reference images</p>
                                <TransitionGroup tag="div" name="reference-thumb" class="flex flex-wrap gap-2">
                                    <figure v-for="image in referenceImages" :key="image.id" class="group relative size-14 border border-[--line]">
                                        <img :src="image.previewUrl" :alt="image.file.name" class="size-full object-cover" />
                                        <button
                                            type="button"
                                            :aria-label="`Remove ${image.file.name}`"
                                            class="absolute -right-px -top-px flex size-4 items-center justify-center bg-neutral-900 text-[11px] leading-none text-neutral-50 opacity-0 transition-opacity focus-visible:opacity-100 group-hover:opacity-100 dark:bg-neutral-100 dark:text-neutral-900"
                                            @click="removeReferenceImage(image.id)"
                                        >
                                            ×
                                        </button>
                                    </figure>
                                    <figure v-for="url in referenceUrls" :key="url" class="group relative size-14 border border-[--line]">
                                        <img :src="url" alt="" class="size-full object-cover" referrerpolicy="no-referrer" />
                                        <span
                                            class="absolute inset-x-0 bottom-0 bg-neutral-50/90 px-1 text-center text-[7px] uppercase tracking-[0.2em] text-neutral-500 dark:bg-neutral-950/90"
                                            >URL</span
                                        >
                                        <button
                                            type="button"
                                            :aria-label="`Remove ${url}`"
                                            class="absolute -right-px -top-px flex size-4 items-center justify-center bg-neutral-900 text-[11px] leading-none text-neutral-50 opacity-0 transition-opacity focus-visible:opacity-100 group-hover:opacity-100 dark:bg-neutral-100 dark:text-neutral-900"
                                            @click="removeReferenceUrl(url)"
                                        >
                                            ×
                                        </button>
                                    </figure>
                                    <div key="add">
                                        <TooltipProvider :delay-duration="150">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <button
                                                        type="button"
                                                        :aria-disabled="hasMaxReferenceImages"
                                                        :aria-label="
                                                            hasMaxReferenceImages ? 'Maximum reference images reached' : 'Add reference images'
                                                        "
                                                        class="flex size-14 items-center justify-center border border-dashed border-[--line] text-base text-neutral-400 transition-colors"
                                                        :class="
                                                            hasMaxReferenceImages
                                                                ? 'cursor-not-allowed opacity-40'
                                                                : 'hover:border-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100'
                                                        "
                                                        @click="openReferenceImagePicker"
                                                    >
                                                        +
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent
                                                    side="top"
                                                    class="rounded-none border-0 bg-neutral-900 px-2 py-1 font-mono text-[9px] uppercase tracking-[0.2em] text-neutral-50 shadow-none dark:bg-neutral-100 dark:text-neutral-900"
                                                >
                                                    {{
                                                        hasMaxReferenceImages
                                                            ? `Maximum ${maxReferenceImages} reference images reached`
                                                            : `Add up to ${maxReferenceImages} reference images`
                                                    }}
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                </TransitionGroup>
                                <div class="flex max-w-md items-center gap-1.5">
                                    <label for="reference-url" class="sr-only">Reference image URL</label>
                                    <input
                                        id="reference-url"
                                        v-model="referenceUrlDraft"
                                        type="url"
                                        inputmode="url"
                                        placeholder="Or paste an image URL"
                                        :disabled="hasMaxReferenceImages"
                                        class="h-7 min-w-0 flex-1 rounded-none border border-[--line] bg-transparent px-2 font-mono text-[10px] tracking-[0.05em] placeholder:uppercase placeholder:tracking-[0.2em] placeholder:text-neutral-400 focus:border-neutral-900 focus:outline-none focus:ring-0 disabled:opacity-40 dark:focus:border-neutral-100"
                                        @input="referenceUrlError = null"
                                        @keydown.enter.prevent.stop="addReferenceUrl"
                                    />
                                    <button
                                        type="button"
                                        :disabled="hasMaxReferenceImages || referenceUrlDraft.trim() === ''"
                                        @click="addReferenceUrl"
                                        class="h-7 border border-[--line] px-3 text-[9px] uppercase tracking-[0.2em] text-neutral-500 transition-colors hover:border-neutral-500 hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:text-neutral-100"
                                    >
                                        Add
                                    </button>
                                </div>
                                <p v-if="referenceUrlError" class="text-[9px] uppercase tracking-[0.2em] text-red-600 dark:text-red-400" role="alert">
                                    {{ referenceUrlError }}
                                </p>
                            </section>
                        </Transition>
                    </div>
                    <div
                        class="flex min-h-12 items-center justify-between gap-4 border-t border-[--line] py-2 pl-5 pr-3 text-[10px] uppercase tracking-[0.25em] text-neutral-500"
                        data-outro
                        style="--outro-index: 3"
                    >
                        <div class="relative min-w-0 flex-1 overflow-hidden">
                            <Transition name="tip-swap" mode="out-in">
                                <p
                                    v-if="submitError"
                                    key="error"
                                    class="truncate text-[9px] tracking-[0.2em] text-red-600 dark:text-red-400"
                                    role="alert"
                                    :title="submitError"
                                >
                                    {{ submitError }}
                                </p>
                                <span v-else-if="!isPromptExpanded" key="summary" class="block truncate">
                                    {{ optionSummary }}
                                </span>
                                <p
                                    v-else
                                    :key="activeTipIndex"
                                    class="flex items-center gap-2 truncate text-[9px] tracking-[0.2em] text-neutral-400 dark:text-neutral-500"
                                >
                                    <kbd
                                        v-for="key in activeTip.keys"
                                        :key="key"
                                        class="flex h-4 items-center border border-[--line] px-1.5 font-mono text-[9px] tracking-normal text-neutral-700 dark:text-neutral-300"
                                    >
                                        <Command v-if="key === commandKeyToken" class="size-2.5" :stroke-width="2.25" aria-label="Command" />
                                        <template v-else>{{ key }}</template>
                                    </kbd>
                                    <span class="truncate">{{ activeTip.text }}</span>
                                </p>
                            </Transition>
                        </div>
                        <button
                            type="submit"
                            :disabled="generateButtonState !== 'ready'"
                            :aria-busy="generateButtonState === 'quoting' || generateButtonState === 'submitting'"
                            :aria-label="generateButtonState === 'quoting' ? 'Updating price' : undefined"
                            :data-state="generateButtonState"
                            class="flex h-8 min-w-28 items-center justify-center bg-neutral-900 px-4 text-neutral-50 transition dark:bg-neutral-100 dark:text-neutral-900"
                            :class="generateButtonClasses[generateButtonState]"
                        >
                            <SquareDots v-if="generateButtonState === 'quoting' || generateButtonState === 'submitting'" />
                            <template v-else>
                                Generate
                                <span
                                    v-if="generateButtonState === 'ready' && formattedQuote"
                                    class="ml-3 tabular-nums tracking-[0.1em] opacity-60"
                                    >{{ formattedQuote }}</span
                                >
                            </template>
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </TranceLayout>
</template>

<style scoped>
.option-reveal-enter-active {
    transition:
        opacity 320ms ease-out,
        transform 320ms cubic-bezier(0.2, 0.8, 0.2, 1),
        filter 320ms ease-out;
    transition-delay: calc(var(--reveal-index) * 80ms + 200ms);
}

.option-reveal-enter-from {
    opacity: 0;
    transform: translateY(6px);
    filter: blur(3px);
}

.option-reveal-leave-active {
    display: none;
}

/* Reverse of the reveal: parts leave in `--outro-index` order before the hand-off. */
.is-dismissing [data-outro] {
    animation: prompt-outro 320ms cubic-bezier(0.4, 0, 1, 1) both;
    animation-delay: calc(var(--outro-index) * 70ms);
}

@keyframes prompt-outro {
    to {
        opacity: 0;
        transform: translateY(-6px);
        filter: blur(4px);
    }
}

.reference-thumb-enter-active,
.reference-thumb-leave-active {
    transition:
        opacity 200ms ease-out,
        transform 200ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.reference-thumb-enter-from,
.reference-thumb-leave-to {
    opacity: 0;
    transform: scale(0.9);
}

.reference-thumb-leave-active {
    position: absolute;
}

.reference-thumb-move {
    transition: transform 200ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.tip-swap-enter-active,
.tip-swap-leave-active {
    transition:
        opacity 220ms ease-out,
        transform 220ms ease-out,
        filter 220ms ease-out;
}

.tip-swap-enter-from {
    opacity: 0;
    transform: translateY(4px);
    filter: blur(2px);
}

.tip-swap-leave-to {
    opacity: 0;
    transform: translateY(-4px);
    filter: blur(2px);
}

.prompt-caret {
    position: absolute;
    pointer-events: none;
    background: var(--beam);
    box-shadow: 0 0 6px var(--beam-glow);
    animation: caret-blink 1s steps(1, end) infinite;
}

@keyframes caret-blink {
    0% {
        opacity: 1;
    }
    50% {
        opacity: 0;
    }
}

.screen-shake {
    animation: screen-shake 300ms cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
}

@keyframes screen-shake {
    0%,
    100% {
        transform: translate(0, 0);
    }
    20% {
        transform: translate(-3px, 1px);
    }
    40% {
        transform: translate(2px, -1px);
    }
    60% {
        transform: translate(-1.5px, 1px);
    }
    80% {
        transform: translate(1px, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .prompt-caret,
    .is-dismissing [data-outro] {
        animation: none;
    }
}
</style>
