<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Token;
use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TokenAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Tokens/Index', [
            'tokens' => Token::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Tokens/TokenForm', $this->getFormData());
    }

    public function edit(Request $request, Token $token)
    {
        return inertia('Admin/Tokens/TokenForm', array_merge(
            ['token' => $token->loadMissing(['characters', 'upgrades'])],
            $this->getFormData(),
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'characters' => ['nullable', 'array'],
            'upgrades' => ['nullable', 'array'],
        ]);

        $characterIds = Character::whereIn('slug', $validated['characters'] ?? [])->pluck('id');
        $upgradeIds = Upgrade::whereIn('slug', $validated['upgrades'] ?? [])->pluck('id');
        unset($validated['characters'], $validated['upgrades']);

        $validated['slug'] = Str::slug($validated['name']);

        $token = Token::create($validated);
        $token->characters()->sync($characterIds);
        $token->upgrades()->sync($upgradeIds);

        return redirect()->route('admin.tokens.index')->withMessage("{$token->name} created successfully.");
    }

    public function update(Request $request, Token $token)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'characters' => ['nullable', 'array'],
            'upgrades' => ['nullable', 'array'],
        ]);

        $characterIds = Character::whereIn('slug', $validated['characters'] ?? [])->pluck('id');
        $upgradeIds = Upgrade::whereIn('slug', $validated['upgrades'] ?? [])->pluck('id');
        unset($validated['characters'], $validated['upgrades']);

        $token->update($validated);
        $token->characters()->sync($characterIds);
        $token->upgrades()->sync($upgradeIds);

        return redirect()->route('admin.tokens.index')->withMessage("{$token->name} has been updated.");
    }

    public function delete(Request $request, Token $token)
    {
        $name = $token->name;
        $token->delete();

        return redirect()->route('admin.tokens.index')->withMessage("{$name} has been deleted.");
    }

    private function getFormData(): array
    {
        return [
            'all_characters' => fn () => Character::orderBy('display_name')->toSelectOptions('display_name', 'slug'),
            'all_upgrades' => fn () => Upgrade::orderBy('name')->toSelectOptions('name', 'slug'),
        ];
    }
}
