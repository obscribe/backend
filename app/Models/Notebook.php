<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Notebook extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'icon',
        'color',
        'type',
        'destruction_at',
        'is_archived',
        'is_locked',
        'sort_order',
        'trashed_at',
    ];

    protected function casts(): array
    {
        return [
            'destruction_at' => 'datetime',
            'trashed_at' => 'datetime',
            'is_archived' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('trashed_at')->where('is_archived', false);
    }

    public function scopeTrashed(Builder $query): Builder
    {
        return $query->whereNotNull('trashed_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true)->whereNull('trashed_at');
    }
}
