<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pencatatan pemakaian "search" SerpApi per hari (guard kuota).
 */
class SerpApiUsage extends Model
{
    protected $table = 'serpapi_usage';

    protected $fillable = [
        'usage_date',
        'searches',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'searches' => 'integer',
        ];
    }
}
