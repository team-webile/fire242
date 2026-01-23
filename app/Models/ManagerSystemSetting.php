<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagerSystemSetting extends Model
{
    protected $fillable = ['start_time', 'end_time', 'active_time','daily_target','days','manager_id','constituency_id','all_constituency'];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function constituency()
    {
        return $this->belongsTo(Constituency::class, 'constituency_id');
    }
}