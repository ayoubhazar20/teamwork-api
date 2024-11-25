<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable; // Import the Authenticatable

class NewUser extends Authenticatable
{
    use HasFactory;
    protected $fillable = [
        'name',
        'email',
        'password',
        // Add other fields as needed
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // If you're using Laravel's built-in email verification:
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

}