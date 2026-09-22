<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cocktail_lignes MODIFY quantite_ml DECIMAL(14,2) NULL');
    }

    public function down(): void
    {
        DB::table('cocktail_lignes')->whereNull('quantite_ml')->update(['quantite_ml' => 0]);
        DB::statement('ALTER TABLE cocktail_lignes MODIFY quantite_ml DECIMAL(14,2) NOT NULL');
    }
};
