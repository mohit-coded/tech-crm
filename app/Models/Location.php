<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'timezone',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'current_location_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
