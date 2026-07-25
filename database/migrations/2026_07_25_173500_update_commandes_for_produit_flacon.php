<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->string('client_nom')->nullable()->change();
            $table->string('client_telephone')->nullable(false)->change();

            $table->foreignId('produit_id')->nullable()->after('reference')->constrained('produits')->nullOnDelete();
            $table->foreignId('flacon_id')->nullable()->after('produit_id')->constrained('flacons')->nullOnDelete();
            $table->unsignedInteger('quantite')->default(1)->after('flacon_id');
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('produit_id');
            $table->dropConstrainedForeignId('flacon_id');
            $table->dropColumn('quantite');

            $table->string('client_nom')->nullable(false)->change();
            $table->string('client_telephone')->nullable()->change();
        });
    }
};
