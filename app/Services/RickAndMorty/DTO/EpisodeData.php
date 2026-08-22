<?php

namespace App\Services\RickAndMorty\DTO;

use App\Services\RickAndMorty\ExternalValueParser;
use Carbon\CarbonImmutable;

/**
 * Episodio tal y como lo entiende nuestro dominio, ya validado y normalizado.
 */
final readonly class EpisodeData
{
    use ValidatesExternalData;

    /**
     * @param  list<int>  $characterExternalIds
     */
    public function __construct(
        public int $externalId,
        public string $name,
        public ?CarbonImmutable $airDate,
        public string $code,
        public array $characterExternalIds,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Un elemento de "results" de la API.
     */
    public static function fromArray(array $data): self
    {
        self::assertValid('episodio', $data, [
            'id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string'],
            'air_date' => ['present', 'nullable', 'string'],
            // La API llama "episode" al código S01E01. Es clave única en nuestra
            // tabla, así que si no tiene ese formato el registro se descarta.
            'episode' => ['required', 'string', 'regex:/^S\d{2}E\d{2}$/'],
            'characters' => ['present', 'array'],
        ]);

        return new self(
            externalId: $data['id'],
            name: $data['name'],
            airDate: ExternalValueParser::dateOrNull($data['air_date']),
            code: $data['episode'],
            characterExternalIds: ExternalValueParser::idsFromUrls($data['characters']),
        );
    }
}
