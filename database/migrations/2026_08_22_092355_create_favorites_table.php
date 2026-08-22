<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personajes favoritos de cada usuario (relación muchos a muchos).
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Un personaje solo puede ser favorito de un usuario una vez. La base
            // de datos es quien lo garantiza; el controlador solo traduce el error.
            $table->unique(['user_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
