export type GenerationStatus = 'queued' | 'processing' | 'succeeded' | 'failed';

/** Nano-dollar amounts; `actual` is null until the generation finishes and 0 when it failed. */
export interface GenerationCost {
    estimate: number | null;
    actual: number | null;
    currency: string;
}

export interface GenerationView {
    id: string;
    status: GenerationStatus;
    provider: { name: string; label: string; logoUrl: string | null; darkLogoUrl: string | null };
    model: { id: string; label: string } | null;
    prompt: string;
    options: Array<{ key: string; label: string; value: string }>;
    expectedSize: { width: number; height: number } | null;
    cost: GenerationCost;
    error: { code: string; message: string | null } | null;
    attempt: number;
    createdAt: string;
    completedAt: string | null;
    references: Array<{ id: number; url: string }>;
    outputs: Array<{ id: number; url: string; downloadUrl: string; mimeType: string; width: number | null; height: number | null }>;
    events: Array<{ id: number; status: GenerationStatus | null; message: string; offsetMs: number }>;
}
