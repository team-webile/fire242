<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model 
{
    use HasFactory;

    protected $fillable = ['question', 'position']; // Fillable fields

    public function answers()
    {
        return $this->hasMany(Answer::class)->orderBy('position', 'asc'); // One question can have many answers ordered by position ascending
    }

    public function voter()
    {
        return $this->belongsTo(Voter::class);
    }
}