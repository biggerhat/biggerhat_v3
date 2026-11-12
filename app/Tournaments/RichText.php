<?php

namespace App\Tournaments;

/**
 * Cleans a TipTap document submitted by an organizer before it is stored and shown to everyone.
 *
 * Organizers are ordinary users, so the document is rebuilt from an allow-list: only known node and mark types
 * survive, link targets must be http(s) or mailto, and images must be files this app stored for tournaments.
 * Anything else is dropped rather than escaped. The result is JSON that the Vue renderer turns into elements, never
 * raw HTML.
 */
class RichText
{
    /** Where organizer-uploaded tournament images live (a path prefix on the public disk). */
    public const IMAGE_PREFIX = '/storage/tournaments/';

    public const MAX_DEPTH = 8;

    private const BLOCKS = ['paragraph', 'heading', 'bulletList', 'orderedList', 'listItem', 'blockquote', 'codeBlock', 'horizontalRule', 'image', 'table', 'tableRow', 'tableCell', 'tableHeader'];

    private const MARKS = ['bold', 'italic', 'underline', 'strike', 'code', 'highlight', 'link'];

    private const ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    /**
     * @param  array<string, mixed>|null  $doc
     * @return array<string, mixed>|null null when nothing meaningful is left (empty document)
     */
    public static function sanitize(?array $doc): ?array
    {
        if ($doc === null || ($doc['type'] ?? null) !== 'doc') {
            return null;
        }

        $content = self::nodes((array) ($doc['content'] ?? []), 1);

        return self::hasContent($content) ? ['type' => 'doc', 'content' => $content] : null;
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function nodes(array $nodes, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $clean = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $built = self::node($node, $depth);
            if ($built !== null) {
                $clean[] = $built;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private static function node(array $node, int $depth): ?array
    {
        $type = $node['type'] ?? null;

        if ($type === 'text') {
            $text = (string) ($node['text'] ?? '');
            if ($text === '') {
                return null;
            }
            $out = ['type' => 'text', 'text' => $text];
            $marks = self::marks((array) ($node['marks'] ?? []));
            if ($marks !== []) {
                $out['marks'] = $marks;
            }

            return $out;
        }

        if ($type === 'hardBreak') {
            return ['type' => 'hardBreak'];
        }

        // Inline game symbols (suits, tomes, ...): the icon name is the only attribute.
        if ($type === 'gameIcon') {
            $icon = (string) ($node['attrs']['iconType'] ?? '');

            return preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $icon) === 1 ? ['type' => 'gameIcon', 'attrs' => ['iconType' => $icon]] : null;
        }

        if (! is_string($type) || ! in_array($type, self::BLOCKS, true)) {
            return null;
        }

        $out = ['type' => $type];
        $attrs = self::attrs($type, (array) ($node['attrs'] ?? []));
        if ($type === 'image' && ($attrs['src'] ?? null) === null) {
            return null;
        }
        if ($attrs !== []) {
            $out['attrs'] = $attrs;
        }

        if (! in_array($type, ['image', 'horizontalRule'], true)) {
            $children = self::nodes((array) ($node['content'] ?? []), $depth + 1);
            if ($children !== []) {
                $out['content'] = $children;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private static function attrs(string $type, array $attrs): array
    {
        $out = [];

        if (in_array($type, ['paragraph', 'heading'], true) && in_array($attrs['textAlign'] ?? null, self::ALIGNMENTS, true)) {
            $out['textAlign'] = $attrs['textAlign'];
        }
        if ($type === 'heading') {
            $out['level'] = max(1, min(4, (int) ($attrs['level'] ?? 2)));
        }
        if ($type === 'orderedList' && isset($attrs['start'])) {
            $out['start'] = max(1, min(9999, (int) $attrs['start']));
        }
        if (in_array($type, ['tableCell', 'tableHeader'], true)) {
            foreach (['colspan', 'rowspan'] as $span) {
                if (isset($attrs[$span])) {
                    $out[$span] = max(1, min(12, (int) $attrs[$span]));
                }
            }
        }
        if ($type === 'image') {
            $src = is_string($attrs['src'] ?? null) ? $attrs['src'] : '';
            $out['src'] = self::isOwnImage($src) ? $src : null;
            foreach (['alt', 'title'] as $text) {
                if (is_string($attrs[$text] ?? null) && $attrs[$text] !== '') {
                    $out[$text] = mb_substr($attrs[$text], 0, 200);
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $marks
     * @return list<array<string, mixed>>
     */
    private static function marks(array $marks): array
    {
        $clean = [];
        foreach ($marks as $mark) {
            $type = is_array($mark) ? ($mark['type'] ?? null) : null;
            if (! is_string($type) || ! in_array($type, self::MARKS, true)) {
                continue;
            }
            if ($type === 'link') {
                $href = is_string($mark['attrs']['href'] ?? null) ? trim($mark['attrs']['href']) : '';
                if (! self::isSafeLink($href)) {
                    continue;
                }
                $clean[] = ['type' => 'link', 'attrs' => ['href' => $href]];

                continue;
            }
            $clean[] = ['type' => $type];
        }

        return $clean;
    }

    public static function isSafeLink(string $href): bool
    {
        return mb_strlen($href) <= 2000 && preg_match('#^(https?://|mailto:)[^\s<>"\']+$#i', $href) === 1;
    }

    public static function isOwnImage(string $src): bool
    {
        return str_starts_with($src, self::IMAGE_PREFIX)
            && ! str_contains($src, '..')
            && preg_match('#^/storage/tournaments/[A-Za-z0-9/_\-.]+\.(?:jpe?g|png|gif|webp)$#i', $src) === 1;
    }

    /** @param  list<array<string, mixed>>  $nodes */
    private static function hasContent(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (in_array($node['type'], ['image', 'horizontalRule'], true)) {
                return true;
            }
            if ($node['type'] === 'text' && trim((string) $node['text']) !== '') {
                return true;
            }
            if (self::hasContent($node['content'] ?? [])) {
                return true;
            }
        }

        return false;
    }
}
