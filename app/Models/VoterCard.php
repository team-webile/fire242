<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Constituency;

class VoterCard extends Model
{
    protected $table = 'voter_cards';
    protected $fillable = ['*'];

    public function voter()
    {
        return $this->belongsTo(Voter::class, 'registration_number', 'voter');
    } 
    public function constituency()
    {
        return $this->belongsTo(Constituency::class, 'constituency_id');
    }

}
