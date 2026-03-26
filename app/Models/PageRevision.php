<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['page_id', 'encrypted_content', 'content_nonce', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
