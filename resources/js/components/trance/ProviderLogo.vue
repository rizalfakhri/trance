<script setup lang="ts">
import type { ProviderCapabilities } from '@/types/providers';

defineProps<{
    provider: Pick<ProviderCapabilities, 'label' | 'logoUrl' | 'darkLogoUrl'>;
}>();
</script>

<template>
    <span v-if="provider.logoUrl" aria-hidden="true" class="flex size-3.5 shrink-0">
        <img :src="provider.logoUrl" alt="" class="size-full object-contain" :class="provider.darkLogoUrl ? 'dark:hidden' : 'dark:invert'" />
        <img v-if="provider.darkLogoUrl" :src="provider.darkLogoUrl" alt="" class="hidden size-full object-contain dark:block" />
    </span>
    <span
        v-else
        aria-hidden="true"
        class="flex size-3.5 shrink-0 items-center justify-center border border-current text-[8px] font-bold leading-none tracking-normal"
    >
        {{ provider.label.charAt(0) }}
    </span>
</template>
