<?php

namespace Modules\Documents\Console;

use Illuminate\Console\Command;
use Modules\Documents\Models\Document;
use Modules\Documents\Notifications\DocumentExpiring;
use Modules\Identity\Models\User;

/**
 * Warns once at 30 days and once at 7 days before a document expires.
 */
final class NotifyExpiringDocuments extends Command
{
    protected $signature = 'documents:notify-expiring';

    protected $description = 'Notify owners about documents expiring in 30 and 7 days';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        Document::query()
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '>=', $today)
            ->whereDate('expires_on', '<=', $today->copy()->addDays(30))
            ->where(fn ($q) => $q->whereNull('notified_30_at')->orWhereNull('notified_7_at'))
            ->each(function (Document $document) use ($today, &$sent) {
                $left = (int) $today->diffInDays($document->expires_on->startOfDay());
                $tier = $left <= 7 ? 7 : 30;
                $column = "notified_{$tier}_at";

                if ($document->{$column} !== null) {
                    return;
                }

                // Skipping the 30-day notice once the 7-day one is due avoids a double ping.
                $document->forceFill(['notified_30_at' => $document->notified_30_at ?? now(), $column => now()])->save();

                foreach ($this->recipients($document) as $user) {
                    $user->notify(new DocumentExpiring($document, $left));
                    $sent++;
                }
            });

        $this->info("Expiry notifications sent: {$sent}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, User>
     */
    private function recipients(Document $document): array
    {
        return User::query()
            ->whereIn('id', array_filter([$document->uploaded_by, $document->employee?->user_id]))
            ->get()
            ->all();
    }
}
