<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MisTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'manager_id',
        'day',
        'date',
        'department_role',
        'task_activity',
        'target',
        'actual_output',
        'status',
        'percentage',
        'time_spent',
        'pending_reason',
        'remarks',
        'priority',
        'category',
        'title',
        'description',
        'progress',
        'due_date',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
