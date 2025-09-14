<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{

     protected $fillable = [
        'name', 'description', 'banner', 'start_date',
        'end_date', 'is_closed', 'created_by', 'amount'
    ];

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }

}
