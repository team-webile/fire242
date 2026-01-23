<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'name',
        'address',
        'city',
        'state',
        'postal_code',
        'latitude',
        'longitude',
        'is_active',
        'position'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float'
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
              ->orWhereRaw('LOWER(city) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
              ->orWhereRaw('LOWER(state) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
              ->orWhereHas('country', function ($query) use ($searchTerm) {
                  $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
              });
        });
    }
}
