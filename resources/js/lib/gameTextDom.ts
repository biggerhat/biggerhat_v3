import { GAME_ICONS, GAME_TEXT_TOKEN_PATTERN, TAG_TO_ICON_TYPE } from '@/lib/gameIcons';

/**
 * DOM helpers for GameTextEditor: game text is stored as plain strings with `{{tag}}` icon tokens, but edited as
 * rich content where each token is a small non-editable icon. Everything here converts between the two.
 */

/** Zero-width space after an icon / line break so the caret always has somewhere to sit. */
const ZWSP = '​';

export const isKnownToken = (token: string) => !!GAME_ICONS[TAG_TO_ICON_TYPE[token.toLowerCase()] ?? ''];

/** A non-editable inline icon (the same M4E-Symbols glyph and colour GameIcon draws) that remembers its token. */
export function createTokenElement(token: string): HTMLElement | null {
    const type = TAG_TO_ICON_TYPE[token.toLowerCase()];
    const icon = type ? GAME_ICONS[type] : undefined;
    if (!icon) return null;

    const span = document.createElement('span');
    span.contentEditable = 'false';
    span.dataset.token = token.toLowerCase();
    span.setAttribute('role', 'img');
    span.setAttribute('aria-label', icon.alt);
    span.title = icon.alt;
    span.className = ["game-icon mx-px inline-block select-none align-baseline font-['M4E-Symbols'] font-normal leading-none", icon.color ?? '']
        .join(' ')
        .trim();
    span.style.fontSize = '1.3em';
    span.textContent = icon.glyph;
    return span;
}

/** Plain text with `{{tag}}` tokens → nodes (text, icons, and <br> for newlines when `multiline`). */
export function textToNodes(text: string, multiline: boolean): Node[] {
    const nodes: Node[] = [];
    const pushText = (chunk: string) => {
        const lines = multiline ? chunk.split('\n') : [chunk.replace(/\n+/g, ' ')];
        lines.forEach((line, i) => {
            if (i > 0) {
                nodes.push(document.createElement('br'), document.createTextNode(ZWSP));
            }
            if (line) nodes.push(document.createTextNode(line));
        });
    };

    let last = 0;
    for (const match of text.matchAll(GAME_TEXT_TOKEN_PATTERN)) {
        pushText(text.slice(last, match.index));
        const el = createTokenElement(match[1]);
        if (el) {
            nodes.push(el, document.createTextNode(ZWSP));
        } else {
            pushText(match[0]);
        }
        last = (match.index ?? 0) + match[0].length;
    }
    pushText(text.slice(last));

    return nodes;
}

/** Rich content → the stored string with `{{tag}}` tokens. */
export function serializeGameText(root: HTMLElement): string {
    let out = '';
    const walk = (node: Node) => {
        node.childNodes.forEach((child) => {
            if (child.nodeType === Node.TEXT_NODE) {
                out += (child.nodeValue ?? '').replace(/​/g, '');
            } else if (child instanceof HTMLElement) {
                if (child.dataset.token) {
                    out += `{{${child.dataset.token}}}`;
                } else if (child.tagName === 'BR') {
                    out += '\n';
                } else {
                    // A browser-made block (<div>/<p>) after Enter or a paste: it starts a new line.
                    if (out && !out.endsWith('\n') && /^(DIV|P)$/.test(child.tagName)) out += '\n';
                    walk(child);
                }
            }
        });
    };
    walk(root);

    return out;
}

/** Replaces the editor's content with `text`. */
export function renderGameText(root: HTMLElement, text: string | null | undefined, multiline: boolean) {
    root.replaceChildren(...textToNodes(text ?? '', multiline));
}

/** Inserts nodes at the caret inside `root` (or at its end when the caret is elsewhere), leaving the caret after them. */
export function insertNodesAtCaret(root: HTMLElement, nodes: Node[]) {
    const selection = window.getSelection();
    let range = selection && selection.rangeCount ? selection.getRangeAt(0) : null;
    if (!range || !root.contains(range.commonAncestorContainer)) {
        range = document.createRange();
        range.selectNodeContents(root);
        range.collapse(false);
    }

    range.deleteContents();
    const fragment = document.createDocumentFragment();
    nodes.forEach((n) => fragment.appendChild(n));
    const lastNode = fragment.lastChild;
    range.insertNode(fragment);

    if (lastNode) {
        range.setStartAfter(lastNode);
        range.collapse(true);
    }
    selection?.removeAllRanges();
    selection?.addRange(range);
    root.dispatchEvent(new Event('input', { bubbles: true }));
}

/** Inserts one icon token at the caret. Returns false when the token isn't a known icon. */
export function insertTokenAtCaret(root: HTMLElement, token: string): boolean {
    const el = createTokenElement(token);
    if (!el) return false;
    insertNodesAtCaret(root, [el, document.createTextNode(ZWSP)]);
    return true;
}
