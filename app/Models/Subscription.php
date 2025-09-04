<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'user_email',
        'order_id',
        'plan_id',
        'subscription_name',
        'subscription_period',
        'billing_cycle',
        'subscription_start_date',
        'subscription_end_date',
        'total_amount',
        'currency',
        'payment_method',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_price_id',
        'stripe_invoice_id',
        'stripe_payment_intent_id',
        'transaction_id',
        'payment_status',
        'subscription_status',
        'auto_renew',
        'cancel_at_period_end',
        'canceled_at',
        'last_payment_status',
        'last_payment_at',
        'is_first_payment',
        'is_renewal',
        'renewed_from_subscription_id',
        'renewed_to_subscription_id',
        'trial_start_date',
        'trial_end_date',
    ];

    protected $casts = [
        'auto_renew' => 'boolean',
        'cancel_at_period_end' => 'boolean',
        'is_first_payment' => 'boolean',
        'is_renewal' => 'boolean',
        'subscription_start_date' => 'date',
        'subscription_end_date' => 'date',
        'trial_start_date' => 'date',
        'trial_end_date' => 'date',
        'canceled_at' => 'datetime',
        'last_payment_at' => 'datetime',
    ];

    public function listing()
    {
        return $this->belongsTo(\App\Models\SubscriptionListing::class, 'plan_id', 'id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function user_api()
    {
        return $this->belongsTo(\App\Models\ApiUser::class, 'user_id', 'id');
    }

    public function isActive(): bool
    {
        return $this->subscription_status === 'active' && now()->lte($this->subscription_end_date);
    }
    public function willCancel(): bool
    {
        return $this->cancel_at_period_end === true;
    }
}
