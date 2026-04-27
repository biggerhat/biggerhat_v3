<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Models\BlogPost;
use App\Models\Blueprint;
use App\Models\Channel;
use App\Models\Character;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use App\Models\Keyword;
use App\Models\Lore;
use App\Models\Marker;
use App\Models\Package;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Token;
use App\Models\Tournament;
use App\Models\Upgrade;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams sitemap.xml for every public-facing entity. Search engines need this
 * to discover the ~1500+ character/upgrade/keyword/lore pages efficiently —
 * crawl-discovery alone takes weeks, an explicit sitemap takes hours.
 *
 * Updated_at on the underlying record drives <lastmod>; entities without a
 * timestamp use the most-recent-change in that group as a sensible fallback.
 */
class SitemapController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        // Stream the response so the XML body never holds in memory all at
        // once. Collection-of-arrays for $urls is fine (~10k entries, ~few
        // MB); the previous 10k-entry concatenated XML string was the spike.
        return response()->stream(function () {
            $urls = collect();

            // Static pages — high priority root surfaces.
            foreach ([
                ['url' => route('index'), 'priority' => '1.0', 'changefreq' => 'daily'],
                ['url' => route('blog.index'), 'priority' => '0.8', 'changefreq' => 'daily'],
                ['url' => route('keywords.index'), 'priority' => '0.7', 'changefreq' => 'weekly'],
                ['url' => route('markers.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['url' => route('tokens.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['url' => route('actions.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['url' => route('triggers.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['url' => route('abilities.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['url' => route('upgrades.character.index'), 'priority' => '0.7', 'changefreq' => 'weekly'],
                ['url' => route('upgrades.crew.index'), 'priority' => '0.7', 'changefreq' => 'weekly'],
                ['url' => route('packages.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['url' => route('blueprints.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['url' => route('lores.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['url' => route('seasons.index'), 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['url' => route('channels.index'), 'priority' => '0.5', 'changefreq' => 'weekly'],
                ['url' => route('search.view'), 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['url' => route('tools.compare'), 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['url' => route('tools.scenario_generator'), 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['url' => route('tools.crew_builder.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['url' => route('privacy'), 'priority' => '0.2', 'changefreq' => 'yearly'],
            ] as $row) {
                $urls->push($row);
            }

            // Factions (one URL per FactionEnum case).
            foreach (FactionEnum::cases() as $faction) {
                $urls->push([
                    'url' => route('factions.view', $faction->value),
                    'priority' => '0.9',
                    'changefreq' => 'weekly',
                ]);
            }

            // Characters — one URL per (character × first miniature). Skip hidden /
            // mini-less rows since those don't have a renderable view URL.
            Character::query()
                ->where('is_hidden', false)
                ->with(['miniatures' => fn ($q) => $q->orderBy('id')->limit(1)])
                ->whereHas('miniatures')
                ->chunk(500, function ($characters) use ($urls) {
                    foreach ($characters as $character) {
                        /** @var Character $character */
                        $miniature = $character->miniatures->first();
                        if (! $miniature) {
                            continue;
                        }
                        $urls->push([
                            'url' => route('characters.view', [
                                'character' => $character->slug,
                                'miniature' => $miniature->id,
                                'slug' => $miniature->slug,
                            ]),
                            'lastmod' => $this->lastmod($character),
                            'priority' => '0.8',
                            'changefreq' => 'monthly',
                        ]);
                    }
                });

            // Keyword / Marker / Token / Upgrade / Package / Blueprint / Lore /
            // Scheme / Strategy / Channel — generic entity walks.
            $this->collectByModel($urls, Keyword::class, 'keywords.view', 'slug', priority: '0.7');
            $this->collectByModel($urls, Marker::class, 'markers.view', 'slug', priority: '0.5');
            $this->collectByModel($urls, Token::class, 'tokens.view', 'slug', priority: '0.5');
            $this->collectByModel($urls, Upgrade::class, 'upgrades.view', 'slug', priority: '0.7');
            $this->collectByModel($urls, Package::class, 'packages.view', 'slug', priority: '0.6');
            $this->collectByModel($urls, Blueprint::class, 'blueprints.view', 'slug', priority: '0.6');
            $this->collectByModel($urls, Lore::class, 'lores.view', 'slug', priority: '0.5');
            $this->collectByModel($urls, Scheme::class, 'schemes.view', 'slug', priority: '0.5');
            $this->collectByModel($urls, Strategy::class, 'strategies.view', 'slug', priority: '0.5');
            $this->collectByModel($urls, Channel::class, 'channels.view', 'slug', priority: '0.5');

            // Blog posts — only published entries. The `published` scope checks
            // status = Published AND published_at IS NOT NULL, so a draft with a
            // backdated published_at can't slip into the sitemap.
            BlogPost::query()
                ->published()
                ->orderByDesc('published_at')
                ->chunk(500, function ($posts) use ($urls) {
                    foreach ($posts as $post) {
                        /** @var BlogPost $post */
                        $urls->push([
                            'url' => route('blog.view', $post->slug),
                            'lastmod' => $this->lastmod($post),
                            'priority' => '0.7',
                            'changefreq' => 'monthly',
                        ]);
                    }
                });

            // Public custom cards — share URLs are crawlable for is_public entries.
            CustomCharacter::query()
                ->where('is_public', true)
                ->chunk(500, function ($cards) use ($urls) {
                    foreach ($cards as $card) {
                        /** @var CustomCharacter $card */
                        $urls->push([
                            'url' => route('tools.card_creator.share', $card->share_code),
                            'lastmod' => $this->lastmod($card),
                            'priority' => '0.4',
                            'changefreq' => 'monthly',
                        ]);
                    }
                });
            CustomUpgrade::query()
                ->where('is_public', true)
                ->chunk(500, function ($cards) use ($urls) {
                    foreach ($cards as $card) {
                        /** @var CustomUpgrade $card */
                        $urls->push([
                            'url' => route('tools.card_creator.upgrades.share', $card->share_code),
                            'lastmod' => $this->lastmod($card),
                            'priority' => '0.4',
                            'changefreq' => 'monthly',
                        ]);
                    }
                });

            // Public tournaments (anyone can see the standings page once the TO
            // marks the tournament public). Skip drafts since they have no value
            // to a search engine.
            Tournament::query()
                ->where('is_public', true)
                ->chunk(200, function ($tournaments) use ($urls) {
                    foreach ($tournaments as $tournament) {
                        /** @var Tournament $tournament */
                        $urls->push([
                            'url' => route('tournaments.view', $tournament->uuid),
                            'lastmod' => $this->lastmod($tournament),
                            'priority' => '0.5',
                            'changefreq' => 'weekly',
                        ]);
                    }
                });

            echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;

            foreach ($urls as $u) {
                echo '  <url>'.PHP_EOL;
                echo '    <loc>'.htmlspecialchars($u['url']).'</loc>'.PHP_EOL;
                if (! empty($u['lastmod'])) {
                    echo '    <lastmod>'.$u['lastmod'].'</lastmod>'.PHP_EOL;
                }
                if (! empty($u['changefreq'])) {
                    echo '    <changefreq>'.$u['changefreq'].'</changefreq>'.PHP_EOL;
                }
                if (! empty($u['priority'])) {
                    echo '    <priority>'.$u['priority'].'</priority>'.PHP_EOL;
                }
                echo '  </url>'.PHP_EOL;
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();
            }

            echo '</urlset>'.PHP_EOL;
        }, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * Walk a model class, push one sitemap entry per record.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function collectByModel(
        \Illuminate\Support\Collection $urls,
        string $modelClass,
        string $routeName,
        string $key,
        string $priority,
    ): void {
        $modelClass::query()->orderBy('id')->chunk(500, function ($rows) use ($urls, $routeName, $key, $priority) {
            foreach ($rows as $row) {
                $value = $row->{$key};
                if (! $value) {
                    continue;
                }
                $urls->push([
                    'url' => route($routeName, $value),
                    'lastmod' => $this->lastmod($row),
                    'priority' => $priority,
                    'changefreq' => 'monthly',
                ]);
            }
        });
    }

    private function lastmod(Model $model): ?string
    {
        $ts = $model->getAttribute('updated_at');

        return $ts instanceof \DateTimeInterface ? $ts->format('Y-m-d') : null;
    }
}
