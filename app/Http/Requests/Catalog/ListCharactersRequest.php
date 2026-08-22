<?php

namespace App\Http\Requests\Catalog;

class ListCharactersRequest extends ListRequest
{
    protected function filterRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:Alive,Dead,unknown'],
            'species' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', 'string', 'in:Female,Male,Genderless,unknown'],
        ];
    }
}
