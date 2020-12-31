<?php

namespace Rennokki\Plans\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class PlanModel
 *
 * @property string $name
 * @property string $code
 * @property string $tag
 * @property string $description
 * @property float $price
 * @property string $currency
 * @property int $duration
 */
class PlanModel extends Model
{
    protected $table = 'plans';
    protected $guarded = [];
    protected $casts = [
        'metadata' => 'object',
    ];

    public function getNameAndPriceAttribute()
    {
        return sprintf('%s - %s %s', $this->name, $this->price, $this->currency);
    }

    public function features()
    {
        return $this->hasMany(config('plans.models.feature'), 'plan_id');
    }

    /**
     * @param $code
     *
     * @return self
     */
    public static function byCode($code)
    {
        return self::query()
            ->with('features')
            ->where('code', $code)->first();
    }

    public static function byCodeAndTag($code, $tag)
    {
        return self::query()
            ->with('features')
            ->where('code', $code)
            ->where('tag', $tag)
            ->first();
    }
}
