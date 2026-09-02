<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['full_name', 'phone_number', 'birth_date'];

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }
}
