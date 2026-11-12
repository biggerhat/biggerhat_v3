<?php

namespace App\Http\Requests\Tournaments;

use App\Services\Discord\TournamentDiscord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TournamentDiscordRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage', $this->route('tournament'));

        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        // Empty clears the connection.
        return ['webhook_url' => ['nullable', 'string', 'max:300', 'regex:'.TournamentDiscord::URL_PATTERN]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['webhook_url.regex' => 'That does not look like a Discord webhook URL (https://discord.com/api/webhooks/…).'];
    }
}
