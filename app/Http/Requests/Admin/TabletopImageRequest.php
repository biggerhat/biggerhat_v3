<?php

namespace App\Http\Requests\Admin;

use App\Models\TabletopImage;
use App\Support\Tabletop\TabletopImages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TabletopImageRequest extends FormRequest
{
    /**
     * Route middleware (role:super_admin) gates access; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $existing = $this->route('tabletopImage');

        return [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'string', Rule::in([TabletopImage::KIND_TABLE, TabletopImage::KIND_TERRAIN])],
            // A terrain texture says which kind of terrain it is for; a table surface has none.
            'terrain_key' => ['nullable', 'required_if:kind,'.TabletopImage::KIND_TERRAIN, 'string', Rule::in(array_keys(TabletopImages::TERRAIN_KINDS))],
            'credit' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            // Required when creating; on edit it is only replaced if a new file is sent.
            'image' => [$existing instanceof TabletopImage ? 'nullable' : 'required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ];
    }
}
