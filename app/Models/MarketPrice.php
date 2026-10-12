<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Model;

/** Ana sayfadaki hal fiyatları şeridi */
class MarketPrice extends Model
{
    use FlushesSiteCache;

    protected $fillable = ['product', 'price', 'change', 'sort', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'change' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
