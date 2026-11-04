<?php

namespace App\Http\Requests;

use App\Models\CardArt;
use App\Models\CustomCharacter;
use Illuminate\Foundation\Http\FormRequest;

/** Art + framing only — lets a Campaign Leader/Totem's art change without going through the full card editor. */
class CustomCharacterArtRequest extends FormRequest
{
    public function authorize(): bool
    {
        $character = $this->route('customCharacter');

        return $character instanceof CustomCharacter && $this->user()?->can('update', $character) === true;
    }

    public function rules(): array
    {
        /** @var CustomCharacter $character */
        $character = $this->route('customCharacter');

        return CardArt::validationRules($character->card_art_id);
    }
}
