<?php

namespace Modules\Identity\Console;

use Illuminate\Console\Command;
use Modules\Identity\Models\Invitation;

final class PruneInvitationsCommand extends Command
{
    protected $signature = 'invitations:prune';

    protected $description = 'Delete accepted invitations and invitations expired more than 30 days ago';

    public function handle(): int
    {
        $count = Invitation::query()
            ->whereNotNull('accepted_at')
            ->orWhere('expires_at', '<', now()->subDays(30))
            ->delete();

        $this->info("Pruned {$count} invitations.");

        return self::SUCCESS;
    }
}
