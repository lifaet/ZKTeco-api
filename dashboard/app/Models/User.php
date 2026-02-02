<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'name', 'title', 'department', 'active'
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
