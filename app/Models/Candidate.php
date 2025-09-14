<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Candidate extends Model
{
    protected $fillable = [
       'category_id',
        'first_name',
        'last_name',
        'phone',
        'position_number',
        'photo',
        'bio',
        'social_links',
        'video_url',
        'created_by'
    ];

    protected $casts = [
        'social_links' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }
}
