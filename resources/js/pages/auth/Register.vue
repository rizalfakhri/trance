<script setup lang="ts">
import SquareDots from '@/components/trance/SquareDots.vue';
import TranceField from '@/components/trance/TranceField.vue';
import TranceNotice from '@/components/trance/TranceNotice.vue';
import TranceAuthLayout from '@/layouts/TranceAuthLayout.vue';
import { continueGenerationQuery, isContinuingGeneration } from '@/lib/pendingPrompt';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const isContinuing = computed(() => isContinuingGeneration(page.url));

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <TranceAuthLayout title="Register" description="Create an account to start generating.">
        <template #notice>
            <TranceNotice v-if="isContinuing">Create an account to continue your generation.</TranceNotice>
        </template>

        <form class="space-y-5" @submit.prevent="submit">
            <TranceField id="name" v-model="form.name" label="Name" autocomplete="name" autofocus :error="form.errors.name" />
            <TranceField id="email" v-model="form.email" label="Email" type="email" autocomplete="email" :error="form.errors.email" />
            <TranceField
                id="password"
                v-model="form.password"
                label="Password"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password"
            />
            <TranceField
                id="password_confirmation"
                v-model="form.password_confirmation"
                label="Confirm password"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
            />

            <button
                type="submit"
                :disabled="form.processing"
                class="flex h-10 w-full items-center justify-center bg-neutral-900 text-[10px] uppercase tracking-[0.25em] text-neutral-50 transition hover:bg-neutral-700 disabled:cursor-wait dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-white"
            >
                <SquareDots v-if="form.processing" />
                <template v-else>Create account</template>
            </button>
        </form>

        <template #footer>
            <span>Have an account?</span>
            <Link
                :href="route('login', isContinuing ? continueGenerationQuery : {})"
                class="ml-auto text-neutral-900 transition-colors hover:text-neutral-500 dark:text-neutral-100"
            >
                Log in
            </Link>
        </template>
    </TranceAuthLayout>
</template>
