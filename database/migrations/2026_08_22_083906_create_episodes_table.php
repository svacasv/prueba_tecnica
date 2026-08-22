<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();

            // Identificador de la API externa (ver comentario en locations).
            $table->unsignedInteger('external_id')->unique();

            $table->string('name');

            // La API lo envía como texto en inglés ("December 2, 2013").
            // Se convierte a fecha al sincronizar; si no se puede interpretar queda NULL.
            $table->date('air_date')->nullable();

            // Código del episodio con formato S01E01. La API lo llama "episode",
            // aquí se renombra a "code" para que no se confunda con la entidad.
            $table->string('code', 10)->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
