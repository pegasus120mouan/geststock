<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->date('date_commande')->nullable()->after('quantite');
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('date_commande');
        });
    }
};
