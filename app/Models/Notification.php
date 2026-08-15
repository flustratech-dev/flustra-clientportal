<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public static function send(int $userId, string $title, ?string $body = null, string $type = 'info', ?string $url = null): self
    {
        return self::create([
            'user_id' => $userId,
            'title'   => $title,
            'body'    => $body,
            'type'    => $type,
            'url'     => $url,
        ]);
    }

    public function getIconColorAttribute(): string
    {
        return match ($this->type) {
            'success' => 'bg-emerald-500',
            'warning' => 'bg-amber-500',
            'error'   => 'bg-red-500',
            default   => 'bg-blue-500',
        };
    }
}
