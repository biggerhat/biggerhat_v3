export interface ParsedFilters {
    params: Record<string, string>;
    // User-facing messages for OR-groups that couldn't be applied (see the
    // cross-field OR-group handling below) — surfaced as a toast by the
    // caller rather than silently dropping the group's second field.
    errors: string[];
}

// Cross-field OR groups (T2-23), e.g. `(f:guild OR kw:ortega)`, are only
// supported for these "simple" fields — each resolves to one straightforward
// where/whereHas condition server-side (SearchController::applyOrGroups()).
// action/ability/trigger/token/marker each have their own multi-column
// sub-filter DSL that doesn't reduce to a single comparable condition, so a
// group mixing one of those in isn't built — see the mixed-group check below.
const OR_GROUP_SIMPLE_FIELDS = new Set(['faction', 'keyword', 'characteristic', 'station', 'base']);
// Canonical field name -> preferred short prefix, for re-serializing an
// or_group param back into syntax-bar text (toSyntax below). Deliberately not
// derived from fieldMap, which maps several prefixes to the same field.
const orGroupFieldPrefix: Record<string, string> = { faction: 'f', keyword: 'kw', characteristic: 'char', station: 'st', base: 'base' };

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
    const errors: string[] = [];

    if (!input || !input.trim()) {
        return { params, errors };
    }

    const tokens = input.match(tokenizeRegex);
    if (!tokens) {
        return { params, errors };
    }

    const fieldValues: Record<string, string[]> = {};
    const fieldExcludes: Record<string, string[]> = {};
    const fieldLogic: Record<string, string> = {};
    const orGroups: string[] = [];
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

            // Parse every field:value pair in the group first — same-field
            // groups (the common case) keep the original collapsing
            // behavior; a group spanning more than one field is a
            // cross-field OR (T2-23), handled separately below.
            const parsedPairs: Array<{ field: string; value: string; negated: boolean }> = [];
            for (const gt of groupTokens) {
                const fieldMatch = gt.match(fieldValueRegex);
                if (fieldMatch) {
                    const negated = fieldMatch[1] === '-';
                    const prefix = fieldMatch[2].toLowerCase();
                    const value = stripQuotes(fieldMatch[3]);
                    const resolved = fieldMap[prefix];
                    if (resolved) {
                        parsedPairs.push({ field: resolved, value, negated });
                    }
                }
            }

            const distinctFields = new Set(parsedPairs.map((p) => p.field));

            if (distinctFields.size > 1) {
                // Cross-field OR group.
                if (parsedPairs.some((p) => p.negated)) {
                    errors.push(`OR-group "${groupTokens.join(' OR ')}" can't mix negation (-field:value) with a cross-field OR — group dropped.`);
                } else if ([...distinctFields].every((f) => OR_GROUP_SIMPLE_FIELDS.has(f))) {
                    orGroups.push(parsedPairs.map((p) => `${p.field}:${p.value}`).join(','));
                } else {
                    const unsupported = [...distinctFields].filter((f) => !OR_GROUP_SIMPLE_FIELDS.has(f));
                    errors.push(
                        `OR-group "${groupTokens.join(' OR ')}" mixes ${unsupported.join(', ')} with another field — that combination isn't supported yet, so this group was dropped.`,
                    );
                }
            } else if (distinctFields.size === 1) {
                // Same-field OR — unchanged from before.
                const [groupField] = distinctFields;
                if (!multiValueFields.has(groupField)) {
                    errors.push(`"${groupField}:" doesn't support OR-grouping — group dropped.`);
                } else {
                    const groupValues = parsedPairs.map((p) => p.value);
                    const groupNegated = parsedPairs[0].negated;
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

    if (orGroups.length > 0) {
        params.or_group = orGroups.join(';');
    }

    return { params, errors };
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

    // Cross-field OR groups (T2-23) — e.g. "faction:guild,keyword:ortega" -> "(f:guild OR kw:ortega)"
    const orGroup = get('or_group');
    if (orGroup) {
        for (const group of orGroup.split(';').filter(Boolean)) {
            const inner = group
                .split(',')
                .filter(Boolean)
                .map((pair) => {
                    const [field, ...rest] = pair.split(':');
                    const value = rest.join(':');
                    const prefix = orGroupFieldPrefix[field] ?? field;
                    return `${prefix}:${quoteIfNeeded(value)}`;
                })
                .join(' OR ');
            if (inner) parts.push(`(${inner})`);
        }
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
