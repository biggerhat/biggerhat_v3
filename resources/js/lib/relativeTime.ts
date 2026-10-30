const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

/** "5 minutes ago", "yesterday", "3 weeks ago". Falls back to "just now" under a minute. */
export function relativeTime(iso: string, now: number = Date.now()): string {
    const seconds = Math.round((new Date(iso).getTime() - now) / 1000);
    const abs = Math.abs(seconds);
    for (const [unit, size] of UNITS) {
        if (abs >= size) return formatter.format(Math.round(seconds / size), unit);
    }
    return 'just now';
}
