<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRequestHistory extends Model
{
    protected $table = 'order_request_history';

    public $timestamps = false;

    protected $fillable = [
        'order_request_id',
        'admin_user',
        'action',
        'change_summary',
        'previous_data',
        'new_data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_data' => 'array',
            'new_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function orderRequest(): BelongsTo
    {
        return $this->belongsTo(OrderRequest::class, 'order_request_id');
    }
}
