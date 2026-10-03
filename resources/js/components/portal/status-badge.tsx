import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/** Server enums expose color(): gray|green|blue|amber|red|purple. */
const COLORS: Record<string, string> = {
    gray: 'bg-muted text-muted-foreground',
    green: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    blue: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    red: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    purple: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
};

export function StatusBadge({
    label,
    color = 'gray',
    className,
}: {
    label: string;
    color?: string | null;
    className?: string;
}) {
    return (
        <Badge
            variant="secondary"
            className={cn(
                'border-0 font-medium',
                COLORS[color ?? 'gray'] ?? COLORS.gray,
                className,
            )}
        >
            {label}
        </Badge>
    );
}
