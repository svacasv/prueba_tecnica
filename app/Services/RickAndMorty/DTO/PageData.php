<?php

namespace App\Services\RickAndMorty\DTO;

/**
 * Una página de resultados de la API ya convertida a DTOs.
 */
final readonly class PageData
{
    /**
     * @param  int  $totalPages  Número total de páginas que tiene el recurso.
     * @param  list<CharacterData|EpisodeData|LocationData>  $items  Registros válidos.
     * @param  list<string>  $rejected  Motivo de cada registro descartado por formato incorrecto.
     * @param  array<string, mixed>  $raw  JSON original decodificado, para poder guardarlo sin procesar.
     */
    public function __construct(
        public int $totalPages,
        public array $items,
        public array $rejected,
        public array $raw,
    ) {}
}
