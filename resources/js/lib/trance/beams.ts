export type GridLayer = 'major' | 'minor';

export interface Beam {
    id: number;
    layer: GridLayer;
    path: string;
    pathLength: number;
    tailLength: number;
    headLength: number;
    duration: number;
    pulseDuration: number;
}

export interface BeamPath {
    path: string;
    pathLength: number;
}

/** Must match the background-size values on `.grid-layer` in app.css. */
export const gridSizes: Record<GridLayer, number> = { major: 120, minor: 30 };

/** Longest straight run, in grid cells, before a grid beam turns. */
const maxCellsPerRun: Record<GridLayer, number> = { major: 3, minor: 8 };

let nextBeamId = 0;

export function createBeamId(): number {
    return nextBeamId++;
}

export function randomBetween(min: number, max: number): number {
    return min + Math.random() * (max - min);
}

export function randomInteger(min: number, maxInclusive: number): number {
    return Math.floor(randomBetween(min, maxInclusive + 1));
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

export function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Pixel offsets of every grid line across an extent, matching a background
 * tile of the given size positioned at `center center`.
 */
function gridLineOffsets(extent: number, size: number): number[] {
    const firstOffset = ((((extent - size) / 2) % size) + size) % size;
    const offsets: number[] = [];

    for (let offset = firstOffset; offset < extent; offset += size) {
        offsets.push(offset);
    }

    return offsets;
}

function toPath(points: Array<[number, number]>): string {
    return 'M ' + points.map(([x, y]) => `${x} ${y}`).join(' L ');
}

/**
 * Random walk along grid lines that alternates between horizontal and
 * vertical runs, turning only at grid intersections.
 */
export function buildGridWalk(width: number, height: number, layer: GridLayer): BeamPath | null {
    const size = gridSizes[layer];
    const columns = gridLineOffsets(width, size);
    const rows = gridLineOffsets(height, size);

    if (columns.length < 2 || rows.length < 2) {
        return null;
    }

    let column = randomInteger(0, columns.length - 1);
    let row = randomInteger(0, rows.length - 1);
    let isHorizontal = Math.random() < 0.5;
    const points: Array<[number, number]> = [[columns[column] + 0.5, rows[row] + 0.5]];
    let pathLength = 0;

    for (let run = randomInteger(4, 10); run > 0; run--) {
        const lineCount = isHorizontal ? columns.length : rows.length;
        const current = isHorizontal ? column : row;
        const step = randomInteger(1, maxCellsPerRun[layer]) * (Math.random() < 0.5 ? -1 : 1);
        let next = clamp(current + step, 0, lineCount - 1);

        if (next === current) {
            next = clamp(current - step, 0, lineCount - 1);
        }

        if (isHorizontal) {
            column = next;
        } else {
            row = next;
        }

        pathLength += Math.abs(next - current) * size;
        points.push([columns[column] + 0.5, rows[row] + 0.5]);
        isHorizontal = !isHorizontal;
    }

    return { path: toPath(points), pathLength };
}

/** Corners of a box's 1px border, clockwise from the top-left. */
function frameCorners(width: number, height: number): Array<[number, number]> {
    return [
        [0.5, 0.5],
        [width - 0.5, 0.5],
        [width - 0.5, height - 0.5],
        [0.5, height - 0.5],
    ];
}

/**
 * Walk around a box's border from a random corner, for one to four sides in a
 * random direction.
 */
export function buildFrameWalk(width: number, height: number): BeamPath {
    const corners = frameCorners(width, height);
    const direction = Math.random() < 0.5 ? 1 : -1;
    let cornerIndex = randomInteger(0, corners.length - 1);
    const points = [corners[cornerIndex]];
    let pathLength = 0;

    for (let side = randomInteger(1, 4); side > 0; side--) {
        const [fromX, fromY] = corners[cornerIndex];

        cornerIndex = (cornerIndex + direction + corners.length) % corners.length;

        const [toX, toY] = corners[cornerIndex];

        pathLength += Math.abs(toX - fromX) + Math.abs(toY - fromY);
        points.push(corners[cornerIndex]);
    }

    return { path: toPath(points), pathLength };
}

/**
 * Two paths from the top-left corner to the bottom-right one, one along the
 * top and right edges and one along the left and bottom, so drawing both at
 * once traces the whole border.
 */
export function buildFrameHalves(width: number, height: number): { paths: string[]; length: number } {
    const [topLeft, topRight, bottomRight, bottomLeft] = frameCorners(width, height);

    return {
        paths: [toPath([topLeft, topRight, bottomRight]), toPath([topLeft, bottomLeft, bottomRight])],
        length: width + height - 1,
    };
}

/**
 * Dash styling that slides a single dash of `dashLength` along the path so
 * its leading edge travels from the path start to past the path end.
 */
export function beamDashStyle(beam: Beam, dashLength: number): Record<string, string> {
    return {
        strokeDasharray: `${dashLength} ${beam.pathLength + beam.tailLength}`,
        '--dash-from': `${dashLength}px`,
        '--dash-to': `${-(beam.pathLength + beam.tailLength - dashLength)}px`,
        '--beam-duration': `${beam.duration}s`,
    };
}

/**
 * Runs callbacks repeatedly after random delays; `stop()` cancels every
 * pending run.
 */
export function createSpawnScheduler(): { schedule: (spawn: () => void, minDelay: number, maxDelay: number) => void; stop: () => void } {
    const timers = new Set<ReturnType<typeof setTimeout>>();

    function schedule(spawn: () => void, minDelay: number, maxDelay: number): void {
        const timer = setTimeout(
            () => {
                timers.delete(timer);
                spawn();
                schedule(spawn, minDelay, maxDelay);
            },
            randomBetween(minDelay, maxDelay),
        );

        timers.add(timer);
    }

    function stop(): void {
        timers.forEach((timer) => clearTimeout(timer));
        timers.clear();
    }

    return { schedule, stop };
}
