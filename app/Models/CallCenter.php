<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Voter;
class CallCenter extends Model
{
    protected $table = 'call_center';

    protected $fillable = [
        'call_center',
        'call_center_caller_id',
        'call_center_caller_name',
        'voter_id',
        'call_center_voter_name',
        'call_center_date_time',
        'call_center_email',
        'call_center_phone',
        'call_center_follow_up',
        'call_center_list_voter_contacts',
        'call_center_number_called',
        'call_center_number_calls_made',
        'call_center_soliciting_volunteers',
        'call_center_address_special_concerns',
        'call_center_voting_decisions'
    ];

    public function voter()
    {
        return $this->belongsTo(Voter::class);
    }
}