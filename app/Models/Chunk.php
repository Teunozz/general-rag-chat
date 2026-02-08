<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\Vector;

class Chunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'content',
        'position',
        'token_count',
        'embedding',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'token_count' => 'integer',
            'embedding' => Vector::class,
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
