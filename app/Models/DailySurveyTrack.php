<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySurveyTrack extends Model
{
    protected $fillable = ['user_id', 'date', 'total_surveys', 'completion_percentage'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}