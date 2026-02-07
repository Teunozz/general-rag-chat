<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property \Carbon\Carbon $period_start
 * @property \Carbon\Carbon $period_end
 */
class Recap extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'period_start',
        'period_end',
        'document_count',
        'summary',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'document_count' => 'integer',
        ];
    }
}
