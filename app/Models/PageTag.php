<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageTag extends Model
{
    public $timestamps = false;

    protected $fillable = ['page_id', 'tag'];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
