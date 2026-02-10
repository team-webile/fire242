<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Survey;
use App\Models\User;
use App\Models\Constituency;

class Voter extends Model
{
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
        'is_contacted',
        'living_constituency',
        'diff_address',
        'flagged',
        'is_national',
        'voter_voting_for',
        'surveyer_constituency',
        'email',
        'phone_code',
        'phone_number',
        'note',
        'user_id',
       
    ];



    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    
    public function survey()
    {
        return $this->belongsTo(Survey::class);
    } 
    public function user()
    {
        return $this->belongsTo(User::class);
    } 
    public function surveys()
    {
        return $this->hasMany(Survey::class);
    }
    // Relationship with Constituency
    public function constituency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Constituency::class, 'const', 'id');
    }
    public function voterCard()
    {
        return $this->hasOne(VoterCard::class, 'registration_number', 'voter');
    }

    // Relationship with VoterCardImage
    public function voterCardImage()
    {
        return $this->hasOne(VoterCardImage::class, 'reg_no', 'voter');
    }


    public function living_constituency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Constituency::class, 'living_constituency', 'id');
    }
    public function surveyer_constituency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Constituency::class, 'surveyer_constituency', 'id'); 
    }

 


  


    
} 