<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkStatus extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active', 'order_column'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function works(): HasMany
    {
        return $this->hasMany(Work::class);
    }
}
