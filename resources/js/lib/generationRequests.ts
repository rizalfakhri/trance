import { postFormData } from '@/lib/http';
import type { OptionValue } from '@/types/providers';

export interface GenerationSubmission {
    provider: string;
    model: string | null;
    prompt: string;
    options: Record<string, OptionValue>;
    references: File[];
    referenceUrls: string[];
}

export class GenerationRequestError extends Error {
    constructor(
        message: string,
        public readonly isInsufficientBalance = false,
    ) {
        super(message);
    }
}

const fallbackErrorMessage = 'The generation could not be started. Please try again.';

function toFormData(submission: GenerationSubmission): FormData {
    const form = new FormData();

    form.append('provider', submission.provider);
    form.append('prompt', submission.prompt);

    if (submission.model !== null) {
        form.append('model', submission.model);
    }

    for (const [key, value] of Object.entries(submission.options)) {
        form.append(`options[${key}]`, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
    }

    submission.references.forEach((file) => form.append('references[]', file));
    submission.referenceUrls.forEach((url) => form.append('reference_urls[]', url));

    return form;
}

/**
 * Starts a generation and returns the URL of its page.
 *
 * @throws GenerationRequestError
 */
export async function createGeneration(submission: GenerationSubmission): Promise<string> {
    const response = await postFormData(route('generations.store'), toFormData(submission)).catch(() => null);

    if (response === null) {
        throw new GenerationRequestError(fallbackErrorMessage);
    }

    const body = (await response.json().catch(() => ({}))) as { url?: string; message?: string };

    if (!response.ok || body.url === undefined) {
        throw new GenerationRequestError(body.message ?? fallbackErrorMessage, response.status === 402);
    }

    return body.url;
}
