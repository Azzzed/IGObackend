<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_accion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iniciativa_id')->constrained('iniciativas')->onDelete('cascade');
            $table->date('deadline')->nullable();
            $table->decimal('presupuesto', 12, 2)->nullable();
            $table->text('aliados')->nullable();
            $table->enum('estado', ['pendiente', 'en_proceso', 'terminado', 'abortado'])
                  ->default('pendiente');
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_accion');
    }
};
