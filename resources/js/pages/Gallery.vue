<script setup lang="ts">
import FrameGuides from '@/components/trance/FrameGuides.vue';
import SquareDots from '@/components/trance/SquareDots.vue';
import { useSettlingFrame } from '@/composables/useSettlingFrame';
import TranceLayout from '@/layouts/TranceLayout.vue';
import type { GalleryItem } from '@/types/gallery';
import { Link, WhenVisible } from '@inertiajs/vue3';

defineProps<{
    generations: GalleryItem[];
    nextPage: number | null;
}>();

const { phase: framePhase, onDrawn: onFrameDrawn } = useSettlingFrame();

const dateFormatter = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

function isPending(item: GalleryItem): boolean {
    return item.status === 'queued' || item.status === 'processing' || (item.status === 'succeeded' && item.thumbnailUrl === null);
}
</script>

<template>
    <TranceLayout title="Gallery">
        <main class="relative mx-auto flex w-full max-w-5xl flex-1 flex-col px-6 py-20">
            <section class="relative" :class="{ 'my-auto': generations.length === 0 }" aria-label="Gallery">
                <FrameGuides :phase="framePhase" @drawn="onFrameDrawn" />

                <span class="absolute -top-10 left-0 text-[10px] uppercase tracking-[0.25em] text-neutral-500">Gallery</span>
                <span
                    v-if="generations.length"
                    class="absolute -top-10 right-0 text-[10px] uppercase tabular-nums tracking-[0.25em] text-neutral-500"
                >
                    {{ generations.length }}{{ nextPage ? '+' : '' }} generations
                </span>

                <div class="relative bg-neutral-50/80 backdrop-blur-[1px] dark:bg-neutral-950/80">
                    <div v-if="generations.length === 0" class="flex flex-col items-center gap-6 px-6 py-20 text-center">
                        <p class="text-[13px] uppercase tracking-[0.2em]">No generations yet</p>
                        <p class="max-w-sm text-[10px] uppercase leading-relaxed tracking-[0.2em] text-neutral-500">
                            Everything you generate will gather here.
                        </p>
                        <Link
                            :href="route('home')"
                            class="flex h-8 items-center bg-neutral-900 px-4 text-[10px] uppercase tracking-[0.25em] text-neutral-50 transition hover:bg-neutral-700 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-white"
                        >
                            Start a prompt
                        </Link>
                    </div>

                    <template v-else>
                        <ul class="columns-2 gap-3 p-5 sm:columns-3 lg:columns-4">
                            <li v-for="item in generations" :key="item.id" class="mb-5 break-inside-avoid">
                                <Link :href="item.url" class="group block space-y-2">
                                    <div
                                        class="relative overflow-hidden border border-[--line] transition-colors group-hover:border-[--line-strong]"
                                        :class="{ 'gallery-shimmer': isPending(item) }"
                                        :style="{ aspectRatio: item.aspectRatio }"
                                    >
                                        <img
                                            v-if="item.thumbnailUrl"
                                            :src="item.thumbnailUrl"
                                            :alt="item.prompt"
                                            loading="lazy"
                                            class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                        />
                                        <div
                                            v-else-if="item.status === 'failed'"
                                            class="flex size-full items-center justify-center text-[9px] uppercase tracking-[0.25em] text-red-600 dark:text-red-400"
                                        >
                                            Failed
                                        </div>
                                        <div
                                            v-else
                                            class="absolute inset-0 flex items-center justify-center gap-2 text-[9px] uppercase tracking-[0.25em] text-neutral-500"
                                        >
                                            Generating
                                            <SquareDots class="text-[--beam]" />
                                        </div>
                                    </div>
                                    <p
                                        class="truncate text-[9px] uppercase tracking-[0.2em] text-neutral-900 dark:text-neutral-100"
                                        :title="item.prompt"
                                    >
                                        {{ item.prompt }}
                                    </p>
                                    <p class="text-[9px] uppercase tracking-[0.2em] text-neutral-400 dark:text-neutral-500">
                                        {{ dateFormatter.format(new Date(item.createdAt)) }}
                                    </p>
                                </Link>
                            </li>
                        </ul>

                        <WhenVisible
                            v-if="nextPage"
                            :key="nextPage"
                            :data="['generations', 'nextPage']"
                            :params="{ data: { page: nextPage }, preserveUrl: true }"
                        >
                            <template #fallback>
                                <div class="flex justify-center pb-6 text-neutral-500">
                                    <SquareDots />
                                </div>
                            </template>
                        </WhenVisible>
                    </template>
                </div>
            </section>
        </main>
    </TranceLayout>
</template>

<style scoped>
.gallery-shimmer {
    background: color-mix(in srgb, var(--line) 22%, transparent);
}

.gallery-shimmer::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(100deg, transparent 20%, color-mix(in srgb, var(--beam) 18%, transparent) 50%, transparent 80%);
    transform: translateX(-100%);
    animation: gallery-shimmer 2.2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

@keyframes gallery-shimmer {
    to {
        transform: translateX(100%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .gallery-shimmer::after {
        animation: none;
    }
}
</style>
