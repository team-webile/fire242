<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Island extends Model
{
    protected $fillable = [
        'id',
        'name'
    ];

    public function constituencies()
    {
        return $this->hasMany(Constituency::class);
    }
}
