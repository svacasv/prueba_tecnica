<?php

namespace App\Services\RickAndMorty\DTO;

use App\Services\RickAndMorty\ExternalValueParser;

/**
 * Personaje tal y como lo entiende nuestro dominio, ya validado y normalizado.
 *
 * Las relaciones se guardan como identificadores externos (los de la API); la
 * sincronización los traduce a claves propias al persistir.
 */
final readonly class CharacterData
{
    use ValidatesExternalData;

    /**
     * @param  list<int>  $episodeExternalIds
     */
    public function __construct(
        public int $externalId,
        public string $name,
        public string $status,
        public string $species,
        public ?string $type,
        public string $gender,
        public string $image,
        public ?int $originExternalId,
        public ?int $currentLocationExternalId,
        public array $episodeExternalIds,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Un elemento de "results" de la API.
     */
    public static function fromArray(array $data): self
    {
        self::assertValid('personaje', $data, [
            'id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string'],
            'status' => ['required', 'string'],
            'species' => ['required', 'string'],
            'type' => ['present', 'nullable', 'string'],
            'gender' => ['required', 'string'],
            'image' => ['required', 'string'],
            // origin y location son objetos {name, url}. Cuando la localización es
            // desconocida la API manda url = "", por eso se admite vacía.
            'origin.url' => ['present', 'nullable', 'string'],
            'location.url' => ['present', 'nullable', 'string'],
            'episode' => ['present', 'array'],
        ]);

        return new self(
            externalId: $data['id'],
            name: $data['name'],
            status: $data['status'],
            species: $data['species'],
            type: ExternalValueParser::nullIfEmpty($data['type']),
            gender: $data['gender'],
            image: $data['image'],
            originExternalId: ExternalValueParser::idFromUrl($data['origin']['url']),
            currentLocationExternalId: ExternalValueParser::idFromUrl($data['location']['url']),
            episodeExternalIds: ExternalValueParser::idsFromUrls($data['episode']),
        );
    }
}
