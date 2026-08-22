<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens de acceso a la API. Nunca se guarda el token en claro: solo su
     * hash SHA-256, igual que se hace con las contraseñas. Si alguien leyera
     * la tabla no podría usar los tokens.
     */
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique(); // sha256 en hexadecimal = 64 caracteres
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // null = no caduca
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
