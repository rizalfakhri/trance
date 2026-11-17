export function formatClock(milliseconds: number, showTenths = false): string {
    const totalSeconds = Math.max(0, milliseconds) / 1000;
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = showTenths ? (totalSeconds % 60).toFixed(1).padStart(4, '0') : String(Math.floor(totalSeconds % 60)).padStart(2, '0');

    return `${String(minutes).padStart(2, '0')}:${seconds}`;
}

export function wait(milliseconds: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}
