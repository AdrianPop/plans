<?php

namespace Rennokki\Plans\Models;

use Illuminate\Database\Eloquent\Model;

class StripeCustomerModel extends Model
{
    protected $table = 'stripe_customers';
    protected $fillable = [
        'model_id', 'model_type', 'customer_id', 'stripe_token',
    ];
    protected $dates = [
        //
    ];

    public function model()
    {
        return $this->morphTo();
    }
}
