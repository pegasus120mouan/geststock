<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commande_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->restrictOnDelete();
            $table->foreignId('flacon_id')->constrained('flacons')->restrictOnDelete();
            $table->string('categorie', 20)->default('detail');
            $table->unsignedInteger('quantite')->default(1);
            $table->decimal('prix_unitaire', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['commande_id', 'categorie']);
            $table->index('produit_id');
        });

        $commandes = DB::table('commandes')
            ->whereNotNull('produit_id')
            ->whereNotNull('flacon_id')
            ->get();

        $now = now();

        foreach ($commandes as $commande) {
            $quantite = max(1, (int) ($commande->quantite ?? 1));
            $prix = (float) ($commande->prix_unitaire ?? 0);
            $totalLigne = (float) ($commande->total ?? 0);

            if ($totalLigne <= 0 && $prix > 0) {
                $totalLigne = round($prix * $quantite, 2);
            }

            DB::table('commande_lignes')->insert([
                'commande_id' => $commande->id,
                'produit_id' => $commande->produit_id,
                'flacon_id' => $commande->flacon_id,
                'categorie' => $commande->categorie ?: 'detail',
                'quantite' => $quantite,
                'prix_unitaire' => $prix,
                'total' => $totalLigne,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('commandes', function (Blueprint $table) {
            if (Schema::hasColumn('commandes', 'produit_id')) {
                $table->dropConstrainedForeignId('produit_id');
            }
            if (Schema::hasColumn('commandes', 'flacon_id')) {
                $table->dropConstrainedForeignId('flacon_id');
            }
            foreach (['categorie', 'quantite', 'prix_unitaire'] as $column) {
                if (Schema::hasColumn('commandes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->foreignId('produit_id')->nullable()->after('reference')->constrained('produits')->nullOnDelete();
            $table->foreignId('flacon_id')->nullable()->after('produit_id')->constrained('flacons')->nullOnDelete();
            $table->string('categorie', 20)->default('detail')->after('flacon_id');
            $table->unsignedInteger('quantite')->default(1)->after('categorie');
            $table->decimal('prix_unitaire', 14, 2)->default(0)->after('quantite');
        });

        $lignes = DB::table('commande_lignes')
            ->orderBy('id')
            ->get()
            ->groupBy('commande_id');

        foreach ($lignes as $commandeId => $items) {
            $first = $items->first();
            DB::table('commandes')->where('id', $commandeId)->update([
                'produit_id' => $first->produit_id,
                'flacon_id' => $first->flacon_id,
                'categorie' => $first->categorie,
                'quantite' => $first->quantite,
                'prix_unitaire' => $first->prix_unitaire,
                'total' => $items->sum('total'),
            ]);
        }

        Schema::dropIfExists('commande_lignes');
    }
};
