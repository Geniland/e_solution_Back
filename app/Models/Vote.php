<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Vote extends Model
{
    protected $fillable = [
        'candidate_id', 'category_id', 'user_id',
        'visitor_token', 'ip_address'
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // public function payment()
    // {
    //     return $this->hasOne(VotePayment::class);
    // }

    public function votePayments()
    {
        return $this->hasMany(VotePayment::class);
    }
}

