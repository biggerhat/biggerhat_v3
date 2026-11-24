<?php

namespace App\Support\Collection;

use App\Enums\ModelStageEnum;
use App\Models\CollectionModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one place that writes a user's collection of Malifaux models.
 *
 * A collection has two layers. `user_miniatures` says which miniatures a user owns and how many (and keeps built/painted flags
 * that older code reads); `collection_models` has one row per physical model with its own stage and note. Writes here change the
 * model rows and then bring the miniature row in line (`refresh`). `reconcile` goes the other way and repairs the model rows when
 * a miniature row was changed some other way (a seeder, an old import, a test): it never drops what a user recorded, only adds
 * the missing models or trims the least progressed extras.
 */
final class CollectionModels
{
    /** The most copies of one miniature a collection can hold. */
    public const MAX_PER_MINIATURE = 200;

    /**
     * Make the models match the miniature rows (all of the user's, or just the given miniatures).
     *
     * @param  array<int, int>|null  $miniatureIds
     */
    public static function reconcile(User $user, ?array $miniatureIds = null): void
    {
        $rows = DB::table('user_miniatures')->where('user_id', $user->id)
            ->when($miniatureIds !== null, fn ($q) => $q->whereIn('miniature_id', $miniatureIds))
            ->get(['miniature_id', 'quantity', 'is_built', 'is_painted']);
        $have = DB::table('collection_models')->where('user_id', $user->id)
            ->when($miniatureIds !== null, fn ($q) => $q->whereIn('miniature_id', $miniatureIds))
            ->selectRaw('miniature_id, COUNT(*) as c')->groupBy('miniature_id')->pluck('c', 'miniature_id');

        $owned = $rows->pluck('miniature_id');
        // Models of a miniature that is no longer owned go with it.
        $stray = $have->keys()->diff($owned);
        if ($stray->isNotEmpty()) {
            DB::table('collection_models')->where('user_id', $user->id)->whereIn('miniature_id', $stray->all())->delete();
        }

        $touched = [];
        foreach ($rows as $row) {
            $want = max(0, (int) $row->quantity);
            $now = (int) ($have[$row->miniature_id] ?? 0);
            if ($now === $want) {
                continue;
            }
            $touched[] = (int) $row->miniature_id;

            if ($now < $want) {
                // A miniature with no models yet takes its stage from the old built/painted flags; extra copies start unbuilt.
                $stage = $now === 0
                    ? (! empty($row->is_painted) ? ModelStageEnum::Painted : (! empty($row->is_built) ? ModelStageEnum::Built : ModelStageEnum::Unbuilt))
                    : ModelStageEnum::Unbuilt;
                $insert = array_fill(0, $want - $now, [
                    'user_id' => $user->id,
                    'miniature_id' => $row->miniature_id,
                    'stage' => $stage->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                foreach (array_chunk($insert, 500) as $part) {
                    DB::table('collection_models')->insert($part);
                }

                continue;
            }

            // Fewer wanted than held: drop the ones furthest from done.
            $drop = DB::table('collection_models')->where('user_id', $user->id)->where('miniature_id', $row->miniature_id)->get(['id', 'stage'])
                ->sortBy([fn ($a, $b) => ModelStageEnum::from($a->stage)->rank() <=> ModelStageEnum::from($b->stage)->rank(), fn ($a, $b) => $b->id <=> $a->id])
                ->take($now - $want)->pluck('id');
            DB::table('collection_models')->whereIn('id', $drop->all())->delete();
        }

        if ($touched !== []) {
            self::refresh($user, $touched);
        }
    }

    /**
     * Owned copies of a miniature, set to a number. Zero removes it from the collection. More adds unbuilt copies; fewer drops the
     * least progressed ones.
     */
    public static function setQuantity(User $user, int $miniatureId, int $quantity): void
    {
        if ($quantity <= 0) {
            self::remove($user, [$miniatureId]);

            return;
        }
        $quantity = min($quantity, self::MAX_PER_MINIATURE);

        DB::transaction(function () use ($user, $miniatureId, $quantity) {
            self::ensureOwned($user, $miniatureId, $quantity);
            DB::table('user_miniatures')->where('user_id', $user->id)->where('miniature_id', $miniatureId)->update(['quantity' => $quantity, 'updated_at' => now()]);
            self::reconcile($user, [$miniatureId]);
            self::refresh($user, [$miniatureId]);
        });
    }

    /**
     * Put miniatures in the collection (one unbuilt copy each) unless they are already there.
     *
     * @param  iterable<int>  $miniatureIds
     */
    public static function addMissing(User $user, iterable $miniatureIds): void
    {
        $ids = collect($miniatureIds)->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }
        DB::transaction(function () use ($user, $ids) {
            $have = DB::table('user_miniatures')->where('user_id', $user->id)->whereIn('miniature_id', $ids->all())->pluck('miniature_id');
            $new = $ids->diff($have)->values();
            if ($new->isEmpty()) {
                return;
            }
            DB::table('user_miniatures')->insert($new->map(fn (int $id) => [
                'user_id' => $user->id, 'miniature_id' => $id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
            ])->all());
            self::reconcile($user, $new->all());
        });
    }

    /**
     * @param  array<int, int>  $miniatureIds
     */
    public static function remove(User $user, array $miniatureIds): void
    {
        DB::transaction(function () use ($user, $miniatureIds) {
            DB::table('collection_models')->where('user_id', $user->id)->whereIn('miniature_id', $miniatureIds)->delete();
            DB::table('user_miniatures')->where('user_id', $user->id)->whereIn('miniature_id', $miniatureIds)->delete();
        });
    }

    /** One more copy of a miniature, which also puts the miniature in the collection. Null when the limit is reached. */
    public static function addModel(User $user, int $miniatureId, ModelStageEnum $stage = ModelStageEnum::Unbuilt, ?string $note = null): ?CollectionModel
    {
        return DB::transaction(function () use ($user, $miniatureId, $stage, $note) {
            self::reconcile($user, [$miniatureId]);
            $count = CollectionModel::where('user_id', $user->id)->where('miniature_id', $miniatureId)->count();
            if ($count >= self::MAX_PER_MINIATURE) {
                return null;
            }
            self::ensureOwned($user, $miniatureId, $count + 1);
            $model = CollectionModel::create(['user_id' => $user->id, 'miniature_id' => $miniatureId, 'stage' => $stage, 'note' => $note]);
            self::refresh($user, [$miniatureId]);

            return $model;
        });
    }

    public static function updateModel(CollectionModel $model, ?ModelStageEnum $stage, bool $hasNote, ?string $note): void
    {
        $data = [];
        if ($stage !== null) {
            $data['stage'] = $stage;
        }
        if ($hasNote) {
            $data['note'] = $note !== null && trim($note) !== '' ? trim($note) : null;
        }
        if ($data === []) {
            return;
        }
        DB::transaction(function () use ($model, $data) {
            self::reconcile($model->user, [$model->miniature_id]);
            $model->update($data);
            self::refresh($model->user, [$model->miniature_id]);
        });
    }

    /** Take one copy out. The last copy takes the miniature out of the collection. */
    public static function deleteModel(CollectionModel $model): void
    {
        DB::transaction(function () use ($model) {
            $user = $model->user;
            $miniatureId = $model->miniature_id;
            self::reconcile($user, [$miniatureId]);
            $model->delete();
            self::refresh($user, [$miniatureId]);
        });
    }

    /**
     * Mark every copy of the given miniatures built or painted (or not). Built false sets them back to unbuilt; painted false
     * keeps the copy built. Miniatures the user does not own are ignored.
     *
     * @param  array<int, int>  $miniatureIds
     */
    public static function mark(User $user, array $miniatureIds, ?bool $built, ?bool $painted): void
    {
        DB::transaction(function () use ($user, $miniatureIds, $built, $painted) {
            self::reconcile($user, $miniatureIds);
            $scope = fn () => DB::table('collection_models')->where('user_id', $user->id)->whereIn('miniature_id', $miniatureIds);

            if ($built === true) {
                $scope()->where('stage', ModelStageEnum::Unbuilt->value)->update(['stage' => ModelStageEnum::Built->value, 'updated_at' => now()]);
            } elseif ($built === false) {
                $scope()->where('stage', '!=', ModelStageEnum::Unbuilt->value)->update(['stage' => ModelStageEnum::Unbuilt->value, 'updated_at' => now()]);
            }
            if ($painted === true) {
                $scope()->whereNotIn('stage', ModelStageEnum::paintedValues())->update(['stage' => ModelStageEnum::Painted->value, 'updated_at' => now()]);
            } elseif ($painted === false) {
                $scope()->whereIn('stage', ModelStageEnum::paintedValues())->update(['stage' => ModelStageEnum::Built->value, 'updated_at' => now()]);
            }
            self::refresh($user, $miniatureIds);
        });
    }

    /**
     * Every copy of the given miniatures to one stage.
     *
     * @param  array<int, int>  $miniatureIds
     */
    public static function setStage(User $user, array $miniatureIds, ModelStageEnum $stage): void
    {
        DB::transaction(function () use ($user, $miniatureIds, $stage) {
            self::reconcile($user, $miniatureIds);
            DB::table('collection_models')->where('user_id', $user->id)->whereIn('miniature_id', $miniatureIds)->update(['stage' => $stage->value, 'updated_at' => now()]);
            self::refresh($user, $miniatureIds);
        });
    }

    /**
     * Bring the miniature rows in line with their models: the number owned, and built/painted when every copy is.
     *
     * @param  array<int, int>  $miniatureIds
     */
    public static function refresh(User $user, array $miniatureIds): void
    {
        $built = ModelStageEnum::builtValues();
        $painted = ModelStageEnum::paintedValues();
        $rows = DB::table('collection_models')->where('user_id', $user->id)->whereIn('miniature_id', $miniatureIds)
            ->selectRaw(
                'miniature_id, COUNT(*) as c, SUM(CASE WHEN stage IN ('.self::marks($built).') THEN 1 ELSE 0 END) as b, SUM(CASE WHEN stage IN ('.self::marks($painted).') THEN 1 ELSE 0 END) as p',
                [...$built, ...$painted],
            )
            ->groupBy('miniature_id')->get()->keyBy('miniature_id');

        foreach ($miniatureIds as $id) {
            $row = $rows[$id] ?? null;
            $query = DB::table('user_miniatures')->where('user_id', $user->id)->where('miniature_id', $id);
            if ($row === null) {
                $query->delete();

                continue;
            }
            $query->update([
                'quantity' => (int) $row->c,
                'is_built' => (int) $row->b === (int) $row->c,
                'is_painted' => (int) $row->p === (int) $row->c,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * What the user owns, counted by model: totals and a count per stage.
     *
     * @return array{models: int, built: int, painted: int, miniatures: int, stages: array<string, int>}
     */
    public static function totals(User $user): array
    {
        self::reconcile($user);
        $stages = DB::table('collection_models')->where('user_id', $user->id)->selectRaw('stage, COUNT(*) as c')->groupBy('stage')->pluck('c', 'stage');
        $by = [];
        foreach (ModelStageEnum::cases() as $stage) {
            $by[$stage->value] = (int) ($stages[$stage->value] ?? 0);
        }

        return [
            'models' => array_sum($by),
            'built' => array_sum(array_intersect_key($by, array_flip(ModelStageEnum::builtValues()))),
            'painted' => array_sum(array_intersect_key($by, array_flip(ModelStageEnum::paintedValues()))),
            'miniatures' => (int) DB::table('user_miniatures')->where('user_id', $user->id)->count(),
            'stages' => $by,
        ];
    }

    private static function ensureOwned(User $user, int $miniatureId, int $quantity): void
    {
        DB::table('user_miniatures')->insertOrIgnore([
            'user_id' => $user->id, 'miniature_id' => $miniatureId, 'quantity' => $quantity, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  array<int, string>  $values */
    private static function marks(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
