<script setup lang="ts">
import {
    beamDashStyle,
    buildGridWalk,
    createBeamId,
    createSpawnScheduler,
    prefersReducedMotion,
    randomBetween,
    type Beam,
    type GridLayer,
} from '@/lib/trance/beams';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const maxConcurrentBeams = 4;

const gridLayerElement = ref<HTMLElement | null>(null);
const beams = ref<Beam[]>([]);
const scheduler = createSpawnScheduler();

function spawnBeam(): void {
    const element = gridLayerElement.value;

    if (!element || beams.value.length >= maxConcurrentBeams) {
        return;
    }

    const { width, height } = element.getBoundingClientRect();
    const layer: GridLayer = Math.random() < 0.4 ? 'major' : 'minor';
    const walk = buildGridWalk(width, height, layer);

    if (!walk) {
        return;
    }

    const tailLength = layer === 'major' ? randomBetween(160, 280) : randomBetween(60, 140);

    beams.value.push({
        id: createBeamId(),
        layer,
        ...walk,
        tailLength,
        headLength: tailLength * 0.25,
        duration: (walk.pathLength + tailLength) / randomBetween(300, 600),
        pulseDuration: randomBetween(0.8, 1.6),
    });
}

function removeBeam(beamId: number): void {
    beams.value = beams.value.filter((beam) => beam.id !== beamId);
}

onMounted(() => {
    if (prefersReducedMotion()) {
        return;
    }

    spawnBeam();
    scheduler.schedule(spawnBeam, 1200, 3200);
});

onBeforeUnmount(() => {
    scheduler.stop();
});
</script>

<template>
    <div ref="gridLayerElement" aria-hidden="true" class="grid-layer pointer-events-none absolute inset-0 overflow-hidden">
        <svg class="absolute inset-0 size-full opacity-50">
            <g
                v-for="beam in beams"
                :key="beam.id"
                class="beam"
                :class="`beam-${beam.layer}`"
                :style="{ '--beam-pulse-duration': `${beam.pulseDuration}s` }"
            >
                <path class="beam-tail" :d="beam.path" :style="beamDashStyle(beam, beam.tailLength)" @animationend="removeBeam(beam.id)" />
                <path class="beam-head" :d="beam.path" :style="beamDashStyle(beam, beam.headLength)" />
            </g>
        </svg>
    </div>
</template>
