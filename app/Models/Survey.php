<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\SurveyAnswer;
class Survey extends Model
{
    use HasFactory;

    // The table associated with the model
    protected $table = 'surveys';

    // The attributes that are mass assignable
    protected $fillable = [
        'voter_id',
        'user_id',
        'sex',
        'marital_status',
        'employed',
        'children',
        'employment_type',
        'employment_sector',
        'religion', 
        'located',
        'island',
        'country',
        'country_location',
        'home_phone_code',
        'home_phone',
        'work_phone_code',
        'work_phone',
        'cell_phone_code',
        'cell_phone',
        'email',
        'special_comments',
        'other_comments',
        'voting_for',
        'last_voted',
        'voted_for_party',
        'voted_where',
        'voted_in_house',
        'voter_image',
        'house_image',
        'voters_in_house',
        'created_by',
        'updated_by',
        'note',
        'question_one',
        'answer_one',
        'question_two', 
        'answer_two',
        'question_three',
        'answer_three',
        'voting_decision',
        'is_died',
        'died_date',
        'challange'
    ]; 
 
    protected $appends = ['voter_image_url', 'home_image_url'];

    // The attributes that should be cast
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'challange' => 'boolean',
    ];

  
    

    /**
     * Get the voter that owns the survey.
     */
    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    /**
     * Get the user that owns the survey.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user that created the survey.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user that last updated the survey.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getVoterImageUrlAttribute()
    {
        return $this->voter_image ? url('storage/' . $this->voter_image) : null;
    }

    public function getHomeImageUrlAttribute()
    {
        return $this->home_image ? url('storage/' . $this->home_image) : null;
    }

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone('America/New_York');
    }

    public function getUpdatedAtAttribute($value) 
    {
        return Carbon::parse($value)->setTimezone('America/New_York');
    }

    // Add this relationship to your Survey model
    public function answers()
    {
        return $this->hasMany(SurveyAnswer::class);
    }

    public function surveyAnswers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class, 'survey_id');
    }
    
}
