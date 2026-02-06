<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'url',
        'crawl_depth',
        'refresh_interval',
        'min_content_length',
        'require_article_markup',
        'status',
        'error_message',
        'last_indexed_at',
        'document_count',
        'chunk_count',
    ];

    protected function casts(): array
    {
        return [
            'crawl_depth' => 'integer',
            'refresh_interval' => 'integer',
            'min_content_length' => 'integer',
            'require_article_markup' => 'boolean',
            'last_indexed_at' => 'datetime',
            'document_count' => 'integer',
            'chunk_count' => 'integer',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_source');
    }
}
