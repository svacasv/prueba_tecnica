<?php

namespace App\Services\RickAndMorty\DTO;

use App\Services\RickAndMorty\ExternalValueParser;

/**
 * Localización tal y como la entiende nuestro dominio, ya validada y normalizada.
 */
final readonly class LocationData
{
    use ValidatesExternalData;

    public function __construct(
        public int $externalId,
        public string $name,
        public ?string $type,
        public ?string $dimension,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Un elemento de "results" de la API.
     */
    public static function fromArray(array $data): self
    {
        self::assertValid('localización', $data, [
            'id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string'],
            'type' => ['present', 'nullable', 'string'],
            'dimension' => ['present', 'nullable', 'string'],
        ]);

        return new self(
            externalId: $data['id'],
            name: $data['name'],
            type: ExternalValueParser::nullIfEmpty($data['type']),
            dimension: ExternalValueParser::nullIfEmpty($data['dimension']),
        );
    }
}
