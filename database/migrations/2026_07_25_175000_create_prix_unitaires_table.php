<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prix_unitaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->foreignId('flacon_id')->constrained('flacons')->cascadeOnDelete();
            $table->decimal('prix', 14, 2);
            $table->timestamps();

            $table->unique(['produit_id', 'flacon_id']);
            $table->index('prix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prix_unitaires');
    }
};
