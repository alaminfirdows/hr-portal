type Cell = string | number | boolean | null;

const MAX_BARS = 15;

/**
 * Dependency-free horizontal bar chart (CSS widths) for the first rows of a report.
 */
export function BarChart({
    rows,
    labelKey,
    valueKey,
}: {
    rows: Record<string, Cell>[];
    labelKey: string;
    valueKey: string;
}) {
    const bars = rows.slice(0, MAX_BARS).map((row, i) => ({
        id: i,
        label: String(row[labelKey] ?? ''),
        value: Number(row[valueKey] ?? 0),
    }));
    const max = Math.max(...bars.map((b) => b.value), 0);

    if (max <= 0) {
        return null;
    }

    return (
        <div
            className="space-y-2 rounded-lg border p-4"
            role="img"
            aria-label="Bar chart"
        >
            {bars.map((bar) => (
                <div
                    key={bar.id}
                    className="grid grid-cols-[8rem_1fr_4rem] items-center gap-3 text-sm sm:grid-cols-[12rem_1fr_5rem]"
                >
                    <span className="truncate" title={bar.label}>
                        {bar.label}
                    </span>
                    <div className="h-3 rounded-full bg-muted">
                        <div
                            className="h-3 rounded-full bg-primary"
                            style={{ width: `${(bar.value / max) * 100}%` }}
                        />
                    </div>
                    <span className="text-right tabular-nums">
                        {new Intl.NumberFormat(undefined, {
                            maximumFractionDigits: 2,
                        }).format(bar.value)}
                    </span>
                </div>
            ))}
        </div>
    );
}
