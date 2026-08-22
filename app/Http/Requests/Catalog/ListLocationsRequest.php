<?php

namespace App\Http\Requests\Catalog;

class ListLocationsRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:255'],
            'dimension' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
