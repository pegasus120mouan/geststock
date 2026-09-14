<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\MouvementSolde;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'prenom',
        'login',
        'contact',
        'matricule',
        'chef_equipe_token',
        'id_chef',
        'avatar',
        'password',
        'code_pin',
        'role',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'code_pin' => 'hashed',
            'permissions' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGestionnaire(): bool
    {
        return $this->role === 'gestionnaire';
    }

    public function canWrite(): bool
    {
        return $this->isAdmin();
    }

    public function canView(string $module): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isGestionnaire()) {
            return false;
        }

        $permissions = is_array($this->permissions) ? $this->permissions : [];

        return in_array($module, $permissions, true);
    }

    /** @return list<string> */
    public function viewPermissions(): array
    {
        if ($this->isAdmin()) {
            return \App\Support\ModulePermissions::keys();
        }

        return array_values(array_intersect(
            \App\Support\ModulePermissions::keys(),
            is_array($this->permissions) ? $this->permissions : []
        ));
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Administrateur',
            'gestionnaire' => 'Gestionnaire',
            'agent' => 'Agent',
            'driver' => 'Chauffeur',
            default => ucfirst((string) $this->role),
        };
    }

    public function getAvatarUrlAttribute(): string
    {
        $avatar = is_string($this->avatar) ? trim($this->avatar) : '';

        if ($avatar === '') {
            return asset('img/avatars/default.png');
        }

        if (str_contains($avatar, '/')) {
            return Storage::disk('public')->url($avatar);
        }

        return asset('img/avatars/' . $avatar);
    }

    public function assignedWeighbridges(): BelongsToMany
    {
        return $this->belongsToMany(PontPesage::class, 'agent_pont_pesage', 'agent_id', 'pont_pesage_id')
            ->withPivot(['affecte_le'])
            ->withTimestamps();
    }

    public function trucksAsDriver(): HasMany
    {
        return $this->hasMany(Camion::class, 'chauffeur_id');
    }

    public function mouvementsSoldes(): HasMany
    {
        return $this->hasMany(MouvementSolde::class, 'user_id');
    }

    public function weighingsAsAgent(): HasMany
    {
        return $this->hasMany(Pesee::class, 'agent_id');
    }

    public function weighingsAsDriver(): HasMany
    {
        return $this->hasMany(Pesee::class, 'chauffeur_id');
    }
}
