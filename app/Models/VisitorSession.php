<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitorSession extends Model
{
    protected $fillable = [
        'visitor_id',
        'session_id',
        'first_seen_at',
        'last_seen_at',
        'entry_path',
        'entry_referrer',
        'ip_address',
        'user_agent',
        'device_type',
        'is_bot',
        'os_name',
        'browser_name',
        'language',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_bot' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(VisitorPageView::class);
    }
}
