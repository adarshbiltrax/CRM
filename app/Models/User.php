<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role_id', 'orgnization_id', 'manager_id', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public function orgnization()
    {
        return $this->belongsTo(Orgnization::class, 'orgnization_id')->withTrashed();
    }

    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_id')->withTrashed();
    }

    public function executives()
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'manager_id');
    }

    public function receivedTasks()
    {
        return $this->hasMany(Task::class, 'executive_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
