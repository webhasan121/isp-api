<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">

    <title>{{ $invoice->invoice_number }}</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #222;
            margin: 30px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .invoice-number {
            margin-top: 10px;
        }

        .info {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #f5f5f5;
            width: 35%;
        }

        .amount {
            text-align: right;
            margin-top: 20px;
            font-size: 18px;
        }

        .status {
            margin-top: 20px;
            font-weight: bold;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #666;
        }
    </style>
</head>

<body>

<div class="header">

    <h1>
        {{ config('app.name') }}
    </h1>

    <div>
        Internet Service Invoice
    </div>

    <div class="invoice-number">
        {{ $invoice->invoice_number }}
    </div>

</div>


<div class="info">

    <strong>Issued:</strong>

    {{ $invoice->issued_at
        ->copy()
        ->timezone('Asia/Dhaka')
        ->format('d M Y h:i A') }}

</div>


<table>

    <tr>
        <th>Customer</th>

        <td>
            {{ $invoice->customer_name_snapshot }}

            <br>

            {{ $invoice->customer_code_snapshot }}

            <br>

            {{ $invoice->customer_phone_snapshot }}
        </td>
    </tr>


    <tr>
        <th>Package</th>

        <td>
            {{ $invoice->package_name_snapshot }}

            -

            {{ $invoice->speed_mbps_snapshot }}
            Mbps
        </td>
    </tr>


    <tr>
        <th>Coverage</th>

        <td>

            {{ $invoice->coverage_start_at
                ->copy()
                ->timezone('Asia/Dhaka')
                ->format('d M Y') }}

            -

            {{ $invoice->coverage_end_at
                ->copy()
                ->timezone('Asia/Dhaka')
                ->format('d M Y') }}

        </td>
    </tr>


    <tr>
        <th>Payment Method</th>

        <td>
            {{ strtoupper(
                $invoice->payment_method
            ) }}
        </td>
    </tr>


    @if($invoice->transaction_id)

        <tr>

            <th>
                Transaction ID
            </th>

            <td>
                {{ $invoice->transaction_id }}
            </td>

        </tr>

    @endif

</table>


<div class="amount">

    Total:

    <strong>
        BDT
        {{ number_format(
            (float) $invoice->amount,
            2
        ) }}
    </strong>

</div>


<div class="status">
    Status: PAID
</div>


<div class="footer">
    Thank you for using our internet service.
</div>

</body>
</html>
