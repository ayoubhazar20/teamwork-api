<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name','user_id',
        'email','first_name' ,'last_name', // Ensure these fields exist
        'portal_id','super_admin' // Ensure this field exists
    ];

    // Define the relationship with the Company model
    public function company()
    {
        return $this->belongsTo(Company::class ,'portal_id');
    }

    // Define the relationship with the Token model
    public function tokens()
    {
        return $this->hasMany(Token::class );
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}