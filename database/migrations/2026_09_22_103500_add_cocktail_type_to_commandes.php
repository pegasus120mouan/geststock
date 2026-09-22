<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            if (! Schema::hasColumn('commandes', 'type')) {
                $table->string('type', 20)->default('normale')->after('reference');
                $table->index('type');
            }
            if (! Schema::hasColumn('commandes', 'cocktail_id')) {
                $table->foreignId('cocktail_id')->nullable()->after('type')->constrained('cocktails')->nullOnDelete();
            }
        });

        Schema::table('commande_lignes', function (Blueprint $table) {
            if (! Schema::hasColumn('commande_lignes', 'quantite_ml')) {
                $table->decimal('quantite_ml', 14, 2)->nullable()->after('quantite');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commande_lignes', function (Blueprint $table) {
            if (Schema::hasColumn('commande_lignes', 'quantite_ml')) {
                $table->dropColumn('quantite_ml');
            }
        });

        Schema::table('commandes', function (Blueprint $table) {
            if (Schema::hasColumn('commandes', 'cocktail_id')) {
                $table->dropConstrainedForeignId('cocktail_id');
            }
            if (Schema::hasColumn('commandes', 'type')) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            }
        });
    }
};
