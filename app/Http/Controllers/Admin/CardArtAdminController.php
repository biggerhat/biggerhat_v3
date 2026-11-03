<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FactionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CardArtRequest;
use App\Jobs\Campaign\GenerateLeaderCardImage;
use App\Models\CardArt;
use App\Support\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Admin-curated library of character art for the Custom Card Creator. Players pick from this list; nothing a
 * player uploads is ever stored, so there's no moderation queue — only what's added here can appear on a card.
 */
class CardArtAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        return inertia('Admin/CardArts/Index', [
            'arts' => CardArt::query()
                ->withCount('customCharacters')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (CardArt $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'faction' => $a->faction?->value,
                    'category' => $a->category,
                    'image_path' => $a->image_path,
                    'is_active' => $a->is_active,
                    'sort_order' => $a->sort_order,
                    'in_use' => (int) $a->getAttribute('custom_characters_count'),
                ]),
            'factions' => FactionEnum::toSelectOptions(),
        ]);
    }

    public function create(): Response|ResponseFactory
    {
        return inertia('Admin/CardArts/Form', [
            'art' => null,
            'factions' => FactionEnum::toSelectOptions(),
            'categories' => $this->categories(),
        ]);
    }

    public function edit(CardArt $cardArt): Response|ResponseFactory
    {
        return inertia('Admin/CardArts/Form', [
            'art' => $cardArt->only(['id', 'name', 'faction', 'category', 'credit', 'image_path', 'is_active', 'sort_order']),
            'factions' => FactionEnum::toSelectOptions(),
            'categories' => $this->categories(),
        ]);
    }

    public function store(CardArtRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['image_path'] = $this->storeImage($request->file('image'), $data['slug']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $art = CardArt::create($data);
        AdminAudit::log("Added card art {$art->name}", $art);

        return redirect()->route('admin.card_arts.index')->withMessage("{$art->name} added.");
    }

    public function update(CardArtRequest $request, CardArt $cardArt): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $replacedImage = $request->hasFile('image');
        if ($replacedImage) {
            $old = $cardArt->image_path;
            $data['image_path'] = $this->storeImage($request->file('image'), $cardArt->slug);
            Storage::disk('public')->delete($old);
        }

        $cardArt->update($data);

        // Saved card images have the art baked in, so a new file means re-rendering them.
        if ($replacedImage) {
            $this->regenerateCampaignCards($cardArt);
        }
        AdminAudit::log("Updated card art {$cardArt->name}", $cardArt);

        return redirect()->route('admin.card_arts.index')->withMessage("{$cardArt->name} updated.");
    }

    public function delete(CardArt $cardArt): RedirectResponse
    {
        $name = $cardArt->name;
        $uses = $cardArt->customCharacters()->count();

        // Characters keep rendering without art (the FK is nullOnDelete). Deactivating is the gentler option,
        // so the delete message says how many cards lost their art.
        // Saved card images would otherwise keep the deleted art baked in.
        $campaignCardIds = $this->campaignCardIds($cardArt);

        // Cleared explicitly as well as by the nullOnDelete FK, so it holds on any database setup.
        $cardArt->customCharacters()->update(['card_art_id' => null]);
        Storage::disk('public')->delete($cardArt->image_path);
        $cardArt->delete();
        AdminAudit::log("Deleted card art {$name}", null, ['characters_affected' => $uses]);

        foreach ($campaignCardIds as $id) {
            GenerateLeaderCardImage::dispatch($id)->afterCommit();
        }

        return redirect()->route('admin.card_arts.index')->withMessage($uses > 0 ? "{$name} deleted — {$uses} card(s) lost their art." : "{$name} deleted.");
    }

    /** @return list<int> every character using this art — their saved card images have it baked in */
    private function campaignCardIds(CardArt $cardArt): array
    {
        return $cardArt->customCharacters()->pluck('id')->all();
    }

    private function regenerateCampaignCards(CardArt $cardArt): void
    {
        foreach ($this->campaignCardIds($cardArt) as $id) {
            GenerateLeaderCardImage::dispatch($id)->afterCommit();
        }
    }

    private function storeImage(?UploadedFile $file, string $slug): string
    {
        $filename = sprintf('%s_%s.%s', $slug, Str::uuid(), $file?->extension() ?? 'webp');

        return Storage::disk('public')->putFileAs('card_arts', $file, $filename);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'art';
        $slug = $base;
        $i = 2;
        while (CardArt::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /** @return list<string> */
    private function categories(): array
    {
        return CardArt::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all();
    }
}
