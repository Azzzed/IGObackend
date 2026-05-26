<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->json('contenido_json');
            $table->json('asintotas_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informes');
    }
};
