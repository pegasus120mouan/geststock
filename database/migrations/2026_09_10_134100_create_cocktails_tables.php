<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cocktails', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('statut')->default('actif');
            $table->timestamps();

            $table->index('statut');
            $table->index('nom');
        });

        Schema::create('cocktail_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cocktail_id')->constrained('cocktails')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->decimal('quantite_ml', 14, 2);
            $table->timestamps();

            $table->unique(['cocktail_id', 'produit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cocktail_lignes');
        Schema::dropIfExists('cocktails');
    }
};
