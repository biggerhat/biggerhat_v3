<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Blueprint;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

class TrashAdminController extends Controller
{
    /**
     * Soft-deletable models we expose in the Trash UI. Add new entries here as
     * more models pick up SoftDeletes.
     *
     * @var array<string, array{class: class-string<\Illuminate\Database\Eloquent\Model>, label: string, name_attr: string}>
     */
    private const MODELS = [
        'tournament' => ['class' => Tournament::class, 'label' => 'Tournaments', 'name_attr' => 'name'],
        'blog_post' => ['class' => BlogPost::class, 'label' => 'Blog Posts', 'name_attr' => 'title'],
        'blueprint' => ['class' => Blueprint::class, 'label' => 'Blueprints', 'name_attr' => 'name'],
        'custom_character' => ['class' => CustomCharacter::class, 'label' => 'Custom Characters', 'name_attr' => 'display_name'],
        'custom_upgrade' => ['class' => CustomUpgrade::class, 'label' => 'Custom Upgrades', 'name_attr' => 'display_name'],
    ];

    public function index(Request $request): Response|ResponseFactory
    {
        $kind = $request->string('kind')->toString() ?: array_key_first(self::MODELS);
        $config = self::MODELS[$kind] ?? self::MODELS[array_key_first(self::MODELS)];

        $nameAttr = $config['name_attr'];
        /** @var \Illuminate\Database\Eloquent\Collection<int, Model> $records */
        $records = $config['class']::onlyTrashed()
            ->latest('deleted_at')
            ->limit(200)
            ->get();

        $rows = $records->map(function (Model $m) use ($nameAttr) {
            $deletedAt = $m->getAttribute('deleted_at');

            return [
                'id' => $m->getKey(),
                'name' => $m->getAttribute($nameAttr) ?? '(unnamed)',
                'deleted_at' => $deletedAt instanceof \DateTimeInterface ? $deletedAt->format('c') : $deletedAt,
            ];
        });

        $counts = collect(self::MODELS)
            ->mapWithKeys(fn ($cfg, $key) => [$key => $cfg['class']::onlyTrashed()->count()])
            ->all();

        return inertia('Admin/Trash/Index', [
            'kind' => $kind,
            'rows' => $rows,
            'tabs' => collect(self::MODELS)->map(fn ($cfg, $key) => [
                'key' => $key,
                'label' => $cfg['label'],
                'count' => $counts[$key],
            ])->values(),
        ]);
    }

    public function restore(Request $request, string $kind, int $id): RedirectResponse
    {
        $model = $this->resolve($kind, $id);
        if (! $model) {
            return back()->withMessage('Record not found.');
        }
        // SoftDeletes::restore() is dynamically attached by the trait. PHPStan
        // can't see it through the abstract Model parent of the union, so call
        // via call_user_func to keep the type-checker quiet without sacrificing
        // the runtime contract (the trait is required on every entry in self::MODELS).
        \call_user_func([$model, 'restore']);

        return back()->withMessage('Record restored.');
    }

    public function forceDestroy(Request $request, string $kind, int $id): RedirectResponse
    {
        $model = $this->resolve($kind, $id);
        if (! $model) {
            return back()->withMessage('Record not found.');
        }
        \call_user_func([$model, 'forceDelete']);

        return back()->withMessage('Record permanently deleted.');
    }

    /**
     * @return Model|null Concrete returned class always uses the SoftDeletes trait.
     */
    private function resolve(string $kind, int $id): ?Model
    {
        $config = self::MODELS[$kind] ?? null;
        if (! $config) {
            return null;
        }

        /** @var Model|null $model */
        $model = $config['class']::onlyTrashed()->find($id);

        return $model;
    }
}
