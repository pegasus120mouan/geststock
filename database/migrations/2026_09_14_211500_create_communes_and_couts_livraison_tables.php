<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('statut', 20)->default('actif');
            $table->timestamps();

            $table->index('statut');
        });

        Schema::create('couts_livraison', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained('communes')->cascadeOnDelete();
            $table->decimal('montant', 14, 2)->default(0);
            $table->string('statut', 20)->default('actif');
            $table->timestamps();

            $table->unique('commune_id');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couts_livraison');
        Schema::dropIfExists('communes');
    }
};
