<template>
    <span class="inline-flex items-center gap-1" role="status" aria-label="Loading">
        <span v-for="index in 3" :key="index" class="square-dot size-1 bg-current" :style="{ animationDelay: `${(index - 1) * 160}ms` }" />
    </span>
</template>
