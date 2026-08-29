<?php

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject:
                'Payment Invoice - ' .
                $this->invoice->invoice_number
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice'
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                function () {
                    return Pdf::loadView(
                        'pdf.invoice',
                        [
                            'invoice' => $this->invoice,
                        ]
                    )->output();
                },
                $this->invoice->invoice_number . '.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
