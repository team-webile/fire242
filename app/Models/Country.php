<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = ['name','is_active']; 

    public function constituencies()
    {
        return $this->hasMany(Constituency::class);
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

}
