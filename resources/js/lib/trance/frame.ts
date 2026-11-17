export type CornerName = 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';

export interface CornerMark {
    name: CornerName;
    vertical: 'top' | 'bottom';
    horizontal: 'left' | 'right';
}

export const cornerMarks: readonly CornerMark[] = [
    { name: 'top-left', vertical: 'top', horizontal: 'left' },
    { name: 'top-right', vertical: 'top', horizontal: 'right' },
    { name: 'bottom-left', vertical: 'bottom', horizontal: 'left' },
    { name: 'bottom-right', vertical: 'bottom', horizontal: 'right' },
];

export const bracketCorners: readonly CornerName[] = ['top-left', 'bottom-right'];

/**
 * Geometry for one 3px arm of a corner mark, measured from the frame corner:
 * 17px centred on the 1px frame line as a "+", or 22px along the edge
 * starting 1px past the corner as an "L" bracket.
 */
export function cornerArmStyle(corner: CornerMark, axis: 'x' | 'y', asBracket: boolean): Record<string, string> {
    const along = asBracket ? { offset: '-1px', length: '22px' } : { offset: '-8px', length: '17px' };
    const across = { offset: '-1px', thickness: '3px' };

    return axis === 'x'
        ? { [corner.vertical]: across.offset, height: across.thickness, [corner.horizontal]: along.offset, width: along.length }
        : { [corner.horizontal]: across.offset, width: across.thickness, [corner.vertical]: along.offset, height: along.length };
}
