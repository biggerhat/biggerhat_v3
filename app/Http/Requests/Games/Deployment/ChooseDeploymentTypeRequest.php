<?php

namespace App\Http\Requests\Games\Deployment;

use App\Enums\DeploymentEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Picking the deployment type on the table, before anyone has placed a model. Either player may (the zones are shown to both). */
class ChooseDeploymentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('game')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['deployment' => ['required', 'string', Rule::enum(DeploymentEnum::class)]];
    }
}
