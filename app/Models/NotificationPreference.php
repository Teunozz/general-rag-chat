<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email_enabled',
        'daily_recap',
        'weekly_recap',
        'monthly_recap',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'daily_recap' => 'boolean',
            'weekly_recap' => 'boolean',
            'monthly_recap' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
