<?php

namespace App\Support\Tabletop;

use App\Models\TabletopLayout;
use App\Models\User;

/** What the pages are told about saved terrain layouts. */
class Layouts
{
    /**
     * A person's own layouts, newest first. The terrain is included so loading one needs no further request.
     *
     * @return list<array<string, mixed>>
     */
    public static function mine(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return TabletopLayout::query()
            ->with('user:id,name')
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->get()
            ->map(fn (TabletopLayout $l) => self::payload($l, $user))
            ->all();
    }

    /** @return array<string, mixed> */
    public static function payload(TabletopLayout $layout, ?User $viewer): array
    {
        return [
            'uuid' => $layout->uuid,
            'name' => $layout->name,
            'description' => $layout->description,
            'terrain' => $layout->terrain,
            'pieces' => count($layout->terrain),
            'table_image' => $layout->table_image,
            'is_shared' => $layout->is_shared,
            'is_public' => $layout->is_public,
            'visibility' => $layout->visibility(),
            'mine' => $viewer !== null && $layout->user_id === $viewer->id,
            'owner' => $layout->relationLoaded('user') ? $layout->user->name : null,
            'copied_from' => $layout->copied_from,
            'updated_at' => $layout->updated_at?->toIso8601String(),
        ];
    }

    /** A layout the viewer may look at: their own, or one its owner shared by link. */
    public static function visibleTo(TabletopLayout $layout, ?User $viewer): bool
    {
        return $layout->is_shared || ($viewer !== null && $layout->user_id === $viewer->id);
    }
}
