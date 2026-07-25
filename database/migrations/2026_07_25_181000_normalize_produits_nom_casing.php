<?php

use App\Models\Produit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('produits')->orderBy('id')->chunkById(100, function ($produits) {
            foreach ($produits as $produit) {
                $formatted = Produit::formatNomParfum($produit->nom);

                if ($formatted !== $produit->nom) {
                    DB::table('produits')
                        ->where('id', $produit->id)
                        ->update(['nom' => $formatted]);
                }
            }
        });
    }

    public function down(): void
    {
        // Irreversible formatting.
    }
};
