import { postJson } from '@/lib/http';
import { formatNanoDollars } from '@/lib/money';
import type { GenerationQuote, OptionValue } from '@/types/providers';

export interface QuotePayload {
    provider: string;
    model: string | null;
    prompt: string;
    options: Record<string, OptionValue>;
    reference_types: string[];
    reference_urls: string[];
}

export async function fetchGenerationQuote(payload: QuotePayload, signal: AbortSignal): Promise<GenerationQuote | null> {
    const response = await postJson(route('generations.quote'), payload, signal);

    if (!response.ok) {
        return null;
    }

    const quote = (await response.json()) as GenerationQuote | { total: null };

    return quote.total === null ? null : (quote as GenerationQuote);
}

export function formatQuoteTotal(quote: GenerationQuote): string {
    return formatNanoDollars(quote.total, quote.currency, 'ceil');
}
