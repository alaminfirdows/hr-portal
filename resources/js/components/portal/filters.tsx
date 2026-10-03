import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { OptionSelect } from '@/components/portal/option-select';
import type { Option } from '@/types';

type FilterValue = string | number | null | undefined;

/**
 * Applies query-string filters to the current URL with a partial reload.
 * Empty values are dropped; page resets to 1.
 */
export function applyFilters(
    current: Record<string, FilterValue>,
    patch: Record<string, FilterValue>,
): void {
    const merged: Record<string, string | number> = {};

    for (const [key, value] of Object.entries({ ...current, ...patch })) {
        if (
            value !== null &&
            value !== undefined &&
            value !== '' &&
            key !== 'page'
        ) {
            merged[key] = value;
        }
    }

    router.get(window.location.pathname, merged, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

export function SearchInput({
    value,
    onSearch,
    placeholder = 'Search…',
}: {
    value: string | null | undefined;
    onSearch: (value: string) => void;
    placeholder?: string;
}) {
    const [term, setTerm] = useState(value ?? '');
    const callback = useRef(onSearch);
    const first = useRef(true);

    useEffect(() => {
        callback.current = onSearch;
    });

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const id = window.setTimeout(() => callback.current(term), 300);

        return () => window.clearTimeout(id);
    }, [term]);

    return (
        <div className="relative w-full sm:w-64">
            <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
            <Input
                value={term}
                onChange={(e) => setTerm(e.target.value)}
                placeholder={placeholder}
                className="pl-8"
                aria-label={placeholder}
            />
        </div>
    );
}

export function FilterSelect<V extends string | number>({
    value,
    options,
    placeholder,
    onChange,
}: {
    value: V | null | undefined;
    options: Option<V>[];
    placeholder: string;
    onChange: (value: V | null) => void;
}) {
    return (
        <OptionSelect
            value={value ?? null}
            options={options}
            placeholder={placeholder}
            onChange={onChange}
            allowEmpty
            emptyLabel={`All ${placeholder.toLowerCase()}`}
            className="w-full sm:w-48"
        />
    );
}

export function FilterBar({
    children,
    onReset,
    active,
}: {
    children: React.ReactNode;
    onReset?: () => void;
    active?: boolean;
}) {
    return (
        <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            {children}
            {onReset && active && (
                <Button variant="ghost" size="sm" onClick={onReset}>
                    <X className="size-4" /> Reset
                </Button>
            )}
        </div>
    );
}
