<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UnregisteredVoter extends Model
{
    use HasFactory;

    protected $fillable = [
     
        'date_of_birth', 
        'gender',
        'phone_number',
        'new_email',
        'new_address',
        'user_id',
        'voter_id',
        'survey_id',
        'contacted',
        'party',
        'note',
        'first_name',
        'last_name',
        'diff_address',
        'living_constituency',
        'surveyer_constituency' 
         
    ];
 
    // protected $casts = [
    //     'date_of_birth' => 'date'
    // ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function surveyerConstituency()
    {
        return $this->belongsTo(Constituency::class, 'surveyer_constituency', 'id');
    }
    
    public function livingConstituency()
    {
        return $this->belongsTo(Constituency::class, 'living_constituency', 'id');
    }
    


    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone('America/New_York');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone('America/New_York');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function voter()
    {
        return $this->belongsTo(Voter::class);
    }

    public function notes()
    {
        return $this->hasMany(VoterNote::class);
    }

    public function voterNotes()
    {
        return $this->hasMany(VoterNote::class);
    }
}