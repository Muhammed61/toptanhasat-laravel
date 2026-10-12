<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    public $timestamps = false;

    protected $fillable = ['id', 'name'];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
