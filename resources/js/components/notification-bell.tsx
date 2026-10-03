import { Link, router, usePage, usePoll } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { DynamicIcon } from '@/components/portal/dynamic-icon';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, open, readAll } from '@/routes/notifications';

/** Header bell. Polls the shared `notifications` prop every 30 s. */
export function NotificationBell() {
    const { notifications } = usePage().props;

    usePoll(30_000, { only: ['notifications'] }, { keepAlive: false });

    if (!notifications) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={`Notifications (${notifications.unread} unread)`}
                >
                    <Bell className="size-5" />
                    {notifications.unread > 0 && (
                        <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 font-semibold text-white">
                            {notifications.unread > 99
                                ? '99+'
                                : notifications.unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    <span>Notifications</span>
                    {notifications.unread > 0 && (
                        <button
                            type="button"
                            className="text-xs font-normal text-primary hover:underline"
                            onClick={() =>
                                router.post(
                                    readAll.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Mark all read
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {notifications.recent.length === 0 && (
                    <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                        You are all caught up.
                    </p>
                )}
                {notifications.recent.map((n) => (
                    <DropdownMenuItem key={n.id} asChild>
                        <Link
                            href={open.url(n.id)}
                            className={cn(
                                'flex items-start gap-3 py-2',
                                !n.read_at && 'bg-muted/50',
                            )}
                        >
                            <DynamicIcon
                                name={n.icon}
                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium">
                                    {n.title}
                                </span>
                                <span className="line-clamp-2 block text-xs text-muted-foreground">
                                    {n.body}
                                </span>
                                <span className="block text-[11px] text-muted-foreground">
                                    {timeAgo(n.created_at)}
                                </span>
                            </span>
                        </Link>
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={index()} className="justify-center text-sm">
                        View all
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
