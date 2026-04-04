<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddOn extends Model
{
    use HasFactory;

    protected $table = 'add_ons';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'cost',
        'description',
        'abbr',
        'fixed_price',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'float',
            'fixed_price' => 'boolean',
        ];
    }

    public function orderRequestAddOns(): HasMany
    {
        return $this->hasMany(OrderRequestAddOn::class, 'add_on_id');
    }
}
