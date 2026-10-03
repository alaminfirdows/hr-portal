<?php

namespace Modules\AccessControl\Console;

use Illuminate\Console\Command;
use Modules\AccessControl\Actions\SyncPermissions;

final class SyncPermissionsCommand extends Command
{
    protected $signature = 'access:sync';

    protected $description = 'Sync permission keys declared by modules into the database';

    public function handle(SyncPermissions $sync): int
    {
        $result = $sync->handle();

        $this->info("Permissions synced: {$result['created']} created, {$result['removed']} removed.");

        return self::SUCCESS;
    }
}
