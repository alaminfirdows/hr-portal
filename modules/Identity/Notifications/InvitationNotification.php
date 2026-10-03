<?php

namespace Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent before the account exists for real, so it is mail-only and bypasses preferences.
 */
final class InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token, public readonly ?string $inviter = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject(__('You are invited to :app', ['app' => $app]))
            ->line($this->inviter
                ? __(':name invited you to join :app.', ['name' => $this->inviter, 'app' => $app])
                : __('You are invited to join :app.', ['app' => $app]))
            ->action(__('Accept invitation'), route('invitations.accept', $this->token))
            ->line(__('This link expires in 7 days.'));
    }
}
