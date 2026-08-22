<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();

            // Identificador que usa la API de Rick and Morty. Es la clave de
            // idempotencia de la sincronización: si ya existe se actualiza, si no se crea.
            // Se guarda aparte del id propio para no acoplar nuestras claves al proveedor.
            $table->unsignedInteger('external_id')->unique();

            $table->string('name');

            // En la API estos campos a veces vienen como cadena vacía ("").
            // Se normalizan a NULL, que es la forma correcta de decir "no hay dato".
            $table->string('type')->nullable();
            $table->string('dimension')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
