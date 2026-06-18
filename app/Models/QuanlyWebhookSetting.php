<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuanlyWebhookSetting extends Model
{
    protected $fillable = [
        'user_id',
        'url',
        'secret',
        'events',
        'is_active',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'events' => 'array',
        'is_active' => 'boolean',
    ];

    public function accepts(string $event): bool
    {
        return $this->isUsable() && in_array($event, $this->events ?: [], true);
    }

    public function isUsable(): bool
    {
        return $this->is_active
            && trim((string) $this->url) !== ''
            && trim((string) $this->secret) !== '';
    }
}
