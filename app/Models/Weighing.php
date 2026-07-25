<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Weighing extends Model
{
    use HasFactory;

    protected $fillable = [
        'weighbridge_id',
        'truck_id',
        'agent_id',
        'driver_id',
        'gross_weight',
        'tare_weight',
        'net_weight',
        'weighed_at',
        'reference',
        'notes',
    ];

    protected $casts = [
        'gross_weight' => 'decimal:3',
        'tare_weight' => 'decimal:3',
        'net_weight' => 'decimal:3',
        'weighed_at' => 'datetime',
    ];

    public function weighbridge()
    {
        return $this->belongsTo(Weighbridge::class);
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
