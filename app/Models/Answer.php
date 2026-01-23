<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model 
{
    use HasFactory;

    protected $fillable = ['question_id', 'answer', 'position']; // Fillable fields

    public function question()
    {
        return $this->belongsTo(Question::class); // Each answer belongs to one question
    }

    public function answer()
    {
        return $this->belongsTo(Answer::class); // Each answer can belong to another answer
    }
}