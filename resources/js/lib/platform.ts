export function isMacPlatform(): boolean {
    return /Mac|iPhone|iPad/.test(navigator.userAgent);
}
