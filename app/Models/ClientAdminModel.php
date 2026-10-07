<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class ClientAdminModel extends Model
{
    use HasFactory;
    protected $table = 'user';
    protected $fillable = [
        'name',
        'email',
        'phone',
        'city',
        'state',
        'country',
        'status'
    ];
}
