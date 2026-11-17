<template>
    <p
        class="flex items-start gap-3 border border-[--line] px-3 py-2.5 text-[10px] uppercase leading-relaxed tracking-[0.2em] text-neutral-900 dark:text-neutral-100"
    >
        <span aria-hidden="true" class="mt-1 size-1.5 shrink-0 bg-[--beam] shadow-[0_0_6px_var(--beam-glow)]" />
        <span><slot /></span>
    </p>
</template>
