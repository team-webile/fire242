<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['start_time', 'end_time', 'active_time','daily_target','days','admin_active_time'];
}