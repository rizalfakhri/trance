function xsrfToken(): string {
    const cookie = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

export function postJson(url: string, body: unknown, signal?: AbortSignal): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        body: JSON.stringify(body),
        signal,
    });
}

export function postFormData(url: string, body: FormData): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        body,
    });
}
