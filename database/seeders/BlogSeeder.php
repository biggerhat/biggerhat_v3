<?php

namespace Database\Seeders;

use App\Enums\BlogPostStatusEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Upgrade;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::first() ?? User::factory()->create(['name' => 'Blog Author']);

        $categories = BlogCategory::factory()->count(3)->sequence(
            ['name' => 'Strategy Guides'],
            ['name' => 'Meta Analysis'],
            ['name' => 'Patch Notes'],
        )->create();

        $character = Character::first();
        $keyword = Keyword::first();
        $upgrade = Upgrade::first();
        $action = Action::first();
        $ability = Ability::first();

        // Post 1: Strategy guide with rich content
        $post1 = BlogPost::create([
            'title' => 'Getting Started with Crew Building',
            'excerpt' => 'A comprehensive guide to building your first competitive crew, including upgrade selection and keyword synergies.',
            'status' => BlogPostStatusEnum::Published,
            'published_at' => now()->subDays(3),
            'user_id' => $author->id,
            'blog_category_id' => $categories[0]->id,
            'content' => $this->buildPost1Content($character, $keyword, $upgrade, $action, $ability),
        ]);

        $this->attachTags($post1, $character, $keyword, $upgrade, $action, $ability);

        // Post 2: Meta analysis with embeds
        $post2 = BlogPost::create([
            'title' => 'Top Actions in the Current Meta',
            'excerpt' => 'Breaking down the most impactful actions and how they shape the current competitive landscape.',
            'status' => BlogPostStatusEnum::Published,
            'published_at' => now()->subDay(),
            'user_id' => $author->id,
            'blog_category_id' => $categories[1]->id,
            'content' => $this->buildPost2Content($character, $action, $ability, $upgrade),
        ]);

        if ($character) {
            $post2->characters()->attach($character->id);
        }
        if ($action) {
            $post2->actions()->attach($action->id);
        }

        // Post 3: Draft post
        BlogPost::create([
            'title' => 'Upcoming Balance Changes — Draft',
            'excerpt' => 'Preview of expected changes in the next errata cycle.',
            'status' => BlogPostStatusEnum::Draft,
            'user_id' => $author->id,
            'blog_category_id' => $categories[2]->id,
            'content' => [
                'type' => 'doc',
                'content' => [
                    $this->paragraph('This post is still being drafted. More details coming soon.'),
                    $this->paragraph('Stay tuned for {{crow}} {{ram}} {{tome}} {{mask}} suit breakdowns and {{soulstone}} cost analysis.'),
                ],
            ],
        ]);
    }

    private function attachTags(BlogPost $post, ?Character $character, ?Keyword $keyword, ?Upgrade $upgrade, ?Action $action, ?Ability $ability): void
    {
        if ($character) {
            $post->characters()->attach($character->id);
        }
        if ($keyword) {
            $post->keywords()->attach($keyword->id);
        }
        if ($upgrade) {
            $post->upgrades()->attach($upgrade->id);
        }
        if ($action) {
            $post->actions()->attach($action->id);
        }
        if ($ability) {
            $post->abilities()->attach($ability->id);
        }
    }

    private function buildPost1Content(?Character $character, ?Keyword $keyword, ?Upgrade $upgrade, ?Action $action, ?Ability $ability): array
    {
        $content = [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Introduction']]],
            $this->paragraph('Building a competitive crew is one of the most rewarding parts of Malifaux. This guide covers the fundamentals of crew construction, from choosing your master to selecting the right upgrades and understanding keyword synergies.'),
        ];

        if ($character) {
            $content[] = $this->paragraphWithInlineRef(
                'Let\'s start by looking at ',
                $character,
                'character',
                ' as an example of how keyword synergies shape crew building decisions.'
            );
        }

        $content[] = ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Understanding Keywords']]];

        if ($keyword) {
            $content[] = $this->paragraphWithInlineRef(
                'Keywords like ',
                $keyword,
                'keyword',
                ' define which models work well together. Always check keyword overlap when adding models to your crew.'
            );
        }

        $content[] = [
            'type' => 'blockquote',
            'content' => [
                $this->paragraph('Pro tip: Focus on one or two keywords in your crew. Spreading across too many keywords dilutes your synergy and makes your crew less effective overall.'),
            ],
        ];

        $content[] = ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Selecting Upgrades']]];
        $content[] = $this->paragraph('Upgrades can dramatically change how a model performs. Consider both the {{soulstone}} cost and the actions they grant when evaluating upgrades.');

        if ($upgrade) {
            $content[] = [
                'type' => 'entityEmbed',
                'attrs' => [
                    'entityType' => 'upgrade',
                    'entityId' => $upgrade->id,
                    'entitySlug' => $upgrade->slug,
                    'displayName' => $upgrade->name,
                ],
            ];
        }

        $content[] = ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Key Actions to Watch For']]];
        $content[] = $this->paragraph('When evaluating a model, pay close attention to its action spread. A good mix of {{melee}} and {{missile}} options with {{positive}} flips gives you flexibility.');

        if ($action) {
            $content[] = [
                'type' => 'entityEmbed',
                'attrs' => [
                    'entityType' => 'action',
                    'entityId' => $action->id,
                    'entitySlug' => $action->slug,
                    'displayName' => $action->name,
                ],
            ];
        }

        $content[] = ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Abilities Matter']]];
        $content[] = $this->paragraph('Don\'t overlook passive abilities — they can define a model\'s role in the crew.');

        if ($ability) {
            $content[] = [
                'type' => 'entityEmbed',
                'attrs' => [
                    'entityType' => 'ability',
                    'entityId' => $ability->id,
                    'entitySlug' => $ability->slug,
                    'displayName' => $ability->name,
                ],
            ];
        }

        $content[] = ['type' => 'horizontalRule'];
        $content[] = [
            'type' => 'blockquote',
            'content' => [
                $this->paragraph('Remember: the best crew isn\'t always the one with the most powerful individual models. It\'s the one where every piece works together toward your strategy.'),
            ],
        ];

        $content[] = ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Further Reading']]];
        $content[] = [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'For more info on the game, check out the '],
                [
                    'type' => 'text',
                    'text' => 'official Malifaux site',
                    'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://www.wyrd-games.net']]],
                ],
                ['type' => 'text', 'text' => ' for the latest news and rules updates.'],
            ],
        ];

        return ['type' => 'doc', 'content' => $content];
    }

    private function buildPost2Content(?Character $character, ?Action $action, ?Ability $ability, ?Upgrade $upgrade): array
    {
        $content = [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Meta Overview']]],
            $this->paragraph('The current meta favors crews with strong {{melee}} threat projection and reliable {{positive}} flips. Here are the standout actions and abilities shaping competitive play.'),
        ];

        if ($character) {
            $content[] = ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Spotlight: '.$character->display_name]]];
            $content[] = [
                'type' => 'entityEmbed',
                'attrs' => [
                    'entityType' => 'character',
                    'entityId' => $character->id,
                    'entitySlug' => $character->slug,
                    'displayName' => $character->display_name,
                ],
            ];
        }

        if ($action) {
            $content[] = ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Top Action: '.$action->name]]];
            $content[] = $this->paragraphWithInlineRef(
                'The action ',
                $action,
                'action',
                ' continues to be a staple in competitive lists.'
            );
            $content[] = [
                'type' => 'entityEmbed',
                'attrs' => [
                    'entityType' => 'action',
                    'entityId' => $action->id,
                    'entitySlug' => $action->slug,
                    'displayName' => $action->name,
                ],
            ];
        }

        $content[] = [
            'type' => 'blockquote',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Editor\'s note:'],
                        ['type' => 'text', 'text' => ' These rankings are based on tournament results from the past three months. Your local meta may differ.'],
                    ],
                ],
            ],
        ];

        $content[] = ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Key Takeaways']]];
        $content[] = [
            'type' => 'bulletList',
            'content' => [
                ['type' => 'listItem', 'content' => [$this->paragraph('{{Melee}} threat ranges are more important than raw damage')]],
                ['type' => 'listItem', 'content' => [$this->paragraph('Models with built-in {{positive}} flips have consistently overperformed')]],
                ['type' => 'listItem', 'content' => [$this->paragraph('{{Soulstone}} efficiency matters — don\'t overspend on upgrades')]],
                ['type' => 'listItem', 'content' => [$this->paragraph('Defensive abilities with {{fortitude}} or {{warding}} are undervalued')]],
            ],
        ];

        if ($upgrade) {
            $content[] = ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Upgrade Spotlight']]];
            $content[] = $this->paragraphWithInlineRef(
                'The upgrade ',
                $upgrade,
                'upgrade',
                ' has seen increased play thanks to its flexible application.'
            );
            $content[] = [
                'type' => 'entityEmbed',
                'attrs' => [
                    'entityType' => 'upgrade',
                    'entityId' => $upgrade->id,
                    'entitySlug' => $upgrade->slug,
                    'displayName' => $upgrade->name,
                ],
            ];
        }

        return ['type' => 'doc', 'content' => $content];
    }

    private function paragraph(string $text): array
    {
        return [
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ];
    }

    /**
     * @param  Character|Keyword|Upgrade|Action|Ability  $entity
     */
    private function paragraphWithInlineRef(string $before, mixed $entity, string $entityType, string $after): array
    {
        $slug = $entity->slug;
        $name = $entity->display_name ?? $entity->name;

        return [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => $before],
                [
                    'type' => 'entityReference',
                    'attrs' => [
                        'entityType' => $entityType,
                        'entityId' => $entity->id,
                        'entitySlug' => $slug,
                        'displayName' => $name,
                    ],
                ],
                ['type' => 'text', 'text' => $after],
            ],
        ];
    }
}
