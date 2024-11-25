<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $primaryKey = 'company_id'; // Specify the primary key
    public $incrementing = false; // Set to false since 'company_id' is not an auto-incrementing integer
    protected $keyType = 'string';
    use HasFactory;
    protected $fillable = [
        'company_id',
        'name',
        'api_key',
        'access_granted', // Add this line

    ];

    protected $table = 'companies'; // Define the table name
    // Define the relationship with the User model
    public function users()
    {
        return $this->hasMany(User::class,'company_id');
    }
}