<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GraduationAccountNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $studentName,
        public CarbonInterface $suspendOn,
        public int $daysUntilSuspend,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action required: back up your school account before suspension',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.graduation-account-notice',
        );
    }
}
