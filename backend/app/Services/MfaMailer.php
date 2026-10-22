<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MfaMailer
{
    public function sendCode(string $recipient, string $code): void
    {
        try {
            if (config('services.resend.key')) {
                $this->sendWithResend($recipient, $code);
                return;
            }

            $this->sendWithSmtp($recipient, $code);
        } catch (\Throwable $exception) {
            report($exception);
            throw new \RuntimeException('Unable to send the verification code.');
        }
    }

    private function sendWithResend(string $recipient, string $code): void
    {
        $key = (string) config('services.resend.key');
        $response = Http::timeout(10)->withToken($key)->post('https://api.resend.com/emails', [
            'from' => (string) config('services.resend.from'),
            'to' => [$recipient],
            'subject' => 'Your CityPulse verification code',
            'html' => $this->htmlBody($code),
            'text' => $this->textBody($code),
        ]);

        if (! $response->successful()) {
            Log::warning('Resend rejected an MFA verification email.', [
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);
            throw new \RuntimeException('Resend did not accept the verification email.');
        }
    }

    private function sendWithSmtp(string $recipient, string $code): void
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = (string) config('services.mfa_mailer.host');
        $mailer->Port = (int) config('services.mfa_mailer.port');
        $mailer->SMTPAuth = (bool) config('services.mfa_mailer.username');
        $mailer->Username = (string) config('services.mfa_mailer.username');
        $mailer->Password = (string) config('services.mfa_mailer.password');
        $mailer->SMTPSecure = (string) config('services.mfa_mailer.encryption');
        $mailer->Timeout = (int) config('services.mfa_mailer.timeout');
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setFrom((string) config('services.mfa_mailer.from.address'), (string) config('services.mfa_mailer.from.name'));
        $mailer->addAddress($recipient);
        $mailer->isHTML(true);
        $mailer->Subject = 'Your CityPulse verification code';
        $mailer->Body = $this->htmlBody($code);
        $mailer->AltBody = $this->textBody($code);
        $mailer->send();
    }

    private function htmlBody(string $code): string
    {
        return '<p>Use this code to finish signing in to CityPulse:</p><p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;">' . e($code) . '</p><p>It expires in 5 minutes. If you did not try to sign in, you can ignore this email.</p>';
    }

    private function textBody(string $code): string
    {
        return "Use this code to finish signing in to CityPulse: {$code}\n\nIt expires in 5 minutes. If you did not try to sign in, you can ignore this email.";
    }
}
