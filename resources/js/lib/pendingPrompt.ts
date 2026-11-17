import type { OptionValue } from '@/types/providers';

export interface PendingPrompt {
    prompt: string;
    provider: string;
    model?: string | null;
    options: Record<string, OptionValue>;
    referenceImages: File[];
    referenceUrls?: string[];
}

export const continueGenerationQuery = { redirect: '/', continue: 'generation' };

const databaseName = 'trance';
const storeName = 'pending-prompt';
const recordKey = 'current';

function openDatabase(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(databaseName, 1);

        request.onupgradeneeded = () => request.result.createObjectStore(storeName);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function runRequest<T>(mode: IDBTransactionMode, createRequest: (store: IDBObjectStore) => IDBRequest<T>): Promise<T> {
    const database = await openDatabase();

    try {
        return await new Promise<T>((resolve, reject) => {
            const request = createRequest(database.transaction(storeName, mode).objectStore(storeName));

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    } finally {
        database.close();
    }
}

export async function savePendingPrompt(pendingPrompt: PendingPrompt): Promise<void> {
    await runRequest('readwrite', (store) => store.put(pendingPrompt, recordKey));
}

export async function takePendingPrompt(): Promise<PendingPrompt | null> {
    const pendingPrompt = await runRequest<PendingPrompt | undefined>('readonly', (store) => store.get(recordKey));

    if (pendingPrompt) {
        await runRequest('readwrite', (store) => store.delete(recordKey));
    }

    return pendingPrompt ?? null;
}

export async function clearPendingPrompt(): Promise<void> {
    await runRequest('readwrite', (store) => store.delete(recordKey));
}

export function isContinuingGeneration(pageUrl: string): boolean {
    return new URLSearchParams(pageUrl.split('?')[1] ?? '').get('continue') === 'generation';
}
