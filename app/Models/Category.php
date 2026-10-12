<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use FlushesSiteCache;

    /** Vue'daki ProduceIcon bileşeninde çizili ikonlar */
    public const ICONS = [
        'sebze' => 'Havuç (sebze)',
        'meyve' => 'Elma (meyve)',
        'narenciye' => 'Dilim (narenciye)',
        'tahil' => 'Başak (tahıl)',
        'bakliyat' => 'Fasulye (bakliyat)',
        'kuruyemis' => 'Fındık (kuruyemiş)',
    ];

    protected $fillable = ['slug', 'name', 'icon', 'badge', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('sort')->orderBy('name');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }
}
