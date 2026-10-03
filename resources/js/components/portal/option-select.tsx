import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Option } from '@/types';

const EMPTY = '__none__';

/**
 * shadcn Select for {value,label} options. Supports an empty choice
 * (Radix forbids empty item values) and a hidden input for <Form>.
 * Controlled when `onChange` is given; otherwise `value` is the initial value.
 */
export function OptionSelect<V extends string | number>({
    id,
    name,
    value,
    options,
    onChange,
    placeholder = 'Select…',
    allowEmpty = false,
    emptyLabel = 'None',
    className,
    disabled,
}: {
    id?: string;
    name?: string;
    value: V | null | undefined;
    options: Option<V>[];
    onChange?: (value: V | null) => void;
    placeholder?: string;
    allowEmpty?: boolean;
    emptyLabel?: string;
    className?: string;
    disabled?: boolean;
}) {
    const [inner, setInner] = useState<V | null>(value ?? null);
    const selected = onChange ? (value ?? null) : inner;
    const current = selected === null ? '' : String(selected);

    const handle = (raw: string) => {
        const match = options.find((o) => String(o.value) === raw);
        const next = raw === EMPTY || !match ? null : match.value;
        setInner(next);
        onChange?.(next);
    };

    return (
        <>
            <Select
                value={
                    current === '' ? (allowEmpty ? EMPTY : undefined) : current
                }
                onValueChange={handle}
                disabled={disabled}
            >
                <SelectTrigger id={id} className={className ?? 'w-full'}>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {allowEmpty && (
                        <SelectItem value={EMPTY}>{emptyLabel}</SelectItem>
                    )}
                    {options.map((o) => (
                        <SelectItem
                            key={String(o.value)}
                            value={String(o.value)}
                        >
                            {o.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {name && <input type="hidden" name={name} value={current} />}
        </>
    );
}
