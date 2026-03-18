<?php

namespace App\Console\Commands;

use App\Models\Ability;
use App\Models\Action;
use App\Models\Marker;
use App\Models\Token;
use App\Models\Trigger;
use App\Models\Upgrade;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class LinkTokensAndMarkers extends Command
{
    protected $signature = 'app:link-tokens-and-markers {--dry-run : Show what would be linked without making changes}';

    protected $description = 'Search action, trigger, and upgrade descriptions for token/marker references and link them to their characters/upgrades';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $tokens = Token::all();
        $markers = Marker::all();

        $this->info('Searching descriptions for token and marker references...');

        $tokenLinks = $this->findLinks($tokens, 'Token');
        $markerLinks = $this->findLinks($markers, 'Marker');

        $this->newLine();
        $this->info("Found {$tokenLinks['character_count']} token→character links and {$tokenLinks['upgrade_count']} token→upgrade links.");
        $this->info("Found {$markerLinks['character_count']} marker→character links and {$markerLinks['upgrade_count']} marker→upgrade links.");

        if ($dryRun) {
            $this->warn('Dry run — no changes made.');

            return self::SUCCESS;
        }

        $this->syncLinks($tokenLinks['items']);
        $this->syncLinks($markerLinks['items']);

        $this->newLine();
        $this->info('Done! All token and marker links have been synced.');

        return self::SUCCESS;
    }

    private function findLinks(Collection $models, string $type): array
    {
        $results = [];
        $totalCharacterLinks = 0;
        $totalUpgradeLinks = 0;

        $bar = $this->output->createProgressBar($models->count());
        $bar->setFormat(' %current%/%max% [%bar%] %message%');
        $bar->setMessage("Searching {$type}s...");
        $bar->start();

        foreach ($models as $model) {
            $bar->setMessage($model->name);

            $characterIds = collect();
            $upgradeIds = collect();

            // Search Actions for "{Name} Token/Marker"
            $matchingActions = Action::whereNotNull('description')
                ->where('description', 'like', "%{$model->name}%")
                ->with('characters')
                ->get();

            foreach ($matchingActions as $action) {
                if ($this->textContainsReference($action->description, $model->name, $type)) {
                    $characterIds = $characterIds->merge($action->characters->pluck('id'));
                }
            }

            // Search Action descriptions for upgrade-linked actions
            $matchingUpgradeActions = Action::whereNotNull('description')
                ->where('description', 'like', "%{$model->name}%")
                ->with('upgrades')
                ->get();

            foreach ($matchingUpgradeActions as $action) {
                if ($this->textContainsReference($action->description, $model->name, $type)) {
                    $upgradeIds = $upgradeIds->merge($action->upgrades->pluck('id'));
                }
            }

            // Search Abilities for "{Name} Token/Marker"
            $matchingAbilities = Ability::whereNotNull('description')
                ->where('description', 'like', "%{$model->name}%")
                ->with('characters', 'upgrades')
                ->get();

            foreach ($matchingAbilities as $ability) {
                if ($this->textContainsReference($ability->description, $model->name, $type)) {
                    $characterIds = $characterIds->merge($ability->characters->pluck('id'));
                    $upgradeIds = $upgradeIds->merge($ability->upgrades->pluck('id'));
                }
            }

            // Search Triggers for "{Name} Token/Marker"
            $matchingTriggers = Trigger::whereNotNull('description')
                ->where('description', 'like', "%{$model->name}%")
                ->with('actions.characters', 'actions.upgrades')
                ->get();

            foreach ($matchingTriggers as $trigger) {
                if ($this->textContainsReference($trigger->description, $model->name, $type)) {
                    foreach ($trigger->actions as $action) {
                        $characterIds = $characterIds->merge($action->characters->pluck('id'));
                        $upgradeIds = $upgradeIds->merge($action->upgrades->pluck('id'));
                    }
                }
            }

            // Search Upgrade descriptions directly
            $matchingUpgrades = Upgrade::whereNotNull('description')
                ->where('description', 'like', "%{$model->name}%")
                ->get();

            foreach ($matchingUpgrades as $upgrade) {
                if ($this->textContainsReference($upgrade->description, $model->name, $type)) {
                    $upgradeIds->push($upgrade->id);
                }
            }

            $characterIds = $characterIds->unique()->values()->all();
            $upgradeIds = $upgradeIds->unique()->values()->all();

            if (count($characterIds) > 0 || count($upgradeIds) > 0) {
                $results[] = [
                    'model' => $model,
                    'character_ids' => $characterIds,
                    'upgrade_ids' => $upgradeIds,
                ];
                $totalCharacterLinks += count($characterIds);
                $totalUpgradeLinks += count($upgradeIds);

                $this->newLine();
                $this->line("  <comment>{$model->name}</comment>: ".count($characterIds).' characters, '.count($upgradeIds).' upgrades');
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return [
            'items' => $results,
            'character_count' => $totalCharacterLinks,
            'upgrade_count' => $totalUpgradeLinks,
        ];
    }

    /**
     * Check if text contains a reference like "Poison Token" or "Ice Pillar Marker".
     * Uses case-insensitive matching.
     */
    private function textContainsReference(string $text, string $name, string $type): bool
    {
        return stripos($text, "{$name} {$type}") !== false
            || stripos($text, "{$name} {$type}s") !== false;
    }

    private function syncLinks(array $links): void
    {
        foreach ($links as $link) {
            $model = $link['model'];

            // Merge with existing links rather than replacing them
            $existingCharacterIds = $model->characters()->pluck('characters.id')->all();
            $existingUpgradeIds = $model->upgrades()->pluck('upgrades.id')->all();

            $allCharacterIds = array_unique(array_merge($existingCharacterIds, $link['character_ids']));
            $allUpgradeIds = array_unique(array_merge($existingUpgradeIds, $link['upgrade_ids']));

            $model->characters()->sync($allCharacterIds);
            $model->upgrades()->sync($allUpgradeIds);
        }
    }
}
