<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ChevronDown } from 'lucide-vue-next';

defineProps<{
    label: string;
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="group inline-flex h-6 shrink-0 items-center justify-between gap-3 whitespace-nowrap border border-neutral-300 bg-neutral-50 px-2 font-mono text-[9px] uppercase tracking-[0.2em] transition-colors hover:border-neutral-500 data-[state=open]:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:hover:border-neutral-500 dark:data-[state=open]:border-neutral-100"
        >
            <span class="text-neutral-400 dark:text-neutral-500">{{ label }}</span>
            <span class="flex items-center gap-1.5 text-neutral-900 dark:text-neutral-100">
                <slot name="value" />
                <ChevronDown class="size-2.5 text-neutral-400 transition-transform group-data-[state=open]:rotate-180 dark:text-neutral-500" />
            </span>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="start"
            :side-offset="4"
            class="min-w-[--radix-dropdown-menu-trigger-width] rounded-none border-neutral-300 bg-neutral-50 p-0 font-mono text-[10px] uppercase tracking-[0.25em] text-neutral-900 shadow-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
        >
            <slot />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
