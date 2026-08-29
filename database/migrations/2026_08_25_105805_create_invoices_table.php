<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_number')
                ->nullable()
                ->unique();

            $table->foreignId('payment_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * Snapshot data
             */
            $table->string('customer_code_snapshot')
                ->nullable();

            $table->string('customer_name_snapshot');

            $table->string('customer_email_snapshot')
                ->nullable();

            $table->string('customer_phone_snapshot')
                ->nullable();

            $table->string('package_name_snapshot');

            $table->unsignedInteger(
                'speed_mbps_snapshot'
            );

            $table->decimal('amount', 10, 2);

            $table->string('payment_method');

            $table->string('transaction_id')
                ->nullable();

            $table->dateTime('coverage_start_at');

            $table->dateTime('coverage_end_at');

            $table->dateTime('issued_at');

            $table->enum('status', [
                'issued',
                'cancelled',
            ])->default('issued');

            $table->timestamps();

            $table->index([
                'customer_id',
                'issued_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
