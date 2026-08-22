<?php

namespace App\Http\Requests\Catalog;

class ListEpisodesRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'regex:/^S\d{2}E\d{2}$/i'],
            'season' => ['sometimes', 'integer', 'min:1', 'max:99'],
        ];
    }
}
