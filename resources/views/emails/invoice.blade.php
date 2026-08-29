<!DOCTYPE html>
<html>
<body>

<h2>
    Payment Successful
</h2>

<p>
    Hello
    {{ $invoice->customer_name_snapshot }},
</p>

<p>
    Your payment has been received successfully.
</p>

<p>
    <strong>Invoice:</strong>
    {{ $invoice->invoice_number }}
</p>

<p>
    <strong>Package:</strong>

    {{ $invoice->package_name_snapshot }}

    -

    {{ $invoice->speed_mbps_snapshot }}
    Mbps
</p>

<p>
    <strong>Amount:</strong>

    BDT
    {{ number_format(
        (float) $invoice->amount,
        2
    ) }}
</p>

<p>
    <strong>Valid From:</strong>

    {{ $invoice->coverage_start_at
        ->copy()
        ->timezone('Asia/Dhaka')
        ->format('d M Y') }}
</p>

<p>
    <strong>Valid Until:</strong>

    {{ $invoice->coverage_end_at
        ->copy()
        ->timezone('Asia/Dhaka')
        ->format('d M Y') }}
</p>

<p>
    Your PDF invoice is attached.
</p>

<p>
    Thank you.
</p>

</body>
</html>
