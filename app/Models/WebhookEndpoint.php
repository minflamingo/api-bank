<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    public const EVENTS = [
        'transaction.created',
        'transaction.updated',
        'balance.updated',
        'account.session_expired',
        'recharge.matched',
        'recharge.failed',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'url',
        'events',
        'secret',
        'is_active',
        'last_success_at',
        'last_failure_at',
        'last_error',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'events' => 'array',
        'is_active' => 'boolean',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    public function accepts(string $event): bool
    {
        return $this->is_active && in_array($event, $this->events ?: [], true);
    }
}
