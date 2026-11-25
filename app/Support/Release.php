<?php

namespace App\Support;

/**
 * Which commit this copy of the site is running, read from the checkout's own `.git` (a deploy that pulls the branch updates it), so
 * a deploy can be told apart from the old code without changing the deploy script. Null when there is no checkout to read.
 */
class Release
{
    /** The full commit hash, or null. */
    public static function sha(?string $gitDir = null): ?string
    {
        $gitDir ??= base_path('.git');
        $head = @file_get_contents($gitDir.'/HEAD');
        if ($head === false) {
            return null;
        }
        $head = trim($head);

        // A detached HEAD holds the hash itself.
        if (preg_match('/^[0-9a-f]{40}$/', $head) === 1) {
            return $head;
        }
        if (! str_starts_with($head, 'ref: ')) {
            return null;
        }
        $ref = substr($head, 5);
        if (preg_match('#^refs/[\w./-]+$#', $ref) !== 1 || str_contains($ref, '..')) {
            return null;
        }

        $loose = @file_get_contents($gitDir.'/'.$ref);
        if ($loose !== false && preg_match('/^[0-9a-f]{40}$/', trim($loose)) === 1) {
            return trim($loose);
        }

        // The ref may be packed (after a gc).
        $packed = @file_get_contents($gitDir.'/packed-refs');
        if ($packed !== false && preg_match('/^([0-9a-f]{40}) '.preg_quote($ref, '/').'$/m', $packed, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
