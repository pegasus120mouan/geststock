<?php

use App\Models\Cocktail;
use App\Models\Commande;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commande_lignes', function (Blueprint $table) {
            if (! Schema::hasColumn('commande_lignes', 'cocktail_id')) {
                $table->foreignId('cocktail_id')->nullable()->after('flacon_id')->constrained('cocktails')->nullOnDelete();
            }
            if (! Schema::hasColumn('commande_lignes', 'cocktail_groupe')) {
                $table->unsignedInteger('cocktail_groupe')->nullable()->after('cocktail_id');
            }
        });

        $cocktails = Cocktail::query()->with('lignes')->get();

        Commande::query()
            ->whereHas('lignes', fn ($q) => $q->whereNotNull('quantite_ml'))
            ->with(['lignes'])
            ->chunkById(50, function ($commandes) use ($cocktails) {
                foreach ($commandes as $commande) {
                    foreach ($commande->partitionsLignesCocktail($cocktails) as $gIndex => $lignes) {
                        foreach ($lignes as $ligne) {
                            $ligne->forceFill([
                                'cocktail_id' => $ligne->cocktail_id ?: null,
                                'cocktail_groupe' => $gIndex,
                            ])->save();
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('commande_lignes', function (Blueprint $table) {
            if (Schema::hasColumn('commande_lignes', 'cocktail_id')) {
                $table->dropConstrainedForeignId('cocktail_id');
            }
            if (Schema::hasColumn('commande_lignes', 'cocktail_groupe')) {
                $table->dropColumn('cocktail_groupe');
            }
        });
    }
};
