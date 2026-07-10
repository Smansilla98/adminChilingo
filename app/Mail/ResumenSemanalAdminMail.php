<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResumenSemanalAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $subjectText,
        public array $payload,
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectText)
            ->view('mail.resumen-admin', $this->payload)
            ->text('mail.resumen-admin-text', $this->payload);
    }
}
