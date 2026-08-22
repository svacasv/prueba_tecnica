<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla pivote de la relación muchos a muchos entre personajes y episodios.
     * El nombre sigue la convención de Laravel (modelos en singular y orden alfabético),
     * así los modelos no necesitan indicar el nombre de la tabla.
     */
    public function up(): void
    {
        Schema::create('character_episode', function (Blueprint $table) {
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('episode_id')->constrained()->cascadeOnDelete();

            // Clave primaria compuesta: un personaje no puede estar dos veces en el
            // mismo episodio. Es lo que garantiza que la sincronización no duplique filas.
            $table->primary(['character_id', 'episode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_episode');
    }
};
