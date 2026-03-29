<?php

if (!function_exists('mailEnabled')) {
    /**
     * Check if outbound email is actually configured (not just 'log' or 'array').
     */
    function mailEnabled(): bool
    {
        $mailer = config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            return false;
        }

        // For SMTP, check that host is configured
        if ($mailer === 'smtp') {
            return !empty(config('mail.mailers.smtp.host'));
        }

        // For other drivers (ses, mailgun, postmark, etc.) assume configured
        return true;
    }
}
