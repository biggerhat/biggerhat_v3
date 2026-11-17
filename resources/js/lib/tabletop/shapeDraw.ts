import type { Shape } from './geometry';

/** The SVG element a shape is drawn with. */
export const shapeTag = (shape: Shape): 'rect' | 'circle' | 'ellipse' | 'polygon' =>
    shape.kind === 'rect' ? 'rect' : shape.kind === 'circle' ? 'circle' : shape.kind === 'ellipse' ? 'ellipse' : 'polygon';

/** The attributes that draw a shape: a turned rectangle or oval carries a transform about its own middle. */
export function shapeAttrs(shape: Shape): Record<string, number | string> {
    if (shape.kind === 'rect') {
        const base = { x: shape.x, y: shape.y, width: shape.w, height: shape.h };
        return shape.angle ? { ...base, transform: `rotate(${shape.angle} ${shape.x + shape.w / 2} ${shape.y + shape.h / 2})` } : base;
    }
    if (shape.kind === 'circle') return { cx: shape.cx, cy: shape.cy, r: shape.r };
    if (shape.kind === 'ellipse') {
        const base = { cx: shape.cx, cy: shape.cy, rx: shape.rx, ry: shape.ry };
        return shape.angle ? { ...base, transform: `rotate(${shape.angle} ${shape.cx} ${shape.cy})` } : base;
    }
    return { points: shape.points.map((p) => p.join(',')).join(' ') };
}
