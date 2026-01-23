<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email', 
        'password',
        'role_id',
        'phone',
        'address',
        'image',
        'is_active',
        'constituency_id',
        'manager_id',
        'time_active',
        'is_super_user',
        'last_token_id'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['image_url'];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'is_admin' => 'boolean',
    ];


    public function getImageUrlAttribute()
    {
        return $this->image ? url('storage/' . $this->image) : url('storage/users/avatar.avif');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function hasRole($role)
    {
        return $this->role->slug === $role;
    }

     

    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function constituency()
    {
        return $this->belongsTo(Constituency::class);
    }

    public function surveys()
    {
        return $this->hasMany(Survey::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

   
}
