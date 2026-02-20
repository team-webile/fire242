<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallCenterAnswer extends Model
{
    use HasFactory;

    protected $table = 'call_center_answer';

    protected $fillable = [
        'call_center_id',
        'question_id',
        'answer_id',
    ];

    // Relationship: An answer belongs to a call center session
    public function callCenter()
    {
        return $this->belongsTo(CallCenter::class, 'call_center_id');
    }
}