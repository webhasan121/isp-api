<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            /*
             * বর্তমানে যে package চলছে।
             */
            $table->foreignId('package_id')
                ->constrained('packages')
                ->restrictOnDelete();

            /*
             * Customer package change request করলে
             * next cycle-এর package এখানে থাকবে।
             */
            $table->foreignId('next_package_id')
                ->nullable()
                ->constrained('packages')
                ->nullOnDelete();

            /*
             * Current active period.
             *
             * First payment-এর আগে এগুলো null থাকবে।
             */
            $table->timestamp('period_start_at')
                ->nullable();

            $table->timestamp('period_end_at')
                ->nullable();

            /*
             * Customer কত তারিখ পর্যন্ত টাকা দিয়ে রেখেছে।
             *
             * Advance payment না থাকলে:
             * paid_until = period_end_at
             *
             * Advance payment থাকলে:
             * paid_until > period_end_at
             */
            $table->timestamp('paid_until')
                ->nullable();

            $table->enum('status', [
                'pending',
                'active',
                'expired',
                'suspended',
                'terminated',
            ])->default('pending');

            $table->timestamp('suspended_at')
                ->nullable();

            $table->timestamp('terminated_at')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
