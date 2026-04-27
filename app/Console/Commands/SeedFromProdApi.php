<?php

namespace App\Console\Commands;

use App\Enums\BaseSizeEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Enums\UpgradeLimitationEnum;
use App\Enums\UpgradeTypeEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use App\Models\Marker;
use App\Models\Miniature;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Token;
use App\Models\Trigger;
use App\Models\Upgrade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeedFromProdApi extends Command
{
    protected $signature = 'app:seed-from-prod
        {--skip-images : Skip downloading images}
        {--only= : Only seed specific types (characters,keywords,upgrades,markers,tokens,strategies,schemes,actions,abilities)}';

    protected $description = 'Seed the local database from the production BiggerHat API';

    private const BASE_URL = 'https://biggerhat.net/api/v1';

    private int $imageCount = 0;

    public function handle(): int
    {
        $only = $this->option('only')
            ? explode(',', $this->option('only'))
            : ['keywords', 'markers', 'tokens', 'characters', 'upgrades', 'strategies', 'schemes', 'actions', 'abilities'];

        $seeders = [
            'keywords' => fn () => $this->seedKeywords(),
            'markers' => fn () => $this->seedMarkers(),
            'tokens' => fn () => $this->seedTokens(),
            'characters' => fn () => $this->seedCharacters(),
            'upgrades' => fn () => $this->seedUpgrades(),
            'strategies' => fn () => $this->seedStrategies(),
            'schemes' => fn () => $this->seedSchemes(),
            'actions' => fn () => $this->seedActionsAndTriggers(),
            'abilities' => fn () => $this->seedAbilities(),
        ];

        $ran = 0;
        foreach ($seeders as $key => $seeder) {
            if (in_array($key, $only)) {
                if ($ran > 0) {
                    $this->info('  Waiting 5s before next endpoint...');
                    sleep(5);
                }
                $seeder();
                $ran++;
            }
        }

        $this->newLine();
        $this->info("Seeding complete! Downloaded {$this->imageCount} images.");

        return self::SUCCESS;
    }

    private function fetchAllPages(string $endpoint): array
    {
        $items = [];
        $page = 1;
        $lastPage = 1;

        do {
            $response = $this->fetchWithRetry(self::BASE_URL.$endpoint, ['page' => $page]);

            if (! $response) {
                $this->error("Failed to fetch {$endpoint} page {$page} after retries.");
                break;
            }

            $data = $response;

            // Handle non-paginated responses (plain array)
            if (! isset($data['data'])) {
                return $data;
            }

            $items = array_merge($items, $data['data']);
            $lastPage = $data['meta']['last_page'] ?? 1;

            if ($page === 1) {
                $total = $data['meta']['total'] ?? count($items);
                $this->info("  Fetching {$total} items across {$lastPage} pages...");
            }

            $page++;

            // Delay between pages to avoid rate limiting
            usleep(500_000);
        } while ($page <= $lastPage);

        return $items;
    }

    private function fetchWithRetry(string $url, array $params, int $maxRetries = 3): ?array
    {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = Http::get($url, $params);

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->status() === 429) {
                $wait = $attempt * 2;
                $this->warn("  Rate limited, waiting {$wait}s (attempt {$attempt}/{$maxRetries})...");
                sleep($wait);

                continue;
            }

            $this->error("  HTTP {$response->status()} for {$url}");
            break;
        }

        return null;
    }

    private function seedKeywords(): void
    {
        $this->newLine();
        $this->info('Seeding keywords...');

        $items = $this->fetchAllPages('/keywords');

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        foreach ($items as $item) {
            Keyword::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('  Keywords seeded: '.count($items));
    }

    private function seedMarkers(): void
    {
        $this->newLine();
        $this->info('Seeding markers...');

        $items = $this->fetchAllPages('/markers');

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        foreach ($items as $item) {
            Marker::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'base' => $this->matchBaseSizeEnum($item['base'] ?? null),
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('  Markers seeded: '.count($items));
    }

    private function seedTokens(): void
    {
        $this->newLine();
        $this->info('Seeding tokens...');

        $items = $this->fetchAllPages('/tokens');

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        foreach ($items as $item) {
            Token::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('  Tokens seeded: '.count($items));
    }

    private function seedCharacters(): void
    {
        $this->newLine();
        $this->info('Seeding characters...');

        $items = $this->fetchAllPages('/characters');

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        foreach ($items as $item) {
            $character = Character::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'title' => $item['title'],
                    'display_name' => $item['display_name'],
                    'nicknames' => $item['nicknames'],
                    'faction' => $item['faction'],
                    'second_faction' => $item['second_faction'],
                    'station' => $item['station'],
                    'cost' => $item['cost'],
                    'health' => $item['health'],
                    'size' => $item['size'],
                    'base' => $this->matchBaseSizeEnum($item['base'] ?? null),
                    'defense' => $item['defense'],
                    'defense_suit' => $item['defense_suit'],
                    'willpower' => $item['willpower'],
                    'willpower_suit' => $item['willpower_suit'],
                    'speed' => $item['speed'],
                    'count' => $item['count'] ?? 1,
                    'summon_target_number' => $item['summon_target_number'],
                    'generates_stone' => $item['generates_stone'] ?? false,
                    'is_unhirable' => $item['is_unhirable'] ?? false,
                    'is_beta' => $item['is_beta'] ?? false,
                ]
            );

            // Sync keywords
            if (! empty($item['keywords'])) {
                $keywordIds = [];
                foreach ($item['keywords'] as $kw) {
                    $keyword = Keyword::firstOrCreate(
                        ['slug' => $kw['slug']],
                        ['name' => $kw['name']]
                    );
                    $keywordIds[] = $keyword->id;
                }
                $character->keywords()->sync($keywordIds);
            }

            // Sync characteristics (always sync to clear stale data)
            $characteristicIds = [];
            foreach ($item['characteristics'] ?? [] as $charName) {
                $characteristic = Characteristic::firstOrCreate(
                    ['slug' => str($charName)->slug()->toString()],
                    ['name' => ucfirst($charName)]
                );
                $characteristicIds[] = $characteristic->id;
            }
            $character->characteristics()->sync($characteristicIds);

            // Sync miniatures
            if (! empty($item['miniatures'])) {
                $seenSlugs = [];
                foreach ($item['miniatures'] as $mini) {
                    // Make slug unique per character when multiple miniatures share the same slug
                    $baseSlug = $mini['slug'];
                    if (isset($seenSlugs[$baseSlug])) {
                        $seenSlugs[$baseSlug]++;
                        $uniqueSlug = $baseSlug.'-v'.$seenSlugs[$baseSlug];
                    } else {
                        $seenSlugs[$baseSlug] = 1;
                        $uniqueSlug = $baseSlug;
                    }

                    $displayName = $mini['display_name'] ?? $item['display_name'];

                    $miniature = Miniature::updateOrCreate(
                        ['character_id' => $character->id, 'slug' => $uniqueSlug, 'version' => $mini['version']],
                        [
                            'name' => $mini['name'],
                            'title' => $mini['title'],
                            'display_name' => $displayName,
                        ]
                    );

                    if (! $this->option('skip-images')) {
                        $this->downloadMiniatureImages($miniature, $mini, $character->id);
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Second pass: resolve totem slugs to local IDs (all characters exist now)
        $totemCount = 0;
        foreach ($items as $item) {
            if (! empty($item['totem_slug'])) {
                $character = Character::where('slug', $item['slug'])->first();
                $totem = Character::where('slug', $item['totem_slug'])->first();
                if ($character && $totem) {
                    $character->has_totem_id = $totem->id;
                    $character->save();
                    $totemCount++;
                }
            }
        }

        $this->info('  Characters seeded: '.count($items));
        if ($totemCount > 0) {
            $this->info("  Totem links resolved: {$totemCount}");
        }
    }

    private function seedUpgrades(): void
    {
        $this->newLine();
        $this->info('Seeding upgrades...');

        $items = $this->fetchAllPages('/upgrades');

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        foreach ($items as $item) {
            $upgrade = Upgrade::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'domain' => $this->matchDomainEnum($item['domain'] ?? 'character'),
                    'description' => $item['description'] ?? null,
                    'plentiful' => $item['plentiful'] ?? null,
                    'faction' => $item['faction'] ?? null,
                    'type' => $this->matchUpgradeTypeEnum($item['type'] ?? null),
                    'limitations' => $this->matchLimitationsEnum($item['limitations'] ?? null),
                ]
            );

            if (! $this->option('skip-images')) {
                $this->downloadUpgradeImages($upgrade, $item);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('  Upgrades seeded: '.count($items));

        // Link crew upgrades to their masters
        $this->linkCrewUpgradesToMasters();
    }

    private function linkCrewUpgradesToMasters(): void
    {
        $this->info('  Linking crew upgrades to masters...');

        // Fetch crew upgrades with master linkage from production API
        $baseUrl = str_replace('/v1', '', self::BASE_URL);
        $response = $this->fetchWithRetry($baseUrl.'/upgrades/crew', ['name' => '']);

        if (! $response) {
            $this->warn('  Could not fetch crew upgrade linkage data, skipping.');

            return;
        }

        // Handle both paginated (with 'data' key) and plain array responses
        $items = $response['data'] ?? $response;

        $linked = 0;
        foreach ($items as $item) {
            $upgrade = Upgrade::where('slug', $item['slug'])->first();
            if (! $upgrade) {
                continue;
            }

            $masterIds = [];
            foreach ($item['masters'] ?? [] as $master) {
                $character = Character::where('slug', $master['slug'])->first();
                if ($character) {
                    $masterIds[] = $character->id;
                }
            }

            if (! empty($masterIds)) {
                $upgrade->characters()->sync($masterIds);
                $linked++;
            }
        }

        $this->info("  Crew upgrade links resolved: {$linked}");
    }

    private function seedStrategies(): void
    {
        $this->newLine();
        $this->info('Seeding strategies...');

        $items = $this->fetchAllPages('/strategies');

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        foreach ($items as $item) {
            Strategy::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'season' => $item['season'] ?? null,
                    'suit' => $item['suit'] ?? null,
                    'setup' => $item['setup'] ?? null,
                    'rules' => $item['rules'] ?? null,
                    'scoring' => $item['scoring'] ?? null,
                    'additional_scoring' => $item['additional_scoring'] ?? null,
                    'image' => $item['image'] ?? null,
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('  Strategies seeded: '.count($items));
    }

    private function seedSchemes(): void
    {
        $this->newLine();
        $this->info('Seeding schemes...');

        $items = $this->fetchAllPages('/schemes');

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        foreach ($items as $item) {
            Scheme::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'season' => $item['season'] ?? null,
                    'selector' => $item['selector'] ?? null,
                    'prerequisite' => $item['prerequisite'] ?? null,
                    'reveal' => $item['reveal'] ?? null,
                    'scoring' => is_array($item['scoring'] ?? null) ? json_encode($item['scoring']) : ($item['scoring'] ?? null),
                    'additional' => is_array($item['additional'] ?? null) ? json_encode($item['additional']) : ($item['additional'] ?? null),
                    'image' => $item['image'] ?? null,
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('  Schemes seeded: '.count($items));
    }

    // ─── Actions, Triggers & Abilities ───

    private function seedActionsAndTriggers(): void
    {
        $this->newLine();
        $this->info('Seeding actions, triggers & character linkage...');

        // Fetch all character slugs from the local database
        $characterSlugs = Character::pluck('slug')->all();

        if (empty($characterSlugs)) {
            $this->warn('  No characters in database. Run characters seeder first.');

            return;
        }

        $this->info('  Fetching details for '.count($characterSlugs).' characters...');

        $bar = $this->output->createProgressBar(count($characterSlugs));
        $bar->start();

        $actionCount = 0;
        $triggerCount = 0;
        $linkCount = 0;

        foreach ($characterSlugs as $slug) {
            $data = $this->fetchWithRetry(self::BASE_URL."/characters/{$slug}", []);

            if (! $data || ! isset($data['data'])) {
                $bar->advance();
                usleep(300_000);

                continue;
            }

            $charData = $data['data'];
            $character = Character::where('slug', $slug)->first();

            if (! $character || empty($charData['actions'])) {
                $bar->advance();
                usleep(300_000);

                continue;
            }

            $actionIds = [];

            foreach ($charData['actions'] as $actionData) {
                $action = $this->findOrCreateAction($actionData);
                $actionIds[$action->id] = ['is_signature_action' => $actionData['is_signature'] ?? false];
                $actionCount++;

                // Sync triggers for this action
                $triggerIds = [];
                foreach ($actionData['triggers'] ?? [] as $triggerData) {
                    $trigger = $this->findOrCreateTrigger($triggerData);
                    $triggerIds[] = $trigger->id;
                    $triggerCount++;
                }

                if (! empty($triggerIds)) {
                    $action->triggers()->syncWithoutDetaching($triggerIds);
                }
            }

            $character->actions()->sync($actionIds);
            $linkCount++;

            $bar->advance();

            // Rate limiting between character fetches
            usleep(300_000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Actions processed: {$actionCount}, Triggers processed: {$triggerCount}, Characters linked: {$linkCount}");
    }

    private function findOrCreateAction(array $data): Action
    {
        // Match by slug (the enforced unique key) — name lookups collide
        // when two source rows differ only in punctuation that the slugger
        // strips (e.g. `"Mighty" Jaws` vs `Mighty Jaws` both → mighty-jaws).
        $slug = Str::slug($data['name']);

        return Action::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $data['name'],
                'type' => $data['type'] ?? 'attack',
                'is_signature' => $data['is_signature'] ?? false,
                'stone_cost' => $data['stone_cost'] ?? 0,
                'range' => $data['range'],
                'range_type' => $data['range_type'],
                'stat' => $data['stat'],
                'stat_suits' => $data['stat_suits'],
                'stat_modifier' => $data['stat_modifier'],
                'resisted_by' => $data['resisted_by'],
                'target_number' => $data['target_number'],
                'target_suits' => $data['target_suits'],
                'description' => $data['description'],
                'damage' => $data['damage'],
            ]
        );
    }

    private function findOrCreateTrigger(array $data): Trigger
    {
        return Trigger::updateOrCreate(
            ['name' => $data['name'], 'suits' => $data['suits'] ?? null],
            [
                'stone_cost' => $data['stone_cost'] ?? 0,
                'description' => $data['description'] ?? null,
            ]
        );
    }

    private function seedAbilities(): void
    {
        $this->newLine();
        $this->info('Seeding abilities & character linkage...');

        $characterSlugs = Character::pluck('slug')->all();

        if (empty($characterSlugs)) {
            $this->warn('  No characters in database. Run characters seeder first.');

            return;
        }

        $this->info('  Fetching details for '.count($characterSlugs).' characters...');

        $bar = $this->output->createProgressBar(count($characterSlugs));
        $bar->start();

        $abilityCount = 0;
        $linkCount = 0;

        foreach ($characterSlugs as $slug) {
            $data = $this->fetchWithRetry(self::BASE_URL."/characters/{$slug}", []);

            if (! $data || ! isset($data['data'])) {
                $bar->advance();
                usleep(300_000);

                continue;
            }

            $charData = $data['data'];
            $character = Character::where('slug', $slug)->first();

            if (! $character || empty($charData['abilities'])) {
                $bar->advance();
                usleep(300_000);

                continue;
            }

            $abilityIds = [];

            foreach ($charData['abilities'] as $abilityData) {
                $ability = Ability::updateOrCreate(
                    ['name' => $abilityData['name']],
                    [
                        'suits' => $abilityData['suits'] ?? null,
                        'defensive_ability_type' => $abilityData['defensive_ability_type'] ?? null,
                        'costs_stone' => $abilityData['costs_stone'] ?? false,
                        'description' => $abilityData['description'] ?? null,
                    ]
                );
                $abilityIds[] = $ability->id;
                $abilityCount++;
            }

            $character->abilities()->sync($abilityIds);
            $linkCount++;

            $bar->advance();
            usleep(300_000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Abilities processed: {$abilityCount}, Characters linked: {$linkCount}");
    }

    // ─── Image Downloading ───

    private function downloadMiniatureImages(Miniature $miniature, array $data, int $characterId): void
    {
        $dir = "characters/{$characterId}";

        $updated = false;

        if (! empty($data['front_image']) && ! $miniature->front_image) {
            $path = $this->downloadImage($data['front_image'], $dir, "{$characterId}_{$miniature->id}_front");
            if ($path) {
                $miniature->front_image = $path;
                $updated = true;
            }
        }

        if (! empty($data['back_image']) && ! $miniature->back_image) {
            $path = $this->downloadImage($data['back_image'], $dir, "{$characterId}_{$miniature->id}_back");
            if ($path) {
                $miniature->back_image = $path;
                $updated = true;
            }
        }

        if (! empty($data['combination_image']) && ! $miniature->combination_image) {
            $path = $this->downloadImage($data['combination_image'], $dir, "{$characterId}_{$miniature->id}_combo");
            if ($path) {
                $miniature->combination_image = $path;
                $updated = true;
            }
        }

        if ($updated) {
            $miniature->save();
        }
    }

    private function downloadUpgradeImages(Upgrade $upgrade, array $data): void
    {
        $dir = "upgrades/{$upgrade->slug}";
        $updated = false;

        if (! empty($data['front_image']) && ! $upgrade->front_image) {
            $path = $this->downloadImage($data['front_image'], $dir, "{$upgrade->slug}_front");
            if ($path) {
                $upgrade->front_image = $path;
                $updated = true;
            }
        }

        if (! empty($data['back_image']) && ! $upgrade->back_image) {
            $path = $this->downloadImage($data['back_image'], $dir, "{$upgrade->slug}_back");
            if ($path) {
                $upgrade->back_image = $path;
                $updated = true;
            }
        }

        if (! empty($data['combination_image']) && ! $upgrade->combination_image) {
            $path = $this->downloadImage($data['combination_image'], $dir, "{$upgrade->slug}_combo");
            if ($path) {
                $upgrade->combination_image = $path;
                $updated = true;
            }
        }

        if ($updated) {
            $upgrade->save();
        }
    }

    private function downloadImage(string $url, string $dir, string $name): ?string
    {
        try {
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $path = "{$dir}/{$name}.{$extension}";

            if (Storage::disk('public')->exists($path)) {
                return $path;
            }

            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                return null;
            }

            Storage::disk('public')->makeDirectory($dir);
            Storage::disk('public')->put($path, $response->body());
            $this->imageCount++;

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    // ─── Enum Matching ───

    private function matchBaseSizeEnum(mixed $value): int
    {
        if (! $value) {
            return BaseSizeEnum::ThirtyMM->value;
        }

        foreach (BaseSizeEnum::cases() as $case) {
            if ($case->value == $value) {
                return $case->value;
            }
        }

        return BaseSizeEnum::ThirtyMM->value;
    }

    private function matchDomainEnum(string $value): string
    {
        foreach (UpgradeDomainTypeEnum::cases() as $case) {
            if ($case->value === $value) {
                return $case->value;
            }
        }

        return UpgradeDomainTypeEnum::Character->value;
    }

    private function matchUpgradeTypeEnum(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        foreach (UpgradeTypeEnum::cases() as $case) {
            if ($case->value === $value) {
                return $case->value;
            }
        }

        return null;
    }

    private function matchLimitationsEnum(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        foreach (UpgradeLimitationEnum::cases() as $case) {
            if ($case->value === $value) {
                return $case->value;
            }
        }

        return null;
    }
}
