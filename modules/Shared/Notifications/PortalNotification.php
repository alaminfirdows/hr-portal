<?php

namespace Modules\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Shared\Contracts\NotificationPreferences;

/**
 * Base for every portal notification. One payload shape for the in-app list and email;
 * channels resolved from the recipient's preferences.
 */
abstract class PortalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Stable key, e.g. "leave.request_approved". Must be listed in a module's notificationTypes().
     */
    abstract public function type(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    public function url(): ?string
    {
        return null;
    }

    /**
     * Lucide icon name for the in-app list.
     */
    public function icon(): string
    {
        return 'bell';
    }

    /**
     * @return list<'in_app'|'email'>
     */
    public function defaultChannels(): array
    {
        return ['in_app', 'email'];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = app(NotificationPreferences::class)->channels($notifiable, $this->type(), $this->defaultChannels());

        return array_map(fn (string $c) => $c === 'email' ? 'mail' : 'database', $channels);
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type();
    }

    /**
     * @return array{type: string, title: string, body: string, url: string|null, icon: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type(),
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'icon' => $this->icon(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title())->line($this->body());

        if ($this->url() !== null) {
            $mail->action(__('View in portal'), $this->url());
        }

        return $mail;
    }
}
