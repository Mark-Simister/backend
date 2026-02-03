<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $otp;
    public int $ttlMinutes;

    /**
     * Create a new message instance.
     */
    public function __construct(string $name, string $otp, int $ttlMinutes)
    {
        $this->name = $name;
        $this->otp = $otp;
        $this->ttlMinutes = $ttlMinutes;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Reset Your Password – OTP Code')
            ->view('emails.reset_password_otp')
            ->with([
                'name' => $this->name,
                'otp' => $this->otp,
                'ttlMinutes' => $this->ttlMinutes,
            ]);
    }
}
