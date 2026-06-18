<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_endpoint_id',
        'user_id',
        'event_id',
        'event',
        'target_url',
        'secret',
        'payload',
        'attempts',
        'max_attempts',
        'next_attempt_at',
        'delivered_at',
        'failed_at',
        'response_status',
        'response_body',
        'last_error',
    ];

    protected $casts = [
        'webhook_endpoint_id' => 'integer',
        'user_id' => 'integer',
        'payload' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'response_status' => 'integer',
    ];

    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
