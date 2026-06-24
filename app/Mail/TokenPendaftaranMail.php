<?php

namespace App\Mail;

use App\Models\Rekruitmen;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TokenPendaftaranMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Rekruitmen $rekruitmen) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Token Pendaftaran Anda - ' . $this->rekruitmen->posisi_dilamar,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.rekruitmen.token-pendaftaran',
        );
    }
}