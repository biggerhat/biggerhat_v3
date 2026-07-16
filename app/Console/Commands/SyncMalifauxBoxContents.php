<?php

namespace App\Console\Commands;

use App\Enums\GameSystemEnum;
use App\Models\Character;
use App\Models\Package;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class SyncMalifauxBoxContents extends Command
{
    protected $signature = 'app:sync-malifaux-box-contents
        {--commit : Write matched quantities/legacy names. Without this flag, only a match report is printed.}
        {--file= : Path to the box-contents JSON data file (defaults to database/data/malifaux_box_contents.json)}';

    protected $description = 'Match the Malifaux core-box contents reference data against existing Characters/Packages and sync per-box character quantities';

    private const STOPWORDS = ['the', 'of', 'a', 'and'];

    /** @var Collection<int, Character> */
    private Collection $allCharacters;

    /** @var Collection<int, Package> */
    private Collection $allPackages;

    public function handle(): int
    {
        $path = $this->option('file') ?: database_path('data/malifaux_box_contents.json');

        if (! File::exists($path)) {
            $this->error("Data file not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode(File::get($path), true);
        $grouped = collect($rows)->groupBy('m4e_box');

        $this->allCharacters = Character::all();
        $this->allPackages = Package::whereIn('game_system', [GameSystemEnum::Malifaux, GameSystemEnum::Both])->get();

        $commit = (bool) $this->option('commit');

        $boxesMatched = 0;
        $boxesUnmatched = [];
        $charactersMatched = 0;
        $charactersUnmatched = [];

        foreach ($grouped as $boxName => $items) {
            $package = $this->matchPackage($boxName);

            if (! $package) {
                $boxesUnmatched[] = $boxName;
                $this->line("<comment>?</comment> {$boxName} — no matching Package");
                foreach ($items as $item) {
                    $this->line("    (skipped) {$item['model_name']}");
                }

                continue;
            }

            $boxesMatched++;
            $this->line("<info>✓</info> {$boxName} → {$package->name}");

            $syncData = [];
            foreach ($items as $item) {
                $character = $this->allCharacters->first(
                    fn (Character $c) => $this->namesMatch($c->display_name, $item['model_name'])
                );

                if ($character) {
                    $charactersMatched++;
                    $syncData[$character->id] = ['quantity' => $item['copies']];
                    $this->line("    <info>✓</info> {$item['model_name']} (x{$item['copies']}) → {$character->display_name}");
                } else {
                    $charactersUnmatched[] = $item['model_name'];
                    $this->line("    <comment>?</comment> {$item['model_name']} — no matching Character");
                }
            }

            if ($commit) {
                $package->characters()->syncWithoutDetaching($syncData);

                $m3eName = $items->pluck('m3e_box')->filter()->first();
                if ($m3eName && ! $package->legacy_m3e_name) {
                    $package->update(['legacy_m3e_name' => $m3eName]);
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Boxes matched: %d/%d. Characters matched: %d/%d.',
            $boxesMatched,
            $grouped->count(),
            $charactersMatched,
            $charactersMatched + count($charactersUnmatched),
        ));

        if ($boxesUnmatched) {
            $this->warn('Unmatched boxes ('.count($boxesUnmatched).'): '.implode('; ', $boxesUnmatched));
        }
        if ($charactersUnmatched) {
            $this->warn('Unmatched characters ('.count($charactersUnmatched).'): '.implode('; ', array_unique($charactersUnmatched)));
        }

        if (! $commit) {
            $this->newLine();
            $this->comment('Dry run only — re-run with --commit to write quantities and legacy box names.');
        }

        return self::SUCCESS;
    }

    private function normalize(string $s): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $s) ?? $s));
    }

    /**
     * Mirrors ImportWyrdPackages::namesMatch() — handles the PDF sometimes
     * listing a bare name where the Character's display_name carries an
     * epithet ("Sonnia Criid" vs. "Sonnia Criid, Unrelenting").
     */
    private function namesMatch(string $displayName, string $contentName): bool
    {
        $a = $this->normalize($displayName);
        $b = $this->normalize($contentName);

        if ($a === $b) {
            return true;
        }

        return str_starts_with($a, $b) || str_starts_with($b, $a);
    }

    /**
     * The PDF's "M4E Box" column is a shorthand (e.g. "Sandeep Font of
     * Magic") that omits words present in the real Package name ("Malifaux
     * Fourth Edition: Sandeep Desai, Font of Magic") and occasionally
     * contains a typo ("Sandeep The Quite Flame"). Score candidates by the
     * fraction of significant words found in the Package name and accept
     * the best match above a tolerance threshold, so a single-word typo
     * doesn't block an otherwise-clear match.
     */
    private function matchPackage(string $boxName): ?Package
    {
        $tokens = array_values(array_filter(
            explode(' ', $this->normalize($boxName)),
            fn (string $t) => strlen($t) > 2 && ! in_array($t, self::STOPWORDS, true),
        ));

        if (empty($tokens)) {
            return null;
        }

        $tokenCount = count($tokens);
        // Allow exactly one missing/typo'd token when there are enough
        // tokens to make that tolerance safe (e.g. "Sandeep The Quite
        // Flame" — a real typo in the source PDF — still finds "Sandeep
        // The Quiet Flame"). A 1-2 token box name must match in full.
        $minFound = $tokenCount <= 2 ? $tokenCount : $tokenCount - 1;

        $bestFound = -1;
        $bestPackages = collect();

        foreach ($this->allPackages as $package) {
            $normalizedName = $this->normalize($package->name);
            $found = 0;
            foreach ($tokens as $token) {
                if (str_contains($normalizedName, $token)) {
                    $found++;
                }
            }

            if ($found > $bestFound) {
                $bestFound = $found;
                $bestPackages = collect([$package]);
            } elseif ($found === $bestFound && $found > 0) {
                $bestPackages->push($package);
            }
        }

        if ($bestFound < $minFound || $bestPackages->count() !== 1) {
            return null;
        }

        return $bestPackages->first();
    }
}
