<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResumenSemanalAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $subjectText
     * @param  string  $bodyText
     */
    public function __construct(
        public string $subjectText,
        public string $bodyText,
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectText)
            ->text('mail.resumen-semanal-admin-text', [
                'bodyText' => $this->bodyText,
            ]);
    }
}

