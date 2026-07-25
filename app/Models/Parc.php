<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parc extends Model
{
    protected $table = 'parcs';

    protected $fillable = [
        'nom',
        'code',
        'id_pont',
        'nom_pont',
        'code_pont',
        'adresse',
        'telephone',
        'responsable',
        'description',
        'statut',
    ];

    public function isActif(): bool
    {
        return $this->statut === 'actif';
    }

    public static function generateCode(string $codePont): string
    {
        $count = self::where('code_pont', $codePont)->count() + 1;
        return sprintf('PARC-%s-%03d', strtoupper($codePont), $count);
    }
}
