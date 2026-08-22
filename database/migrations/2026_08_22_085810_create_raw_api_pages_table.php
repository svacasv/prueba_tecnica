<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copia tal cual de cada página descargada de la API, antes de procesarla.
     * Permite volver a procesar los datos sin descargarlos otra vez y ver qué
     * envió exactamente la fuente si algo sale mal.
     */
    public function up(): void
    {
        Schema::create('raw_api_pages', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 20);          // locations, episodes o characters
            $table->unsignedSmallInteger('page');
            $table->json('payload');
            $table->timestamp('fetched_at');

            // Una fila por página: si se vuelve a descargar, se sobrescribe.
            $table->unique(['entity', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_api_pages');
    }
};
