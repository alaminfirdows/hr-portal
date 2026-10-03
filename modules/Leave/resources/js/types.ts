export type LeaveRequestRow = {
    id: number;
    employee: { id: number; full_name: string };
    department: string | null;
    type: { id: number; name: string; color: string };
    start_date: string;
    end_date: string;
    day_part: string;
    day_part_label: string;
    days: number;
    reason: string | null;
    has_attachment: boolean;
    status: string;
    status_label: string;
    status_color: string;
    current_role: string | null;
    created_at: string | null;
};

export type BalanceRow = {
    type: {
        id: number;
        name: string;
        code: string;
        color: string;
        is_paid: boolean;
    };
    entitled: number;
    carried_forward: number;
    adjustment: number;
    used: number;
    pending: number;
    available: number | null;
};

export function formatDays(value: number | null): string {
    if (value === null) {
        return 'Unlimited';
    }

    return `${Number(value.toFixed(2))}`;
}

export function dateRange(start: string, end: string): string {
    return start === end ? start : `${start} to ${end}`;
}
