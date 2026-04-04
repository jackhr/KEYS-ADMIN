<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminUser extends Model
{
    protected $table = 'admin_users';

    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'role',
        'active',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(AdminApiToken::class, 'admin_user_id');
    }
}
