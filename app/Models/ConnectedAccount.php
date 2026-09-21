<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConnectedAccount extends Model
{
    use HasFactory, BelongsToLocation;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'location_id',
        'provider',
        'external_account_id',
        'external_account_name',
        'access_token',
        'refresh_token',
        'expires_at',
        'connected_at',
    ];

    /**
     * The attributes that should be hidden for serialization — belt and
     * braces alongside the 'encrypted' cast below: even if this model
     * were ever accidentally serialized to JSON (an API response, a log
     * call), the raw tokens must never appear in it.
     *
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * access_token/refresh_token use Eloquent's 'encrypted' cast — they
     * are transparently encrypted with the app's APP_KEY on write and
     * decrypted on read, so applications code (and this model's own
     * callers) never has to think about it, but the raw DB column never
     * holds the plaintext token. See
     * tests/Feature/ConnectedAccountControllerTest.php's encryption
     * test, which queries the column directly (bypassing this cast) to
     * prove it.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }
}
