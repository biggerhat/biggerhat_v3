/** How each stage of a collected model is drawn: a dot, never colour alone (the dot always sits beside a label or has an accessible name). */
export const STAGE_DOT: Record<string, string> = {
    unbuilt: 'bg-transparent ring-1 ring-inset ring-muted-foreground/70',
    built: 'bg-amber-600',
    primed: 'bg-sky-500',
    painted: 'bg-violet-600',
    based: 'bg-emerald-600',
};

export const STAGE_LABEL: Record<string, string> = {
    unbuilt: 'unbuilt',
    built: 'built',
    primed: 'primed',
    painted: 'painted',
    based: 'based',
};

/** "2 painted, 1 unbuilt" for a list of stages, in stage order. */
export function describeStages(stages: string[]): string {
    const order = Object.keys(STAGE_LABEL);
    return order
        .map((s) => [s, stages.filter((x) => x === s).length] as const)
        .filter(([, n]) => n > 0)
        .map(([s, n]) => `${n} ${STAGE_LABEL[s]}`)
        .join(', ');
}
