<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('prix_unitaires', 'categorie')) {
            Schema::table('prix_unitaires', function (Blueprint $table) {
                $table->string('categorie', 20)->default('detail')->after('flacon_id');
            });
        }

        DB::table('prix_unitaires')
            ->where(function ($q) {
                $q->whereNull('categorie')->orWhere('categorie', '');
            })
            ->update(['categorie' => 'detail']);

        // MySQL empêche de dropper l'unique s'il sert aux FK : on recrée les contraintes.
        Schema::table('prix_unitaires', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
            $table->dropForeign(['flacon_id']);
        });

        $this->dropIndexIfExists('prix_unitaires', 'prix_unitaires_produit_id_flacon_id_unique');
        $this->dropIndexIfExists('prix_unitaires', 'prix_unitaires_produit_id_flacon_id_categorie_unique');

        Schema::table('prix_unitaires', function (Blueprint $table) {
            $table->unique(['produit_id', 'flacon_id', 'categorie'], 'prix_unitaires_produit_flacon_categorie_unique');
            $table->foreign('produit_id')->references('id')->on('produits')->cascadeOnDelete();
            $table->foreign('flacon_id')->references('id')->on('flacons')->cascadeOnDelete();
        });

        if (! $this->indexExists('prix_unitaires', 'prix_unitaires_categorie_index')) {
            Schema::table('prix_unitaires', function (Blueprint $table) {
                $table->index('categorie');
            });
        }
    }

    public function down(): void
    {
        Schema::table('prix_unitaires', function (Blueprint $table) {
            $table->dropForeign(['produit_id']);
            $table->dropForeign(['flacon_id']);
        });

        $this->dropIndexIfExists('prix_unitaires', 'prix_unitaires_produit_flacon_categorie_unique');
        $this->dropIndexIfExists('prix_unitaires', 'prix_unitaires_categorie_index');

        Schema::table('prix_unitaires', function (Blueprint $table) {
            if (Schema::hasColumn('prix_unitaires', 'categorie')) {
                $table->dropColumn('categorie');
            }
            $table->unique(['produit_id', 'flacon_id']);
            $table->foreign('produit_id')->references('id')->on('produits')->cascadeOnDelete();
            $table->foreign('flacon_id')->references('id')->on('flacons')->cascadeOnDelete();
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropUnique($index);
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return (int) ($row->c ?? 0) > 0;
    }
};
