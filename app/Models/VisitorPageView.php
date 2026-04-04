<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorPageView extends Model
{
    protected $fillable = [
        'visitor_session_id',
        'visitor_id',
        'visited_at',
        'route_path',
        'full_url',
        'query_string',
        'referrer',
        'user_agent',
        'device_type',
        'is_bot',
        'os_name',
        'browser_name',
        'language',
        'timezone',
        'ip_address',
        'viewport_width',
        'viewport_height',
        'screen_width',
        'screen_height',
        'event_type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'is_bot' => 'boolean',
            'viewport_width' => 'integer',
            'viewport_height' => 'integer',
            'screen_width' => 'integer',
            'screen_height' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(VisitorSession::class, 'visitor_session_id');
    }
}
