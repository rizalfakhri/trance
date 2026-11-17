<script setup lang="ts">
defineProps<{
    id: string;
    label: string;
    type?: string;
    autocomplete?: string;
    error?: string;
    autofocus?: boolean;
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between text-[9px] uppercase tracking-[0.2em]">
            <label :for="id" class="text-neutral-400 dark:text-neutral-500">{{ label }}</label>
            <slot name="aside" />
        </div>
        <input
            :id="id"
            v-model="model"
            :type="type ?? 'text'"
            :autocomplete="autocomplete"
            :autofocus="autofocus"
            :aria-invalid="Boolean(error)"
            :aria-describedby="error ? `${id}-error` : undefined"
            required
            class="block h-10 w-full rounded-none border border-[--line] bg-transparent px-3 font-mono text-[12px] tracking-[0.15em] text-neutral-900 transition-colors placeholder:text-neutral-400 focus:border-neutral-900 focus:outline-none focus:ring-0 aria-[invalid=true]:border-red-500 dark:text-neutral-100 dark:focus:border-neutral-100"
        />
        <p v-if="error" :id="`${id}-error`" class="text-[9px] uppercase tracking-[0.2em] text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
