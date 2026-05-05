<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class AutomationNotifier
{
    public function send(string $subject, string $body): void
    {
        if (! config('automation.notifications.enabled')) {
            return;
        }

        $recipients = config('automation.notifications.recipients', []);
        if (! is_array($recipients) || $recipients === []) {
            return;
        }

        Mail::raw($body, function ($message) use ($recipients, $subject): void {
            $message->to($recipients)->subject($subject);
        });
    }
}
