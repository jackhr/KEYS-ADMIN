<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDiscount extends Model
{
    protected $table = 'vehicle_discounts';

    public $timestamps = false;

    protected $fillable = [
        'vehicle_id',
        'price_XCD',
        'price_USD',
        'days',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_id' => 'integer',
            'price_XCD' => 'float',
            'price_USD' => 'float',
            'days' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
