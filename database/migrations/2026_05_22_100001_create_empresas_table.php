<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('nombre');
            $table->enum('sector', [
                'agro', 'calzado_moda', 'tecnologia', 'servicios',
                'comercio', 'salud', 'turismo', 'educacion',
                'manufactura', 'otro',
            ]);
            $table->enum('tamano', ['idea', 'micro', 'pequena', 'mediana', 'grande']);
            $table->enum('genero_empresario', ['hombre', 'mujer', 'otro']);
            $table->enum('rango_edad', ['18-25', '26-35', '36-45', '46-55', '56+']);
            $table->string('pais');
            $table->string('ciudad');
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
