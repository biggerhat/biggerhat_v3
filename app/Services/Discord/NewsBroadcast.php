<?php

namespace App\Services\Discord;

use App\Models\BlogPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends a published site news post to the site's Discord channel through a channel webhook (`services.discord_news.webhook`). It is a
 * manual action: an admin presses "Post to Discord" on a news post, optionally with a line of their own on top. Only discord.com webhook
 * URLs are accepted, redirects are not followed, mentions are never parsed, and a failing Discord is reported back rather than thrown.
 */
class NewsBroadcast
{
    private const COLOR = 0xC9A227;

    /** Whether the site has a channel set up to post to. */
    public function configured(): bool
    {
        return $this->url() !== null;
    }

    /** True when Discord took the post. */
    public function send(BlogPost $post, ?string $message = null): bool
    {
        $url = $this->url();
        if ($url === null) {
            return false;
        }

        try {
            $response = Http::timeout(5)->withoutRedirecting()->post($url, $this->payload($post, $message));

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('Discord news post failed for blog post '.$post->id.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(BlogPost $post, ?string $message = null): array
    {
        $summary = trim((string) $post->excerpt) !== '' ? trim((string) $post->excerpt) : $post->plainTextContent();

        $embed = [
            'title' => Str::limit($post->title, 250, '…'),
            'url' => route('news.view', $post),
            'description' => Str::limit($summary, 350, '…'),
            'color' => self::COLOR,
            'timestamp' => ($post->published_at ?? now())->toIso8601String(),
        ];

        return [
            'username' => 'BiggerHat News',
            'allowed_mentions' => ['parse' => []], // text from a post or an admin never pings @everyone or roles
            'content' => $message !== null && trim($message) !== '' ? Str::limit(trim($message), 300, '…') : null,
            'embeds' => [$embed],
        ];
    }

    private function url(): ?string
    {
        $url = config('services.discord_news.webhook');

        return is_string($url) && preg_match(TournamentDiscord::URL_PATTERN, $url) === 1 ? $url : null;
    }
}
