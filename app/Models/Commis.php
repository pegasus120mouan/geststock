<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Commis extends Model
{
    protected $table = 'commis';

    protected $fillable = [
        'id_pont',
        'nom_pont',
        'code_pont',
        'gerant',
        'nom',
        'prenom',
        'contact',
        'code_pin',
        'password',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'code_pin',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'code_pin' => 'hashed',
        ];
    }

    public function getNomCompletAttribute(): string
    {
        return trim($this->nom . ' ' . $this->prenom);
    }

    public function getAvatarUrlAttribute(): string
    {
        $avatar = is_string($this->avatar) ? trim($this->avatar) : '';

        if ($avatar === '' || in_array($avatar, ['default.png', 'user.png'], true)) {
            return asset('img/avatars/default.png');
        }

        if (str_contains($avatar, '/')) {
            return Storage::disk('public')->url($avatar);
        }

        return asset('img/avatars/' . $avatar);
    }
}
