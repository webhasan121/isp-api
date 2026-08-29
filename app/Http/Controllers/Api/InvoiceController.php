<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Staff Invoice List
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $invoices = Invoice::query()
            ->with([
                'payment',
                'customer.user',
            ])
            ->latest('issued_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Staff Single Invoice
    |--------------------------------------------------------------------------
    */

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'payment',
            'customer.user',
        ]);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Staff PDF
    |--------------------------------------------------------------------------
    */

    public function download(Invoice $invoice)
    {
        $pdf = Pdf::loadView(
            'pdf.invoice',
            [
                'invoice' => $invoice,
            ]
        );

        return $pdf->download(
            $invoice->invoice_number . '.pdf'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Customer Invoice List
    |--------------------------------------------------------------------------
    */

    public function myInvoices(Request $request)
    {
        $customer = $request->user()->customer;

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Customer profile not found.',
            ], 404);
        }

        $invoices = $customer
            ->invoices()
            ->latest('issued_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Customer Single Invoice
    |--------------------------------------------------------------------------
    */

    public function myInvoice(
        Request $request,
        Invoice $invoice
    ) {
        $customer = $request->user()->customer;

        if (
            ! $customer ||
            $invoice->customer_id !== $customer->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Customer PDF
    |--------------------------------------------------------------------------
    */

    public function myDownload(
        Request $request,
        Invoice $invoice
    ) {
        $customer = $request->user()->customer;

        if (
            ! $customer ||
            $invoice->customer_id !== $customer->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        }

        $pdf = Pdf::loadView(
            'pdf.invoice',
            [
                'invoice' => $invoice,
            ]
        );

        return $pdf->download(
            $invoice->invoice_number . '.pdf'
        );
    }
}
