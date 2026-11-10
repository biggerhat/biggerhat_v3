<?php

namespace App\Http\Requests\TOS\Admin;

use App\Models\TOS\Deployment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_deployment') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('tos_deployments', 'name')],
            // One Deployment per Fate suit: the Defender's flip picks it (a Joker counts as R).
            'suit' => ['nullable', 'string', Rule::in(Deployment::SUITS), Rule::unique('tos_deployments', 'suit')],
            'description' => ['nullable', 'string'],
            'image_path' => ['nullable', 'file', 'image', 'max:30000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
