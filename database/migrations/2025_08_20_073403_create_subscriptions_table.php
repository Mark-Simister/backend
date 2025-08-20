<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->string('user_email');
            $table->string('order_id')->nullable();

            $table->integer('plan_id')->nullable();
            $table->string('subscription_name');
            $table->string('subscription_period'); // e.g. "6 Months"
            $table->enum('billing_cycle', ['one_time', 'recurring']);

            $table->date('subscription_start_date');
            $table->date('subscription_end_date');

            $table->decimal('total_amount', 8, 2);
            $table->string('currency', 10)->default('usd');
            $table->string('payment_method', 50); // "Stripe"

            // Stripe / gateway IDs
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->string('stripe_invoice_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();

            $table->string('transaction_id')->nullable(); // legacy/general

            // statuses
            $table->string('payment_status', 50); // succeeded, failed, pending, etc.
            $table->enum('subscription_status', ['active','canceled','expired','cancel_scheduled','renew_pending','incomplete'])->default('active');

            $table->boolean('auto_renew')->default(true);
            $table->boolean('cancel_at_period_end')->default(false);
            $table->dateTime('canceled_at')->nullable();
            $table->string('last_payment_status', 50)->nullable();
            $table->dateTime('last_payment_at')->nullable();

            // renewal tracking
            $table->boolean('is_first_payment')->default(true);
            $table->boolean('is_renewal')->default(false);
            $table->unsignedBigInteger('renewed_from_subscription_id')->nullable();
            $table->unsignedBigInteger('renewed_to_subscription_id')->nullable();

            // trial (optional)
            $table->date('trial_start_date')->nullable();
            $table->date('trial_end_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'stripe_subscription_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('subscriptions');
    }
};
