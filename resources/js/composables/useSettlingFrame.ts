import type { FramePhase } from '@/components/trance/FrameGuides.vue';
import { prefersReducedMotion } from '@/lib/trance/beams';
import { onMounted, ref } from 'vue';

export function useSettlingFrame() {
    const phase = ref<FramePhase>('idle');

    function onDrawn(): void {
        if (phase.value === 'drawing') {
            phase.value = 'idle';
        }
    }

    onMounted(() => {
        if (!prefersReducedMotion()) {
            phase.value = 'drawing';
        }
    });

    return { phase, onDrawn };
}
