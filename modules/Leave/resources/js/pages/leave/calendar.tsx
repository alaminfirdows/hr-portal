import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { calendar } from '@/routes/leave';

type Day = {
    date: string;
    weekday: number;
    is_weekend: boolean;
    holiday: string | null;
};

type Entry = {
    id: number;
    employee: string;
    type: string;
    color: string;
    start_date: string;
    end_date: string;
    half: boolean;
};

type Props = {
    month: string;
    label: string;
    previous: string;
    next: string;
    days: Day[];
    entries: Entry[];
};

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const DOT: Record<string, string> = {
    gray: 'bg-muted-foreground',
    green: 'bg-emerald-500',
    blue: 'bg-sky-500',
    amber: 'bg-amber-500',
    red: 'bg-red-500',
    purple: 'bg-violet-500',
};

export default function LeaveCalendar({
    month,
    label,
    previous,
    next,
    days,
    entries,
}: Props) {
    const go = (target: string) =>
        router.visit(calendar({ query: { month: target } }).url, {
            preserveScroll: true,
        });

    const offset = (days[0]?.weekday ?? 1) - 1;

    return (
        <PageBody>
            <Head title="Leave calendar" />

            <PageHeader
                title="Leave calendar"
                description="Approved leave, holidays and weekends."
                actions={
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Previous month"
                            onClick={() => go(previous)}
                        >
                            <ChevronLeft />
                        </Button>
                        <span
                            className="min-w-32 text-center text-sm font-medium"
                            data-month={month}
                        >
                            {label}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Next month"
                            onClick={() => go(next)}
                        >
                            <ChevronRight />
                        </Button>
                    </div>
                }
            />

            <div className="overflow-x-auto">
                <div className="grid min-w-[44rem] grid-cols-7 gap-px overflow-hidden rounded-lg border bg-border text-sm">
                    {WEEKDAYS.map((d) => (
                        <div
                            key={d}
                            className="bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                        >
                            {d}
                        </div>
                    ))}
                    {Array.from({ length: offset }).map((_, i) => (
                        <div key={`pad-${i}`} className="bg-background" />
                    ))}
                    {days.map((day) => {
                        const people = entries.filter(
                            (e) =>
                                e.start_date <= day.date &&
                                e.end_date >= day.date,
                        );

                        return (
                            <div
                                key={day.date}
                                className={cn(
                                    'min-h-24 bg-background p-1.5',
                                    (day.is_weekend || day.holiday) &&
                                        'bg-muted/50',
                                )}
                            >
                                <div className="text-xs text-muted-foreground">
                                    {Number(day.date.slice(8))}
                                </div>
                                {day.holiday && (
                                    <div className="truncate text-xs font-medium text-amber-700 dark:text-amber-400">
                                        {day.holiday}
                                    </div>
                                )}
                                {!day.is_weekend &&
                                    people.slice(0, 3).map((e) => (
                                        <div
                                            key={e.id}
                                            className="mt-0.5 flex items-center gap-1 truncate text-xs"
                                            title={`${e.employee} · ${e.type}`}
                                        >
                                            <span
                                                className={cn(
                                                    'size-2 shrink-0 rounded-full',
                                                    DOT[e.color] ?? DOT.gray,
                                                )}
                                            />
                                            <span className="truncate">
                                                {e.employee}
                                                {e.half && ' (½)'}
                                            </span>
                                        </div>
                                    ))}
                                {!day.is_weekend && people.length > 3 && (
                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                        +{people.length - 3} more
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </PageBody>
    );
}

LeaveCalendar.layout = {
    breadcrumbs: [{ title: 'Leave calendar', href: calendar() }],
};
