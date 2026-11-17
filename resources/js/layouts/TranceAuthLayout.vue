<script setup lang="ts">
import FrameGuides from '@/components/trance/FrameGuides.vue';
import { useSettlingFrame } from '@/composables/useSettlingFrame';
import TranceLayout from '@/layouts/TranceLayout.vue';

defineProps<{
    title: string;
    description: string;
}>();

const { phase: framePhase, onDrawn: onFrameDrawn } = useSettlingFrame();
</script>

<template>
    <TranceLayout :title="title">
        <main class="relative flex flex-1 items-center justify-center px-6 py-20">
            <section class="relative w-full max-w-md" :aria-label="title">
                <FrameGuides :phase="framePhase" @drawn="onFrameDrawn" />

                <span class="absolute -top-10 left-0 text-[10px] uppercase tracking-[0.25em] text-neutral-500">{{ title }}</span>

                <div class="relative bg-neutral-50/80 backdrop-blur-[1px] dark:bg-neutral-950/80">
                    <div class="space-y-6 p-6">
                        <p class="text-[11px] uppercase leading-relaxed tracking-[0.2em] text-neutral-500">{{ description }}</p>
                        <slot name="notice" />
                        <slot />
                    </div>

                    <div
                        v-if="$slots.footer"
                        class="flex min-h-12 items-center border-t border-[--line] px-6 py-2 text-[10px] uppercase tracking-[0.25em] text-neutral-500"
                    >
                        <slot name="footer" />
                    </div>
                </div>
            </section>
        </main>
    </TranceLayout>
</template>
