<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance2 extends Model
{
    protected $table = 'attendance2';
    
    protected $fillable = [
        'user_id',
        'timestamp', 
        'status',
        'punch',
    ];
    
    protected $casts = [
        'timestamp' => 'datetime',
    ];
}
