<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoterHistory extends Model
{ 
    protected $table = 'voter_history';
    protected $fillable = [
        'const',
        'polling', 
        'voter',
        'surname',
        'first_name',
        'second_name',
        'dob',
        'pobcn',
        'pobis',
        'pobse',
        'house_number',
        'blkno',
        'aptno',
        'address',
        'new_dob',
        'new_pobcn',
        'new_pobis', 
        'new_pobse',
        'new_house_number',
        'new_aptno',
        'new_blkno',
        'new_address',
        'changed_fields',
        'action_type'
    ];

    // public function voter()
    // {
    //     return $this->belongsTo(Voter::class);
    // }
    
} 