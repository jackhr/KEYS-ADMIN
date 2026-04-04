<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRequestAddOn extends Model
{
    protected $table = 'order_request_add_ons';

    public $timestamps = false;

    protected $fillable = [
        'order_request_id',
        'add_on_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'order_request_id' => 'integer',
            'add_on_id' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function orderRequest(): BelongsTo
    {
        return $this->belongsTo(OrderRequest::class, 'order_request_id');
    }

    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class, 'add_on_id');
    }
}
