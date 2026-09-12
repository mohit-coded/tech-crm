<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    use HasFactory, BelongsToLocation;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'location_id',
        'first_name',
        'last_name',
        'email',
        'phone',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * This Contact's single most recent Message, for the conversations
     * index preview — lets the controller eager-load it directly
     * instead of pulling every message just to read the last one.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
