<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoterImportList extends Model
{
    protected $fillable = [
        'filename',
        'file_path', 
        'status',
        'processed_rows',
        'total_rows',
        'progress',
        'error_message',
        'started_at',
        'last_processed_at',
        'completed_at',
        'failed_at'
    ];
} 
 