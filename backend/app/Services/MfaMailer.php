<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class MfaMailer
{
    public function sendCode(string $recipient, string $code): void
    {
        $mailer = new PHPMailer(true);

        try {
            $mailer->isSMTP();
            $mailer->Host = (string) config('services.mfa_mailer.host');
            $mailer->Port = (int) config('services.mfa_mailer.port');
            $mailer->SMTPAuth = (bool) config('services.mfa_mailer.username');
            $mailer->Username = (string) config('services.mfa_mailer.username');
            $mailer->Password = (string) config('services.mfa_mailer.password');
            $mailer->SMTPSecure = (string) config('services.mfa_mailer.encryption');
            // Limit connection time so an unreachable mail server cannot exhaust PHP's request limit.
            $mailer->Timeout = (int) config('services.mfa_mailer.timeout');
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $mailer->setFrom((string) config('services.mfa_mailer.from.address'), (string) config('services.mfa_mailer.from.name'));
            $mailer->addAddress($recipient);
            $mailer->isHTML(true);
            $mailer->Subject = 'Your CityPulse verification code';
            $mailer->Body = '<p>Use this code to finish signing in to CityPulse:</p><p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;">' . e($code) . '</p><p>It expires in 5 minutes. If you did not try to sign in, you can ignore this email.</p>';
            $mailer->AltBody = "Use this code to finish signing in to CityPulse: {$code}\n\nIt expires in 5 minutes. If you did not try to sign in, you can ignore this email.";
            $mailer->send();
        } catch (Exception $exception) {
            report($exception);
            throw new \RuntimeException('Unable to send the verification code.');
        }
    }
}
