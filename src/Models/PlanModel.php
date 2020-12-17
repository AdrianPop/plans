<?php

namespace Rennokki\Plans\Models;

use Illuminate\Database\Eloquent\Model;

class PlanModel extends Model
{
    protected $table = 'plans';
    protected $guarded = [];
    protected $casts = [
        'metadata' => 'object',
    ];

    public function features()
    {
        return $this->hasMany(config('plans.models.feature'), 'plan_id');
    }

    public static function byCode($code)
    {
        return self::query()
            ->with('features')
            ->where('code', $code)->first();
    }
}
