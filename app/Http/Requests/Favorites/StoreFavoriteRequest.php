<?php

namespace App\Http\Requests\Favorites;

use Illuminate\Foundation\Http\FormRequest;

class StoreFavoriteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'character_id' => ['required', 'integer', 'exists:characters,id'],
        ];
    }
}
