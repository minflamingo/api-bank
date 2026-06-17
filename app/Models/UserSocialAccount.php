<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSocialAccount extends Model
{
    protected $table = 'user_social_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'email',
        'display_name',
        'avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'raw_profile',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'raw_profile',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'token_expires_at' => 'datetime',
        'raw_profile' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
