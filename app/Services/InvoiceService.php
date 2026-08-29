<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;

class InvoiceService
{
    public function createForPayment(
        Payment $payment
    ): Invoice {

        /*
         * Same payment-এর জন্য duplicate
         * invoice তৈরি হতে দিব না।
         */
        $existingInvoice = Invoice::where(
            'payment_id',
            $payment->id
        )->first();

        if ($existingInvoice) {
            return $existingInvoice;
        }

        $payment->loadMissing([
            'customer.user',
        ]);

        $customer = $payment->customer;
        $user = $customer->user;

        $invoice = Invoice::create([
            'payment_id' =>
                $payment->id,

            'customer_id' =>
                $customer->id,

            'customer_code_snapshot' =>
                $customer->customer_code,

            'customer_name_snapshot' =>
                $user->name,

            'customer_email_snapshot' =>
                $user->email,

            'customer_phone_snapshot' =>
                $user->phone,

            'package_name_snapshot' =>
                $payment->package_name_snapshot,

            'speed_mbps_snapshot' =>
                $payment->speed_mbps_snapshot,

            'amount' =>
                $payment->amount,

            'payment_method' =>
                $payment->payment_method,

            'transaction_id' =>
                $payment->transaction_id,

            'coverage_start_at' =>
                $payment->coverage_start_at,

            'coverage_end_at' =>
                $payment->coverage_end_at,

            'issued_at' =>
                $payment->paid_at,

            'status' =>
                'issued',
        ]);

        /*
         * Example:
         * INV-2026-000001
         */
        $invoice->update([
            'invoice_number' =>
                'INV-' .
                $invoice->issued_at->format('Y') .
                '-' .
                str_pad(
                    $invoice->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
        ]);

        return $invoice->fresh();
    }
}
