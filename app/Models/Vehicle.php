<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $table = 'vehicles';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'type',
        'slug',
        'image_filename',
        'showing',
        'landing_order',
        'base_price_XCD',
        'base_price_USD',
        'insurance',
        'times_requested',
        'people',
        'bags',
        'doors',
        '4wd',
        'ac',
        'manual',
    ];

    protected function casts(): array
    {
        return [
            'showing' => 'boolean',
            'landing_order' => 'integer',
            'base_price_XCD' => 'float',
            'base_price_USD' => 'float',
            'insurance' => 'integer',
            'times_requested' => 'integer',
            'people' => 'integer',
            'bags' => 'integer',
            'doors' => 'integer',
            '4wd' => 'boolean',
            'ac' => 'boolean',
            'manual' => 'boolean',
        ];
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(VehicleDiscount::class, 'vehicle_id');
    }

    public function orderRequests(): HasMany
    {
        return $this->hasMany(OrderRequest::class, 'car_id');
    }
}
