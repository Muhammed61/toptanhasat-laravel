<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Ana sayfadaki reklam görseli: başlık görselin altında yazar, görsele tıklanınca bağlantıya gider */
class Banner extends Model
{
    use FlushesSiteCache;

    protected $fillable = ['title', 'image_path', 'url', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Görsel değişince ya da kayıt silinince eski dosyayı kaldır
        static::updated(function (Banner $banner) {
            if ($banner->wasChanged('image_path') && $old = $banner->getOriginal('image_path')) {
                Storage::disk('public')->delete($old);
            }
        });
        static::deleted(fn (Banner $banner) => Storage::disk('public')->delete($banner->image_path));
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }
}
