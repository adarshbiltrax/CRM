<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Orgnization extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'orgnizations';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'city',
        'state',
        'country',
        'status',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function clientAdmins()
    {
        return $this->users()->where('role_id', 2);
    }
}
