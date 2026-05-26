<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // empresas.user_id — acelera "dame las empresas de este usuario"
        Schema::table('empresas', function (Blueprint $table) {
            $table->index('user_id', 'idx_empresas_user_id');
        });

        // iniciativas.empresa_id — acelera todas las queries de iniciativas por empresa
        // iniciativas.(empresa_id, cuadrante) — acelera filtros y orden de matriz IGO
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->index('empresa_id', 'idx_iniciativas_empresa_id');
            $table->index(['empresa_id', 'cuadrante'], 'idx_iniciativas_empresa_cuadrante');
        });

        // planes_accion.iniciativa_id — acelera eager load planAccion en listados
        Schema::table('planes_accion', function (Blueprint $table) {
            $table->index('iniciativa_id', 'idx_planes_iniciativa_id');
        });

        // personal_access_tokens.tokenable_id — acelera el token lookup de Sanctum en CADA request autenticado
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->index('tokenable_id', 'idx_pat_tokenable_id');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropIndex('idx_empresas_user_id');
        });

        Schema::table('iniciativas', function (Blueprint $table) {
            $table->dropIndex('idx_iniciativas_empresa_id');
            $table->dropIndex('idx_iniciativas_empresa_cuadrante');
        });

        Schema::table('planes_accion', function (Blueprint $table) {
            $table->dropIndex('idx_planes_iniciativa_id');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_pat_tokenable_id');
        });
    }
};
