export type AttendanceRow = {
    id: number;
    employee_id: number;
    date: string;
    weekday: string;
    check_in: string | null;
    check_out: string | null;
    status: string;
    status_label: string;
    status_color: string;
    is_late: boolean;
    late_minutes: number;
    worked_minutes: number;
    overtime_minutes: number;
    source: string;
    source_label: string;
    note: string | null;
};

export type ShiftRow = {
    id: number | null;
    name: string;
    start_time: string;
    end_time: string;
    grace_minutes: number;
    half_day_minutes: number;
    overtime_after_minutes: number;
    is_default: boolean;
    is_active: boolean;
};

export function formatMinutes(minutes: number): string {
    if (minutes <= 0) {
        return '—';
    }

    const h = Math.floor(minutes / 60);
    const m = minutes % 60;

    return h > 0 ? `${h}h ${m.toString().padStart(2, '0')}m` : `${m}m`;
}
