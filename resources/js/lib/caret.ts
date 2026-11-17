export interface CaretRect {
    top: number;
    left: number;
    width: number;
    height: number;
}

const mirrorStyleProperties = [
    'paddingTop',
    'paddingRight',
    'paddingBottom',
    'paddingLeft',
    'fontFamily',
    'fontSize',
    'fontWeight',
    'fontStyle',
    'lineHeight',
    'letterSpacing',
    'wordSpacing',
    'textTransform',
    'textIndent',
    'tabSize',
] as const;

let fontMetricsContext: CanvasRenderingContext2D | null = null;

function measureFontMetrics(style: CSSStyleDeclaration): { ascent: number; capHeight: number } {
    const fontSize = parseFloat(style.fontSize);

    fontMetricsContext ??= document.createElement('canvas').getContext('2d');

    if (!fontMetricsContext) {
        return { ascent: fontSize * 0.8, capHeight: fontSize * 0.7 };
    }

    fontMetricsContext.font = `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;

    const metrics = fontMetricsContext.measureText('H');

    return {
        ascent: metrics.fontBoundingBoxAscent || fontSize * 0.8,
        capHeight: metrics.actualBoundingBoxAscent || fontSize * 0.7,
    };
}

export function createCaretMirror(textarea: HTMLTextAreaElement): HTMLDivElement {
    const mirror = document.createElement('div');

    mirror.setAttribute('aria-hidden', 'true');
    Object.assign(mirror.style, {
        position: 'absolute',
        top: '0',
        left: '0',
        visibility: 'hidden',
        pointerEvents: 'none',
        boxSizing: 'border-box',
        whiteSpace: 'pre-wrap',
        overflowWrap: 'break-word',
    });
    textarea.parentElement?.appendChild(mirror);

    return mirror;
}

/**
 * Renders the text before the cursor into a hidden mirror of the textarea and
 * returns a block caret centred on the capital letters at that position.
 */
export function measureCaret(textarea: HTMLTextAreaElement, mirror: HTMLDivElement): CaretRect {
    const textareaStyle = getComputedStyle(textarea);

    for (const property of mirrorStyleProperties) {
        mirror.style[property] = textareaStyle[property];
    }

    mirror.style.width = `${textarea.clientWidth}px`;
    mirror.textContent = textarea.value.slice(0, textarea.selectionStart);

    const marker = document.createElement('span');

    marker.textContent = '​';
    mirror.appendChild(marker);

    const fontSize = parseFloat(textareaStyle.fontSize);
    const { ascent, capHeight } = measureFontMetrics(textareaStyle);
    const caretHeight = Math.round(capHeight * 1.7);
    const baseline = marker.offsetTop + ascent;

    return {
        top: textarea.offsetTop - textarea.scrollTop + baseline - capHeight / 2 - caretHeight / 2,
        left: textarea.offsetLeft + marker.offsetLeft - textarea.scrollLeft,
        width: Math.max(4, fontSize * 0.5),
        height: caretHeight,
    };
}
