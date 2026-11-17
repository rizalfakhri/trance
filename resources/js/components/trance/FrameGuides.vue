<script setup lang="ts">
import {
    beamDashStyle,
    buildFrameHalves,
    buildFrameWalk,
    createBeamId,
    createSpawnScheduler,
    prefersReducedMotion,
    randomBetween,
    type Beam,
} from '@/lib/trance/beams';
import { bracketCorners, cornerArmStyle, cornerMarks } from '@/lib/trance/frame';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * idle: guides and corner marks at rest. hidden: everything fades out (e.g.
 * while the framed box resizes). drawing: the border is traced and the guides
 * shoot out. tracing: the border is traced while the guides stay put.
 * corners: only "L" brackets at the top-left and bottom-right corners remain.
 */
export type FramePhase = 'idle' | 'hidden' | 'drawing' | 'tracing' | 'corners';

const props = withDefaults(
    defineProps<{
        phase?: FramePhase;
        /** Extend the guide lines to the viewport edges instead of stopping at the box. */
        extended?: boolean;
        /** Send an occasional light around the border while idle. */
        ambientBeams?: boolean;
        /** Milliseconds at rest before the guides settle into corner brackets; null keeps the full frame. */
        settleDelay?: number | null;
    }>(),
    { phase: 'idle', extended: true, ambientBeams: false, settleDelay: 1200 },
);

const emit = defineEmits<{
    /** The guides finished drawing in after a `drawing` phase. */
    drawn: [];
}>();

const overlayElement = ref<SVGSVGElement | null>(null);
const frameBeams = ref<Beam[]>([]);
const frameRedraw = ref<{ id: number; paths: string[]; length: number } | null>(null);
const scheduler = createSpawnScheduler();

const guideLines = [
    { edge: 'top', axis: 'x' },
    { edge: 'bottom', axis: 'x' },
    { edge: 'left', axis: 'y' },
    { edge: 'right', axis: 'y' },
] as const;

function measureBox(): { width: number; height: number } | null {
    const element = overlayElement.value;

    return element ? element.getBoundingClientRect() : null;
}

const isSettled = ref(false);
const visiblePhase = computed<FramePhase>(() => (props.phase === 'idle' && isSettled.value ? 'corners' : props.phase));

let settleTimer: ReturnType<typeof setTimeout> | undefined;

function scheduleSettle(phase: FramePhase): void {
    clearTimeout(settleTimer);
    isSettled.value = false;

    if (phase === 'idle' && props.settleDelay !== null) {
        settleTimer = setTimeout(() => {
            isSettled.value = true;
        }, props.settleDelay);
    }
}

function applyPhase(phase: FramePhase): void {
    scheduleSettle(phase);

    if (phase !== 'idle') {
        frameBeams.value = [];
    }

    const box = measureBox();

    const tracesBorder = phase === 'drawing' || phase === 'tracing';

    frameRedraw.value = tracesBorder && box ? { id: createBeamId(), ...buildFrameHalves(box.width, box.height) } : null;
}

function spawnFrameBeam(): void {
    const box = measureBox();

    if (!box || !props.ambientBeams || visiblePhase.value !== 'idle' || frameBeams.value.length > 0) {
        return;
    }

    const walk = buildFrameWalk(box.width, box.height);
    const tailLength = randomBetween(120, 240);

    frameBeams.value.push({
        id: createBeamId(),
        layer: 'major',
        ...walk,
        tailLength,
        headLength: tailLength * 0.3,
        duration: (walk.pathLength + tailLength) / randomBetween(600, 1100),
        pulseDuration: randomBetween(0.18, 0.45),
    });
}

function removeFrameBeam(beamId: number): void {
    frameBeams.value = frameBeams.value.filter((beam) => beam.id !== beamId);
}

function onGuideAnimationEnd(event: AnimationEvent): void {
    if (props.phase === 'drawing' && event.animationName.includes('frame-line-draw')) {
        emit('drawn');
    }
}

watch(() => props.phase, applyPhase);

onMounted(() => {
    applyPhase(props.phase);

    if (!prefersReducedMotion()) {
        scheduler.schedule(spawnFrameBeam, 4000, 10000);
    }
});

onBeforeUnmount(() => {
    clearTimeout(settleTimer);
    scheduler.stop();
});
</script>

<template>
    <!-- Inset by -12px so the frame floats around the content with breathing room instead of touching it. -->
    <div aria-hidden="true" class="pointer-events-none absolute -inset-3 z-10">
        <span
            v-for="(line, index) in guideLines"
            :key="line.edge"
            class="frame-line absolute bg-[--line-strong]"
            :class="[
                `frame-line-${line.axis}`,
                `frame-line-${line.edge}`,
                `frame-phase-${visiblePhase}`,
                line.axis === 'x' ? 'h-px' : 'w-px',
                line.axis === 'x' ? (extended ? '-inset-x-[100vw]' : 'inset-x-0') : extended ? '-inset-y-[100vh]' : 'inset-y-0',
                {
                    'top-0': line.edge === 'top',
                    'bottom-0': line.edge === 'bottom',
                    'left-0': line.edge === 'left',
                    'right-0': line.edge === 'right',
                },
            ]"
            @animationend="index === 0 && onGuideAnimationEnd($event)"
        />

        <span
            v-for="corner in cornerMarks"
            :key="corner.name"
            class="corner-mark"
            :class="[`corner-mark-${corner.name}`, `frame-phase-${visiblePhase}`, `${corner.vertical}-0`, `${corner.horizontal}-0`]"
        >
            <span class="corner-mark-arm" :style="cornerArmStyle(corner, 'x', visiblePhase === 'corners' && bracketCorners.includes(corner.name))" />
            <span class="corner-mark-arm" :style="cornerArmStyle(corner, 'y', visiblePhase === 'corners' && bracketCorners.includes(corner.name))" />
        </span>

        <svg ref="overlayElement" class="absolute inset-0 size-full overflow-visible">
            <g v-for="beam in frameBeams" :key="beam.id" class="beam beam-frame" :style="{ '--beam-pulse-duration': `${beam.pulseDuration}s` }">
                <path class="beam-tail" :d="beam.path" :style="beamDashStyle(beam, beam.tailLength)" @animationend="removeFrameBeam(beam.id)" />
                <path class="beam-head" :d="beam.path" :style="beamDashStyle(beam, beam.headLength)" />
            </g>
            <g v-if="frameRedraw" :key="frameRedraw.id" class="frame-redraw" :style="{ '--redraw-length': `${frameRedraw.length}px` }">
                <path
                    v-for="(path, index) in frameRedraw.paths"
                    :key="index"
                    :d="path"
                    :style="{ strokeDasharray: `${frameRedraw.length}` }"
                    @animationend="index === 0 && (frameRedraw = null)"
                />
            </g>
        </svg>
    </div>
</template>
