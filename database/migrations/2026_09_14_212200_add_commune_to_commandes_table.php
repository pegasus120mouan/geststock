<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            if (! Schema::hasColumn('commandes', 'commune_id')) {
                $table->foreignId('commune_id')->nullable()->after('client_telephone')->constrained('communes')->nullOnDelete();
            }
            if (! Schema::hasColumn('commandes', 'frais_livraison')) {
                $table->decimal('frais_livraison', 14, 2)->default(0)->after('commune_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            if (Schema::hasColumn('commandes', 'commune_id')) {
                $table->dropConstrainedForeignId('commune_id');
            }
            if (Schema::hasColumn('commandes', 'frais_livraison')) {
                $table->dropColumn('frais_livraison');
            }
        });
    }
};
