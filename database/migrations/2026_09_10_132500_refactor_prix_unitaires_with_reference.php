<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produit_prix_unitaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->foreignId('prix_unitaire_id')->constrained('prix_unitaires')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['produit_id', 'prix_unitaire_id']);
        });

        Schema::table('prix_unitaires', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('id');
        });

        $rows = DB::table('prix_unitaires')->orderBy('id')->get();
        $groups = [];

        foreach ($rows as $row) {
            $key = $row->flacon_id.'|'.($row->categorie ?: 'detail').'|'.number_format((float) $row->prix, 2, '.', '');
            $groups[$key][] = $row;
        }

        foreach ($groups as $items) {
            $master = $items[0];
            $reference = 'PU-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));

            while (DB::table('prix_unitaires')->where('reference', $reference)->exists()) {
                $reference = 'PU-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
            }

            DB::table('prix_unitaires')->where('id', $master->id)->update([
                'reference' => $reference,
                'categorie' => $master->categorie ?: 'detail',
            ]);

            $produitIds = collect($items)->pluck('produit_id')->filter()->unique()->values();

            foreach ($produitIds as $produitId) {
                DB::table('produit_prix_unitaire')->updateOrInsert(
                    [
                        'produit_id' => $produitId,
                        'prix_unitaire_id' => $master->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            $duplicateIds = collect($items)->pluck('id')->filter(fn ($id) => (int) $id !== (int) $master->id)->all();

            if ($duplicateIds !== []) {
                DB::table('prix_unitaires')->whereIn('id', $duplicateIds)->delete();
            }
        }

        // Drop FK on produit_id carefully (MySQL).
        Schema::table('prix_unitaires', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
        });

        $this->dropIndexIfExists('prix_unitaires', 'prix_unitaires_produit_flacon_categorie_unique');
        $this->dropIndexIfExists('prix_unitaires', 'prix_unitaires_produit_id_flacon_id_categorie_unique');

        Schema::table('prix_unitaires', function (Blueprint $table) {
            $table->dropColumn('produit_id');
            $table->unique('reference');
        });
    }

    public function down(): void
    {
        Schema::table('prix_unitaires', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->foreignId('produit_id')->nullable()->after('id')->constrained('produits')->nullOnDelete();
        });

        $pivots = DB::table('produit_prix_unitaire')->get();

        foreach ($pivots as $pivot) {
            DB::table('prix_unitaires')
                ->where('id', $pivot->prix_unitaire_id)
                ->whereNull('produit_id')
                ->update(['produit_id' => $pivot->produit_id]);
        }

        Schema::dropIfExists('produit_prix_unitaire');

        Schema::table('prix_unitaires', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        if ((int) ($row->c ?? 0) > 0) {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropUnique($index);
            });
        }
    }
};
