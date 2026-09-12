<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'twilio_phone_number',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'timezone',
    ];

    /**
     * Users who are members of this location (may switch into it).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'location_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Users whose currently active location is this one.
     */
    public function activeUsers(): HasMany
    {
        return $this->hasMany(User::class, 'current_location_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
