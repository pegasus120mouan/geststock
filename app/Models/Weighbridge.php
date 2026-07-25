<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Weighbridge extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'location',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function agents()
    {
        return $this->belongsToMany(User::class, 'weighbridge_user')
            ->withPivot(['assigned_at'])
            ->withTimestamps();
    }

    public function weighings()
    {
        return $this->hasMany(Weighing::class);
    }
}
