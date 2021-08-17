<?php

declare(strict_types=1);

namespace Rennokki\Plans\Models;

use App\Models\Invoice;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $proforma_id
 * @property int $invoice_id
 * @property string $payment_method
 * @property CarbonInterface $starts_on
 * @property CarbonInterface $expires_on
 * @property CarbonInterface $grace_until
 * @property CarbonInterface $cancelled_on
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class PlanSubscriptionModel extends Model
{
    protected $table = "plans_subscriptions";
    protected $guarded = [];
    protected $dates = [
        "starts_on",
        "expires_on",
        "cancelled_on",
        "grace_until",
    ];
    protected $casts = [
        "is_paid" => "boolean",
        "is_recurring" => "boolean",
    ];

    protected $with = ["plan"];

    public function model()
    {
        return $this->morphTo();
    }

    public function plan()
    {
        return $this->belongsTo(config("plans.models.plan"), "plan_id");
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, "invoice_id");
    }

    public function proforma()
    {
        return $this->belongsTo(Invoice::class, "proforma_id");
    }

    public function features()
    {
        return $this->plan()
            ->first()
            ->features();
    }

    public function usages()
    {
        return $this->hasMany(config("plans.models.usage"), "subscription_id");
    }

    public function scopePaid($query)
    {
        return $query->where("is_paid", true);
    }

    public function scopeUnpaid($query)
    {
        return $query->where("is_paid", false);
    }

    public function scopeNoProforma($query)
    {
        return $query->whereNull("proforma_id");
    }

    public function scopeNoInvoice($query)
    {
        return $query->whereNull("invoice_id");
    }

    public function scopeExpired($query)
    {
        return $query->where(
            "expires_on",
            "<",
            Carbon::now()->toDateTimeString()
        );
    }

    public function scopeExpiresIn1Hour($query)
    {
        return $query->where(
            "expires_on",
            "<",
            Carbon::parse("- 1 hour")->toDateTimeString()
        );
    }

    public function scopeRecurring($query)
    {
        return $query->where("is_recurring", true);
    }

    public function scopeCancelled($query)
    {
        return $query->whereNotNull("cancelled_on");
    }

    public function scopeNotCancelled($query)
    {
        return $query->whereNull("cancelled_on");
    }

    public function scopeFreePlan($query)
    {
        return $query->whereHas(
            "plan",
            fn(Builder $query) => $query->where("code", "=", PLAN_FREE)
        );
    }

    public function scopePremiumPlan($query)
    {
        return $query->whereHas(
            "plan",
            fn(Builder $query) => $query->where("code", "=", PLAN_PREMIUM)
        );
    }

    public function scopeShouldBePaid($query)
    {
        return $query->where("charging_price", ">", 0);
    }

    public function scopeNoChargingPrice($query)
    {
        return $query->where("charging_price", "=", 0);
    }

    public function scopeInGracePeriod($query)
    {
        return $query->where("grace_until", ">", Carbon::now());
    }

    public function scopeOutsideGracePeriod($query)
    {
        return $query->where("grace_until", "<", Carbon::now());
    }

    public function scopeHasProforma($query)
    {
        return $query->whereNotNull("proforma_id");
    }

    /**
     * Checks if the current subscription has started.
     *
     * @return bool
     */
    public function hasStarted()
    {
        return (bool) Carbon::now()->greaterThanOrEqualTo(
            Carbon::parse($this->starts_on)
        );
    }

    /**
     * There's a proforma which is not paid
     *
     * @return bool
     */
    public function needsPayment(): bool
    {
        return !is_null($this->proforma_id) && $this->is_paid === false;
    }

    /**
     * Checks if the current subscription has expired.
     *
     * @return bool
     */
    public function hasExpired()
    {
        return (bool) Carbon::now()->greaterThan($this->expires_on);
    }

    /**
     * Checks if the current subscription is active.
     *
     * @return bool
     */
    public function isActive()
    {
        return (bool) ($this->hasStarted() && !$this->hasExpired());
    }

    public function isPaid()
    {
        return (bool) $this->is_paid;
    }

    public function isInGracePeriod()
    {
        return Carbon::now()->isBetween($this->expires_on, $this->grace_until);
    }

    public function isRecurring()
    {
        return (int) $this->is_recurring === 1;
    }

    public function isOutsideGracePeriod()
    {
        return $this->hasExpired() &&
            $this->grace_until->lessThan(Carbon::now());
    }

    public function hasFreePlan()
    {
        return $this->plan->code === PLAN_FREE;
    }

    public function hasPremiumPlan()
    {
        return $this->plan->code === PLAN_PREMIUM;
    }

    /**
     * Get the remaining days in this subscription.
     *
     * @return int
     */
    public function remainingDays()
    {
        if ($this->hasExpired()) {
            return (int) 0;
        }

        return (int) Carbon::now()->diffInDays(
            Carbon::parse($this->expires_on)
        );
    }

    /**
     * Checks if the current subscription is cancelled (expiration date is in the past & the subscription is cancelled).
     *
     * @return bool
     */
    public function isCancelled()
    {
        return (bool) $this->cancelled_on != null;
    }

    /**
     * Checks if the current subscription is pending cancellation.
     *
     * @return bool
     */
    public function isPendingCancellation()
    {
        return (bool) ($this->isCancelled() && $this->isActive());
    }

    /**
     * Cancel this subscription.
     *
     * @return self $this
     */
    public function cancel()
    {
        $this->update([
            "cancelled_on" => Carbon::now(),
        ]);

        return $this;
    }

    public function pay()
    {
        $this->update([
            "is_paid" => true,
        ]);

        return $this;
    }

    public function updateChargingPrice($amount = 0)
    {
        $this->update([
            "charging_price" => $amount,
        ]);

        return $this;
    }

    /**
     * Consume a feature, if it is 'limit' type.
     *
     * @param string $featureCode The feature code. This feature has to be 'limit' type.
     * @param float $amount The amount consumed.
     * @return bool Wether the feature was consumed successfully or not.
     */
    public function consumeFeature(string $featureCode, float $amount = 1)
    {
        $usageModel = config("plans.models.usage");

        $feature = $this->features()
            ->code($featureCode)
            ->first();

        if (!$feature || $feature->type != "limit") {
            return false;
        }

        $usage = $this->usages()
            ->code($featureCode)
            ->first();

        if (!$usage) {
            $usage = $this->usages()->save(
                new $usageModel([
                    "code" => $featureCode,
                    "used" => 0,
                ])
            );
        }

        if (
            !$feature->isUnlimited() &&
            $usage->used + $amount > $feature->limit
        ) {
            return false;
        }

        $remaining = (float) $feature->isUnlimited()
            ? -1
            : $feature->limit - ($usage->used + $amount);

        //        if ($remaining === (float) 0) {
        //            $this->update([
        //                'expires_on' => Carbon::now()
        //            ]);
        //        }

        event(
            new \Rennokki\Plans\Events\FeatureConsumed(
                $this,
                $feature,
                $amount,
                $remaining
            )
        );

        return $usage->update([
            "used" => (float) ($usage->used + $amount),
        ]);
    }

    /**
     * Reverse of the consume a feature method, if it is 'limit' type.
     *
     * @param string $featureCode The feature code. This feature has to be 'limit' type.
     * @param float $amount The amount consumed.
     * @return bool Wether the feature was consumed successfully or not.
     */
    public function unconsumeFeature(string $featureCode, float $amount = 1)
    {
        $usageModel = config("plans.models.usage");

        $feature = $this->features()
            ->code($featureCode)
            ->first();

        if (!$feature || $feature->type != "limit") {
            return false;
        }

        $usage = $this->usages()
            ->code($featureCode)
            ->first();

        if (!$usage) {
            $usage = $this->usages()->save(
                new $usageModel([
                    "code" => $featureCode,
                    "used" => 0,
                ])
            );
        }

        $used = (float) $feature->isUnlimited()
            ? ($usage->used - $amount < 0
                ? 0
                : $usage->used - $amount)
            : $usage->used - $amount;
        $remaining = (float) $feature->isUnlimited()
            ? -1
            : ($used > 0
                ? $feature->limit - $used
                : $feature->limit);

        event(
            new \Rennokki\Plans\Events\FeatureUnconsumed(
                $this,
                $feature,
                $amount,
                $remaining
            )
        );

        return $usage->update([
            "used" => $used,
        ]);
    }

    /**
     * Get the amount used for a limit.
     *
     * @param string $featureCode The feature code. This feature has to be 'limit' type.
     * @return null|float Null if doesn't exist, integer with the usage.
     */
    public function getUsageOf(string $featureCode)
    {
        $usage = $this->usages()
            ->code($featureCode)
            ->first();
        $feature = $this->features()
            ->code($featureCode)
            ->first();

        if (!$feature || $feature->type != "limit") {
            return;
        }

        if (!$usage) {
            return 0;
        }

        return (float) $usage->used;
    }

    /**
     * Get the amount remaining for a feature.
     *
     * @param string $featureCode The feature code. This feature has to be 'limit' type.
     * @return float The amount remaining.
     */
    public function getRemainingOf(string $featureCode)
    {
        $usage = $this->usages()
            ->code($featureCode)
            ->first();

        /** @var PlanFeatureModel $feature */
        $feature = $this->features()
            ->code($featureCode)
            ->first();

        if (!$feature || $feature->type != "limit") {
            return 0;
        }

        if (!$usage) {
            return (float) $feature->isUnlimited() ? -1 : $feature->limit;
        }

        return (float) $feature->isUnlimited()
            ? -1
            : $feature->limit - $usage->used;
    }

    public function getLimitOf(string $featureCode)
    {
        $feature = $this->features()
            ->code($featureCode)
            ->first();

        if (!$feature || $feature->type != "limit") {
            return 0;
        }
        return $feature->limit;
    }

    /**
     * @param  int  $proformaId
     *
     * @return static
     */
    public static function byProformaId(int $proformaId): self
    {
        return self::query()
            ->where("proforma_id", $proformaId)
            ->firstOrFail();
    }
}
