import type { GenerationStatus } from '@/types/generation';

export interface GalleryItem {
    id: string;
    url: string;
    status: GenerationStatus;
    prompt: string;
    thumbnailUrl: string | null;
    aspectRatio: string;
    createdAt: string;
}
