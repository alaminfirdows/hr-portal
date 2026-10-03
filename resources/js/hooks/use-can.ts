import { usePage } from '@inertiajs/react';
import type { Scope } from '@/types';

const RANK: Record<Scope, number> = { own: 1, team: 2, department: 3, all: 4 };

/**
 * UI-only permission check against the shared `auth.permissions` map.
 * The server always re-checks; use this to hide controls.
 */
export function useCan(): (key: string, atLeast?: Scope) => boolean {
    const { auth } = usePage().props;

    return (key, atLeast) => {
        if (auth.is_super) {
            return true;
        }

        const scope = auth.permissions[key];

        if (!scope) {
            return false;
        }

        return atLeast ? RANK[scope] >= RANK[atLeast] : true;
    };
}
