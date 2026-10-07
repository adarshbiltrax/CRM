<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{
    protected $table = 'role';

    protected $fillable = ['role'];

    public function user()
    {
        return $this->hasMany(User::class);
    }

}
