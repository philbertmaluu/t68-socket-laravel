<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NssfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private string $subjectLine,
        private string $htmlBody,
        private ?string $attachmentContents = null,
        private string $attachmentName = 'attachment.pdf',
        private string $attachmentMime = 'application/pdf',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.from.address'),
                'NSSF',
            ),
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->attachmentContents === null || $this->attachmentContents === '') {
            return [];
        }

        $contents = $this->attachmentContents;
        $name = $this->attachmentName;
        $mime = $this->attachmentMime;

        return [
            Attachment::fromData(fn () => $contents, $name)
                ->withMime($mime),
        ];
    }
}
