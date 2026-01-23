<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoterNote extends Model
{
    protected $table = 'voter_notes';

    protected $fillable = [
        'note',
        'unregistered_voter_id', 
        'user_id'
    ];

    public function unregisteredVoter()
    {
        return $this->belongsTo(UnregisteredVoter::class);
    }

    public function voter()
    {
        return $this->belongsTo(UnregisteredVoter::class, 'unregistered_voter_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 