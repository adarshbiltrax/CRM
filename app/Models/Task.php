<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'orgnization_id',
        'client_admin_id',
        'template_id',
        'manager_id',
        'executive_id',
        'title',
        'description',
        'status',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function clientAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_admin_id');
    }

    public function orgnization(): BelongsTo
    {
        return $this->belongsTo(Orgnization::class, 'orgnization_id')->withTrashed();
    }

    public function executive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executive_id');
    }
}
