<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'notebook_id',
        'title',
        'encrypted_content',
        'content_nonce',
        'date_mode',
        'page_date',
        'template_type',
        'is_pinned',
        'is_favorited',
        'word_count',
        'sort_order',
        'trashed_at',
    ];

    protected function casts(): array
    {
        return [
            'page_date' => 'date',
            'trashed_at' => 'datetime',
            'is_pinned' => 'boolean',
            'is_favorited' => 'boolean',
            'word_count' => 'integer',
        ];
    }

    public function notebook()
    {
        return $this->belongsTo(Notebook::class);
    }

    public function tags()
    {
        return $this->hasMany(PageTag::class);
    }

    public function revisions()
    {
        return $this->hasMany(PageRevision::class);
    }

    public function searchTokens()
    {
        return $this->hasMany(SearchToken::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('trashed_at');
    }

    public function scopeTrashed(Builder $query): Builder
    {
        return $query->whereNotNull('trashed_at');
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    public function scopeFavorited(Builder $query): Builder
    {
        return $query->where('is_favorited', true);
    }
}
