<?php

namespace App\Services\Sync;

/**
 * Resumen de una ejecución de la sincronización: qué se procesó, qué falló
 * y por qué. El comando de consola lo usa para pintar la tabla final y decidir
 * el código de salida.
 */
final class SyncReport
{
    /**
     * @var array<string, array{pages_ok: int, pages_failed: int, records: int, rejected: int}>
     */
    private array $entities = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param  list<string>  $rejectedReasons  Motivos de los registros descartados por formato.
     */
    public function pageSucceeded(string $entity, int $page, int $records, array $rejectedReasons): void
    {
        $this->counters($entity)['pages_ok']++;
        $this->counters($entity)['records'] += $records;
        $this->counters($entity)['rejected'] += count($rejectedReasons);

        foreach ($rejectedReasons as $reason) {
            $this->warnings[] = "{$entity}, página {$page}: {$reason}";
        }
    }

    public function pageFailed(string $entity, int $page, string $reason): void
    {
        $this->counters($entity)['pages_failed']++;
        $this->errors[] = "{$entity}, página {$page}: {$reason}";
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function hasFailures(): bool
    {
        return $this->errors !== [];
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Filas para la tabla resumen del comando: una por entidad.
     *
     * @return list<array{string, int, int, int, int}>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->entities as $entity => $counters) {
            $rows[] = [
                $entity,
                $counters['pages_ok'],
                $counters['pages_failed'],
                $counters['records'],
                $counters['rejected'],
            ];
        }

        return $rows;
    }

    /**
     * Devuelve los contadores de una entidad por referencia, creándolos a cero
     * la primera vez que se usan.
     *
     * @return array{pages_ok: int, pages_failed: int, records: int, rejected: int}
     */
    private function &counters(string $entity): array
    {
        if (! isset($this->entities[$entity])) {
            $this->entities[$entity] = [
                'pages_ok' => 0,
                'pages_failed' => 0,
                'records' => 0,
                'rejected' => 0,
            ];
        }

        return $this->entities[$entity];
    }
}
