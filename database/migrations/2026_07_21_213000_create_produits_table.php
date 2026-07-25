<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('marque')->nullable();
            $table->string('famille')->nullable();
            $table->text('description')->nullable();
            $table->decimal('prix_achat_ml', 12, 4)->default(0);
            $table->string('image')->nullable();
            $table->decimal('stock_ml', 14, 2)->default(0);
            $table->string('statut')->default('actif');
            $table->timestamps();

            $table->index(['nom', 'statut']);
            $table->index('marque');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
