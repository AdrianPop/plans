<?php

namespace Rennokki\Plans\Models;

use Illuminate\Database\Eloquent\Model;

class PlanModel extends Model
{
    protected $table = 'plans';
    protected $fillable = [
        'name', 'description', 'price', 'currency', 'duration',
    ];

    public function features()
    {
        return $this->hasMany(config('plans.models.feature'), 'plan_id');
    }
}
