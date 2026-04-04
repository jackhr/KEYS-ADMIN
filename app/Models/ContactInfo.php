<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactInfo extends Model
{
    protected $table = 'contact_info';

    public $timestamps = false;

    protected $fillable = [
        'first_name',
        'last_name',
        'driver_license',
        'hotel',
        'country_or_region',
        'street',
        'town_or_city',
        'state_or_county',
        'phone',
        'email',
    ];

    public function orderRequests(): HasMany
    {
        return $this->hasMany(OrderRequest::class, 'contact_info_id');
    }
}
