<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $verificationCode;
    public $expiresAt;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $verificationCode, $expiresAt = null)
    {
        $this->user = $user;
        $this->verificationCode = $verificationCode;
        $this->expiresAt = $expiresAt ?: now()->addMinutes(10);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email Address - Action Required',
            from: env('MAIL_FROM_ADDRESS', 'noreply@deyna.com'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification',
            with: [
                'user' => $this->user,
                'verificationCode' => $this->verificationCode,
                'verificationLink' => $this->generateVerificationLink(),
                'expiresAt' => $this->expiresAt,
                'companyName' => config('app.name', 'Deyna'),
                'supportEmail' => config('mail.support.address', 'support@deyna.store'),
            ]
        );
    }

    /**
     * Generate verification link
     */
    private function generateVerificationLink(): string
    {
        return url('/verify-email?' . http_build_query([
            'code' => $this->verificationCode,
            'email' => $this->user->email,
        ]));
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}