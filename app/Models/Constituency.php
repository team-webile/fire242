<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Constituency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'island_id', 
        'is_active',
        'position'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ]; 

    protected $with = ['island'];

    public function island()
    {
        return $this->belongsTo(Island::class);
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->whereRaw('LOWER(TRIM(name)) LIKE ?', ['%' . $searchTerm . '%']);
    }

    // Relationship with Voters
    public function voters(): \Illuminate\Database\Eloquent\Relations\HasMany
    { 
        return $this->hasMany(Voter::class, 'const', 'id');
    }

    public function systemSettings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ManagerSystemSetting::class, 'constituency_id', 'id');
    }
}
