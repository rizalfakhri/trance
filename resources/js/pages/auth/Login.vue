<script setup lang="ts">
import SquareDots from '@/components/trance/SquareDots.vue';
import TranceField from '@/components/trance/TranceField.vue';
import TranceNotice from '@/components/trance/TranceNotice.vue';
import TranceAuthLayout from '@/layouts/TranceAuthLayout.vue';
import { continueGenerationQuery, isContinuingGeneration } from '@/lib/pendingPrompt';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage();
const isContinuing = computed(() => isContinuingGeneration(page.url));

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit(): void {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <TranceAuthLayout title="Log in" description="Enter your email and password to continue.">
        <template #notice>
            <TranceNotice v-if="isContinuing">Log in to continue your generation.</TranceNotice>
            <TranceNotice v-if="status">{{ status }}</TranceNotice>
        </template>

        <form class="space-y-5" @submit.prevent="submit">
            <TranceField id="email" v-model="form.email" label="Email" type="email" autocomplete="email" autofocus :error="form.errors.email" />

            <TranceField
                id="password"
                v-model="form.password"
                label="Password"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
            >
                <template #aside>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-neutral-400 transition-colors hover:text-neutral-900 dark:text-neutral-500 dark:hover:text-neutral-100"
                    >
                        Forgot?
                    </Link>
                </template>
            </TranceField>

            <label class="flex cursor-pointer items-center gap-3 text-[9px] uppercase tracking-[0.2em] text-neutral-500">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="size-3.5 cursor-pointer appearance-none rounded-none border border-[--line-strong] bg-transparent checked:border-neutral-900 checked:bg-neutral-900 focus:ring-0 focus:ring-offset-0 dark:checked:border-neutral-100 dark:checked:bg-neutral-100"
                />
                Remember me
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="flex h-10 w-full items-center justify-center bg-neutral-900 text-[10px] uppercase tracking-[0.25em] text-neutral-50 transition hover:bg-neutral-700 disabled:cursor-wait dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-white"
            >
                <SquareDots v-if="form.processing" />
                <template v-else>Log in</template>
            </button>
        </form>

        <template #footer>
            <span>No account?</span>
            <Link
                :href="route('register', isContinuing ? continueGenerationQuery : {})"
                class="ml-auto text-neutral-900 transition-colors hover:text-neutral-500 dark:text-neutral-100"
            >
                Register
            </Link>
        </template>
    </TranceAuthLayout>
</template>
