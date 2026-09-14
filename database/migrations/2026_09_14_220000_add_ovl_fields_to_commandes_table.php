<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            if (!Schema::hasColumn('commandes', 'ovl_commande_id')) {
                $table->unsignedBigInteger('ovl_commande_id')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('commandes', 'ovl_sent_at')) {
                $table->timestamp('ovl_sent_at')->nullable()->after('ovl_commande_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            if (Schema::hasColumn('commandes', 'ovl_sent_at')) {
                $table->dropColumn('ovl_sent_at');
            }
            if (Schema::hasColumn('commandes', 'ovl_commande_id')) {
                $table->dropColumn('ovl_commande_id');
            }
        });
    }
};
