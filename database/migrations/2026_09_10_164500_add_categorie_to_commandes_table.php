<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commandes', 'categorie')) {
            Schema::table('commandes', function (Blueprint $table) {
                $table->string('categorie', 20)->default('detail')->after('flacon_id');
                $table->index('categorie');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('commandes', 'categorie')) {
            Schema::table('commandes', function (Blueprint $table) {
                $table->dropIndex(['categorie']);
                $table->dropColumn('categorie');
            });
        }
    }
};
