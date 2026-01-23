<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Constituency;

class VoterCardImage extends Model
{
    protected $table = 'voter_cards_images'; 
    protected $fillable = [
        'user_id',
        'image',
        'reg_no',
        'voter_name',
        'exit_poll',
        'processed',
        'created_at', 
        'updated_at'
    ];
    protected $appends = ['image_url'];
  

    public function getImageUrlAttribute()
    {
        return $this->image ? url('storage/' . $this->image) : url('storage/users/avatar.avif');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship with Voter
    public function voter()
    {
        return $this->belongsTo(Voter::class, 'reg_no', 'voter');
    }

    
}
