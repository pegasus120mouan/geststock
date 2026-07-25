<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_mouvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // entree | sortie
            $table->decimal('quantite', 14, 4);
            $table->string('unite'); // ml | cl | litre
            $table->decimal('quantite_ml', 14, 2);
            $table->decimal('stock_avant', 14, 2);
            $table->decimal('stock_apres', 14, 2);
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['produit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mouvements');
    }
};
