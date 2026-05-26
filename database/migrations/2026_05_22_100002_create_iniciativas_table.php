<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iniciativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->string('titulo');
            $table->enum('categoria', [
                'gestion_operativa', 'tecnologia_informacion',
                'gestion_financiera', 'cadena_suministro',
                'talento_humano', 'comercial_ventas',
                'legal_cumplimiento', 'otro',
            ]);
            $table->tinyInteger('importancia');
            $table->tinyInteger('gobernabilidad');
            $table->tinyInteger('cuadrante')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iniciativas');
    }
};
