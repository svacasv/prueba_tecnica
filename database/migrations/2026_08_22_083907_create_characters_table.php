<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();

            // Identificador de la API externa (ver comentario en locations).
            $table->unsignedInteger('external_id')->unique();

            // Los campos por los que se filtra en la API propia llevan índice.
            $table->string('name')->index();
            $table->string('status')->index();   // Alive, Dead o unknown
            $table->string('species')->index();
            $table->string('type')->nullable();  // en la API casi siempre viene vacío
            $table->string('gender')->index();   // Female, Male, Genderless o unknown
            $table->string('image');

            // Cada personaje referencia dos localizaciones: de dónde viene y dónde está.
            // Son nullable porque en la API muchas veces vienen como "unknown" sin id.
            // Si se borrara una localización, el personaje no se borra: se queda sin ella.
            $table->foreignId('origin_location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();

            $table->foreignId('current_location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
