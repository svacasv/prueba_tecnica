<?php

namespace App\Services\RickAndMorty\DTO;

use App\Services\RickAndMorty\Exceptions\InvalidExternalDataException;
use Illuminate\Support\Facades\Validator;

/**
 * Comprueba que un registro de la API tiene los campos y tipos esperados antes
 * de construir el DTO. Se apoya en el validador de Laravel para que las reglas
 * se lean como una lista y no como un montón de ifs.
 */
trait ValidatesExternalData
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<int, string>>  $rules
     *
     * @throws InvalidExternalDataException
     */
    protected static function assertValid(string $entity, array $data, array $rules): void
    {
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw InvalidExternalDataException::forRecord(
                $entity,
                $data['id'] ?? null,
                $validator->errors()->first(),
            );
        }
    }
}
