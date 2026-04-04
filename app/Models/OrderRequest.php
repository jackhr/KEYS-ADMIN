<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderRequest extends Model
{
    protected $table = 'order_requests';

    protected $fillable = [
        'key',
        'pick_up',
        'drop_off',
        'pick_up_location',
        'drop_off_location',
        'confirmed',
        'status',
        'contact_info_id',
        'sub_total',
        'car_id',
        'days',
    ];

    protected function casts(): array
    {
        return [
            'pick_up' => 'datetime',
            'drop_off' => 'datetime',
            'confirmed' => 'boolean',
            'status' => 'string',
            'sub_total' => 'float',
            'car_id' => 'integer',
            'contact_info_id' => 'integer',
            'days' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'car_id');
    }

    public function contactInfo(): BelongsTo
    {
        return $this->belongsTo(ContactInfo::class, 'contact_info_id');
    }

    public function addOnLinks(): HasMany
    {
        return $this->hasMany(OrderRequestAddOn::class, 'order_request_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderRequestHistory::class, 'order_request_id')->latest('created_at');
    }
}
