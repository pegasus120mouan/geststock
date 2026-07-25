<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->decimal('prix_unitaire', 14, 2)->default(0)->after('quantite');
        });

        $prixUnitaires = DB::table('prix_unitaires')->get();

        foreach ($prixUnitaires as $prix) {
            DB::table('commandes')
                ->where('produit_id', $prix->produit_id)
                ->where('flacon_id', $prix->flacon_id)
                ->where('prix_unitaire', 0)
                ->update([
                    'prix_unitaire' => $prix->prix,
                    'total' => DB::raw('quantite * '.$prix->prix),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('prix_unitaire');
        });
    }
};
