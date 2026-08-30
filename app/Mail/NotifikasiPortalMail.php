<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotifikasiPortalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $namaPenerima,
        public string $judul,
        public string $isi,
        public string $tipe = 'info',
        public ?string $actionUrl = null,
        public ?string $actionText = 'Buka Portal',
        public ?string $nomorReferensi = null,
        public ?string $namaPerusahaan = null,
        public ?string $subjekEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->subjekEmail ?: "[Portal Flustra] {$this->judul}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifikasi-mitra',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
