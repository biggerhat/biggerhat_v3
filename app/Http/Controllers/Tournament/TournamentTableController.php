<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentTableRequest;
use App\Models\Tournament;
use App\Models\TournamentTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Per-table notes for rulesets that care about terrain (Malifaux): a name, a description and a photo or layout.
 * A table with nothing left on it has its row removed.
 */
class TournamentTableController extends Controller
{
    use BroadcastsTournamentUpdates;

    /** Highest table number an organizer can describe. */
    private const MAX_TABLE = 200;

    public function save(TournamentTableRequest $request, Tournament $tournament, int $number): JsonResponse
    {
        $this->guard($tournament, $number);
        $validated = $request->validated();

        $table = TournamentTable::firstOrNew(['tournament_id' => $tournament->id, 'number' => $number]);

        $imagePath = $table->image_path;
        if ($request->hasFile('image')) {
            $this->forget($imagePath);
            $imagePath = $request->file('image')->store("tournaments/{$tournament->id}/tables", 'public');
        } elseif (! empty($validated['remove_image'])) {
            $this->forget($imagePath);
            $imagePath = null;
        }

        $name = $this->clean($validated['name'] ?? null);
        $description = $this->clean($validated['description'] ?? null);

        if ($name === null && $description === null && $imagePath === null) {
            if ($table->exists) {
                $table->delete();
            }
            $this->broadcastUpdate($tournament, 'tables_updated');

            return response()->json(['success' => true, 'table' => null]);
        }

        $table->fill(['name' => $name, 'description' => $description, 'image_path' => $imagePath])->save();
        $this->broadcastUpdate($tournament, 'tables_updated');

        return response()->json(['success' => true, 'table' => $table->fresh()]);
    }

    public function destroy(Tournament $tournament, int $number): JsonResponse
    {
        Gate::authorize('manage', $tournament);
        $this->guard($tournament, $number);

        $table = TournamentTable::where('tournament_id', $tournament->id)->where('number', $number)->first();
        if ($table) {
            $this->forget($table->image_path);
            $table->delete();
            $this->broadcastUpdate($tournament, 'tables_updated');
        }

        return response()->json(['success' => true]);
    }

    private function guard(Tournament $tournament, int $number): void
    {
        abort_unless($tournament->rulesetDefinition()->supportsTableDetails, 404);
        abort_unless($number >= 1 && $number <= self::MAX_TABLE, 404);
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function forget(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
