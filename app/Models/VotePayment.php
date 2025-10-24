<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;



class VotePayment extends Model
{
    protected $fillable = [
         'amount', 'visitor_token','candidate_id',
        'payment_status', 'transaction_reference', 'network','ip_address','votes',
    ];

    public function vote()
    {
        return $this->belongsTo(Vote::class);
    }

     public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }
}
