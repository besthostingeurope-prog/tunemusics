<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ArtistAnnouncement extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public string $recipientName,
        public bool $isHtml = false,
    ) {
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('emails.artist-announcement')
            ->with([
                'bodyText' => $this->bodyText,
                'recipientName' => $this->recipientName,
                'isHtml' => $this->isHtml,
            ]);
    }
}
