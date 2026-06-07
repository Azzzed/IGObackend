<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debug_auth_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('ruta', 30);
            $table->boolean('bearer')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('tipo_antes', 20)->nullable();
            $table->string('tipo_despues', 20)->nullable();
            $table->integer('filas')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debug_auth_eventos');
    }
};
