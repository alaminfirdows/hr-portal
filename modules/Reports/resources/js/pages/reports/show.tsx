import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { EmptyState } from '@/components/portal/empty-state';
import {
    applyFilters,
    FilterBar,
    FilterSelect,
} from '@/components/portal/filters';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/reports';
import type { Option } from '@/types';
import { BarChart } from '../../components/bar-chart';

type ColumnType = 'text' | 'number' | 'decimal' | 'money' | 'percent';

type Column = { key: string; label: string; type: ColumnType };

type Filter = {
    key: string;
    label: string;
    type: 'date' | 'department' | 'year';
};

type Cell = string | number | boolean | null;

type Props = {
    report: {
        key: string;
        title: string;
        description: string;
        group: string;
        filters: Filter[];
        columns: Column[];
        chart: { label: string; value: string } | null;
    };
    values: Record<string, string | number | null>;
    departments: Option<number>[];
    rows: Record<string, Cell>[];
    total_rows: number;
    export_url: string;
};

const number = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 });
const money = new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function formatCell(value: Cell | undefined, type: ColumnType): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (typeof value !== 'number') {
        return String(value);
    }

    switch (type) {
        case 'money':
            return money.format(value);
        case 'percent':
            return `${number.format(value)}%`;
        default:
            return number.format(value);
    }
}

export default function ReportShow({
    report,
    values,
    departments,
    rows,
    total_rows,
    export_url,
}: Props) {
    const current = values as Record<string, string | number | null>;
    const patch = (p: Record<string, string | number | null>) =>
        applyFilters(current, p);

    return (
        <PageBody>
            <Head title={report.title} />
            <PageHeader
                title={report.title}
                description={report.description}
                actions={
                    <Button variant="outline" asChild>
                        <a href={export_url}>
                            <Download /> Export CSV
                        </a>
                    </Button>
                }
            />

            {report.filters.length > 0 && (
                <FilterBar>
                    {report.filters.map((filter) => {
                        if (filter.type === 'department') {
                            return (
                                <FilterSelect
                                    key={filter.key}
                                    value={
                                        values.department_id as
                                            | number
                                            | null
                                            | undefined
                                    }
                                    options={departments}
                                    placeholder="Departments"
                                    onChange={(v) =>
                                        patch({ department_id: v })
                                    }
                                />
                            );
                        }

                        const name =
                            filter.type === 'year' ? 'year' : filter.key;

                        return (
                            <label
                                key={filter.key}
                                className="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                {filter.label}
                                <Input
                                    type={
                                        filter.type === 'year'
                                            ? 'number'
                                            : 'date'
                                    }
                                    className="w-full sm:w-40"
                                    defaultValue={values[name] ?? ''}
                                    key={`${name}-${values[name] ?? ''}`}
                                    onChange={(e) =>
                                        patch({ [name]: e.target.value })
                                    }
                                />
                            </label>
                        );
                    })}
                </FilterBar>
            )}

            {rows.length === 0 ? (
                <EmptyState
                    icon="bar-chart-3"
                    title="No data"
                    description="Nothing matches the selected filters."
                />
            ) : (
                <>
                    {report.chart && (
                        <BarChart
                            rows={rows}
                            labelKey={report.chart.label}
                            valueKey={report.chart.value}
                        />
                    )}

                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {report.columns.map((column) => (
                                        <TableHead
                                            key={column.key}
                                            className={
                                                column.type === 'text'
                                                    ? ''
                                                    : 'text-right'
                                            }
                                        >
                                            {column.label}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row, i) => (
                                    <TableRow key={i}>
                                        {report.columns.map((column) => (
                                            <TableCell
                                                key={column.key}
                                                className={
                                                    column.type === 'text'
                                                        ? ''
                                                        : 'text-right tabular-nums'
                                                }
                                            >
                                                {formatCell(
                                                    row[column.key],
                                                    column.type,
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {total_rows > rows.length && (
                        <p className="text-sm text-muted-foreground">
                            Showing the first {rows.length} of {total_rows}{' '}
                            rows. Export CSV for the full report.
                        </p>
                    )}
                </>
            )}
        </PageBody>
    );
}

ReportShow.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }, { title: 'Report' }],
};
