<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterClassification extends Model
{
    protected $fillable = ['code', 'name', 'is_active', 'order_column'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }
}
