export interface ParsedFilters {
    params: Record<string, string>;
}

export const fieldMap: Record<string, string> = {
    f: 'faction',
    faction: 'faction',
    st: 'station',
    station: 'station',
    kw: 'keyword',
    keyword: 'keyword',
    char: 'characteristic',
    characteristic: 'characteristic',
    act: 'action',
    action: 'action',
    ab: 'ability',
    ability: 'ability',
    tr: 'trigger',
    trigger: 'trigger',
    token: 'token',
    marker: 'marker',
    o: 'description',
    text: 'description',
    desc: 'description',
    description: 'description',
    base: 'base',
    is: 'is',
    has: 'has',
    order: 'sort',
    sort: 'sort',
    dir: 'sort_type',
    direction: 'sort_type',
    view: 'page_view',
    display: 'page_view',
};

const numericFieldMap: Record<string, string> = {
    cost: 'cost',
    health: 'health',
    speed: 'speed',
    defense: 'defense',
    wp: 'willpower',
    willpower: 'willpower',
    size: 'size',
    count: 'count',
};

const statNames = new Set(Object.keys(numericFieldMap));

const multiValueFields = new Set(['faction', 'keyword', 'characteristic', 'action', 'ability', 'trigger', 'token', 'marker', 'is', 'has']);

const excludableFields = new Set(['faction', 'keyword', 'characteristic']);

const fieldValueRegex = /^(-?)([\w]+):(.+)$/;
const numericRegex = /^(\w+)(>=|<=|!=|>|<|=)(\d+)$/;
const tokenizeRegex = /(?:[^\s"]+|"[^"]*")+/g;

function stripQuotes(value: string): string {
    if (value.length >= 2 && value.startsWith('"') && value.endsWith('"')) {
        return value.slice(1, -1);
    }
    return value;
}

function appendValue(map: Record<string, string[]>, key: string, value: string): void {
    if (!map[key]) {
        map[key] = [];
    }
    map[key].push(value);
}

export function parseSyntax(input: string): ParsedFilters {
    const params: Record<string, string> = {};

    if (!input || !input.trim()) {
        return { params };
    }

    const tokens = input.match(tokenizeRegex);
    if (!tokens) {
        return { params };
    }

    const fieldValues: Record<string, string[]> = {};
    const fieldExcludes: Record<string, string[]> = {};
    const fieldLogic: Record<string, string> = {};
    const nameWords: string[] = [];

    let i = 0;
    while (i < tokens.length) {
        const token = tokens[i];

        // Handle parenthesized OR groups
        if (token.startsWith('(')) {
            const groupTokens: string[] = [];
            // Strip leading ( from first token
            let first = token.slice(1);
            if (first.endsWith(')')) {
                first = first.slice(0, -1);
            }
            if (first) {
                groupTokens.push(first);
            }

            // If the first token didn't end the group, collect until we hit )
            if (!token.endsWith(')')) {
                i++;
                while (i < tokens.length) {
                    let t = tokens[i];
                    const endsGroup = t.endsWith(')');
                    if (endsGroup) {
                        t = t.slice(0, -1);
                    }
                    if (t && t.toUpperCase() !== 'OR') {
                        groupTokens.push(t);
                    }
                    if (endsGroup) {
                        break;
                    }
                    i++;
                }
            }

            // Parse group tokens to find field and values
            let groupField: string | null = null;
            const groupValues: string[] = [];
            let groupNegated = false;

            for (const gt of groupTokens) {
                const fieldMatch = gt.match(fieldValueRegex);
                if (fieldMatch) {
                    const negated = fieldMatch[1] === '-';
                    const prefix = fieldMatch[2].toLowerCase();
                    const value = stripQuotes(fieldMatch[3]);
                    const resolved = fieldMap[prefix];

                    if (resolved && multiValueFields.has(resolved)) {
                        if (!groupField) {
                            groupField = resolved;
                            groupNegated = negated;
                        }
                        if (resolved === groupField) {
                            groupValues.push(value);
                        }
                    }
                }
            }

            if (groupField && groupValues.length > 0) {
                if (groupNegated && excludableFields.has(groupField)) {
                    for (const v of groupValues) {
                        appendValue(fieldExcludes, groupField, v);
                    }
                } else {
                    for (const v of groupValues) {
                        appendValue(fieldValues, groupField, v);
                    }
                    if (groupValues.length > 1) {
                        fieldLogic[groupField] = 'or';
                    }
                }
            }

            i++;
            continue;
        }

        // Skip standalone OR
        if (token.toUpperCase() === 'OR') {
            i++;
            continue;
        }

        // Skip standalone closing paren (shouldn't happen with well-formed input)
        if (token === ')') {
            i++;
            continue;
        }

        // Stat comparison: defense>willpower, health>=cost, etc.
        const statCompareMatch = token.match(/^(\w+)(>=|<=|>|<|=)(\w+)$/);
        if (statCompareMatch) {
            const left = statCompareMatch[1].toLowerCase();
            const op = statCompareMatch[2];
            const right = statCompareMatch[3].toLowerCase();
            const leftResolved = numericFieldMap[left];
            const rightResolved = numericFieldMap[right];
            if (leftResolved && rightResolved && statNames.has(left) && statNames.has(right)) {
                const existing = params.stat_compare ? params.stat_compare + ',' : '';
                params.stat_compare = existing + `${leftResolved}${op}${rightResolved}`;
                i++;
                continue;
            }
        }

        // Numeric comparison
        const numMatch = token.match(numericRegex);
        if (numMatch) {
            const field = numMatch[1].toLowerCase();
            const operator = numMatch[2];
            const value = parseInt(numMatch[3], 10);
            const resolved = numericFieldMap[field];

            if (resolved) {
                switch (operator) {
                    case '>=':
                        params[`${resolved}_min`] = String(value);
                        break;
                    case '>':
                        params[`${resolved}_min`] = String(value + 1);
                        break;
                    case '<=':
                        params[`${resolved}_max`] = String(value);
                        break;
                    case '<':
                        params[`${resolved}_max`] = String(value - 1);
                        break;
                    case '=':
                        params[`${resolved}_min`] = String(value);
                        params[`${resolved}_max`] = String(value);
                        break;
                    case '!=':
                        // Not supported, skip
                        break;
                }
            } else {
                nameWords.push(token);
            }
            i++;
            continue;
        }

        // Field:value
        const fieldMatch = token.match(fieldValueRegex);
        if (fieldMatch) {
            const negated = fieldMatch[1] === '-';
            const prefix = fieldMatch[2].toLowerCase();
            const value = stripQuotes(fieldMatch[3]);
            const resolved = fieldMap[prefix];

            if (resolved) {
                if (negated && excludableFields.has(resolved)) {
                    appendValue(fieldExcludes, resolved, value);
                } else if (!negated) {
                    if (multiValueFields.has(resolved)) {
                        appendValue(fieldValues, resolved, value);
                    } else {
                        // Single-value fields: last one wins
                        params[resolved] = value;
                    }
                }
                // Negation on non-excludable fields is silently ignored
            } else {
                // Unknown field prefix, treat as name
                nameWords.push(token);
            }
            i++;
            continue;
        }

        // Bare word — part of name search
        nameWords.push(stripQuotes(token));
        i++;
    }

    // Build name param
    if (nameWords.length > 0) {
        params.name = nameWords.join(' ');
    }

    // Build multi-value field params
    for (const [field, values] of Object.entries(fieldValues)) {
        if (values.length > 0) {
            params[field] = values.join(',');
            if (fieldLogic[field]) {
                params[`${field}_logic`] = fieldLogic[field];
            }
        }
    }

    // Build exclude params
    for (const [field, values] of Object.entries(fieldExcludes)) {
        if (values.length > 0) {
            params[`${field}_exclude`] = values.join(',');
        }
    }

    return { params };
}

const numericFields = ['cost', 'health', 'speed', 'defense', 'willpower', 'size', 'count'];

function quoteIfNeeded(value: string): string {
    if (value.includes(' ')) {
        return `"${value}"`;
    }
    return value;
}

function splitValues(value: string | null | undefined): string[] {
    if (!value) return [];
    return value
        .split(',')
        .map((v) => v.trim())
        .filter(Boolean);
}

function serializeMultiValue(prefix: string, values: string[], logic: string | null | undefined): string {
    if (values.length === 0) return '';

    if (logic === 'or' && values.length > 1) {
        const inner = values.map((v) => `${prefix}:${quoteIfNeeded(v)}`).join(' OR ');
        return `(${inner})`;
    }

    return values.map((v) => `${prefix}:${quoteIfNeeded(v)}`).join(' ');
}

function serializeExcludes(prefix: string, values: string[]): string {
    if (values.length === 0) return '';
    return values.map((v) => `-${prefix}:${quoteIfNeeded(v)}`).join(' ');
}

function serializeNumeric(field: string, min: string | null | undefined, max: string | null | undefined): string {
    const parts: string[] = [];

    if (min && max && min === max) {
        parts.push(`${field}=${min}`);
    } else {
        if (min) {
            parts.push(`${field}>=${min}`);
        }
        if (max) {
            parts.push(`${field}<=${max}`);
        }
    }

    return parts.join(' ');
}

export function toSyntax(params: Record<string, string | null | undefined>): string {
    const parts: string[] = [];

    const get = (key: string): string | null | undefined => params[key];

    // 1. Name
    const name = get('name');
    if (name) {
        parts.push(name.includes(' ') ? `"${name}"` : name);
    }

    // 2. Factions
    const factions = splitValues(get('faction'));
    if (factions.length > 0) {
        parts.push(serializeMultiValue('f', factions, get('faction_logic')));
    }

    // 3. Excluded factions
    const factionsExclude = splitValues(get('faction_exclude'));
    if (factionsExclude.length > 0) {
        parts.push(serializeExcludes('f', factionsExclude));
    }

    // 4. Station
    const station = get('station');
    if (station) {
        parts.push(`st:${quoteIfNeeded(station)}`);
    }

    // 5. Keywords
    const keywords = splitValues(get('keyword'));
    if (keywords.length > 0) {
        parts.push(serializeMultiValue('kw', keywords, get('keyword_logic')));
    }

    // 6. Excluded keywords
    const keywordsExclude = splitValues(get('keyword_exclude'));
    if (keywordsExclude.length > 0) {
        parts.push(serializeExcludes('kw', keywordsExclude));
    }

    // 7. Characteristics
    const characteristics = splitValues(get('characteristic'));
    if (characteristics.length > 0) {
        parts.push(serializeMultiValue('char', characteristics, get('characteristic_logic')));
    }

    // 8. Excluded characteristics
    const characteristicsExclude = splitValues(get('characteristic_exclude'));
    if (characteristicsExclude.length > 0) {
        parts.push(serializeExcludes('char', characteristicsExclude));
    }

    // 9. Numeric ranges
    for (const field of numericFields) {
        const min = get(`${field}_min`);
        const max = get(`${field}_max`);
        const serialized = serializeNumeric(field, min, max);
        if (serialized) {
            parts.push(serialized);
        }
    }

    // 10. Base
    const base = get('base');
    if (base) {
        parts.push(`base:${quoteIfNeeded(base)}`);
    }

    // 11. Description
    const description = get('description');
    if (description) {
        parts.push(`o:"${description}"`);
    }

    // 12. Actions
    const actions = splitValues(get('action'));
    if (actions.length > 0) {
        parts.push(serializeMultiValue('act', actions, get('action_logic')));
    }

    // 13. Abilities
    const abilities = splitValues(get('ability'));
    if (abilities.length > 0) {
        parts.push(serializeMultiValue('ab', abilities, get('ability_logic')));
    }

    // 14. Triggers
    const triggers = splitValues(get('trigger'));
    if (triggers.length > 0) {
        parts.push(serializeMultiValue('tr', triggers, get('trigger_logic')));
    }

    // 15. Tokens
    const tokens = splitValues(get('token'));
    if (tokens.length > 0) {
        parts.push(serializeMultiValue('token', tokens, get('token_logic')));
    }

    // 16. Markers
    const markers = splitValues(get('marker'));
    if (markers.length > 0) {
        parts.push(serializeMultiValue('marker', markers, get('marker_logic')));
    }

    // Is/Has filters
    const isFilters = splitValues(get('is'));
    if (isFilters.length > 0) {
        parts.push(isFilters.map((v) => `is:${v}`).join(' '));
    }
    const hasFilters = splitValues(get('has'));
    if (hasFilters.length > 0) {
        parts.push(hasFilters.map((v) => `has:${v}`).join(' '));
    }

    // Stat comparison
    const statCompare = get('stat_compare');
    if (statCompare) {
        parts.push(statCompare.split(',').join(' '));
    }

    // 17. Sort
    const sort = get('sort');
    if (sort) {
        parts.push(`order:${quoteIfNeeded(sort)}`);
    }

    // 18. Direction (only if not default)
    const sortType = get('sort_type');
    if (sortType && sortType !== 'ascending') {
        parts.push(`dir:${quoteIfNeeded(sortType)}`);
    }

    // 19. View (only if not default)
    const pageView = get('page_view');
    if (pageView && pageView !== 'images') {
        parts.push(`view:${quoteIfNeeded(pageView)}`);
    }

    return parts.filter(Boolean).join(' ');
}
