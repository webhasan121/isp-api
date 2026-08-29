<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->string('payment_reference')
                ->nullable()
                ->unique();

            $table->foreignId('customer_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('subscription_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('package_id')
                ->constrained('packages')
                ->restrictOnDelete();

            $table->string('package_name_snapshot');

            $table->unsignedInteger('speed_mbps_snapshot');

            $table->decimal('amount', 10, 2);

            $table->enum('payment_type', [
                'activation',
                'renewal',
            ]);

            $table->enum('payment_method', [
                'cash',
                'bkash',
                'nagad',
                'bank',
            ]);

            $table->string('transaction_id')
                ->nullable()
                ->unique();

            /*
             * এই payment কোন period cover করছে
             */
            $table->dateTime('coverage_start_at');

            $table->dateTime('coverage_end_at');

            $table->dateTime('paid_at');

            /*
             * কোন admin/operator payment receive করেছে
             */
            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('status', [
                'paid',
                'cancelled',
            ])->default('paid');

            $table->text('notes')->nullable();

            $table->dateTime('cancelled_at')
                ->nullable();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('cancellation_reason')
                ->nullable();

            $table->timestamps();

            $table->index([
                'customer_id',
                'paid_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
