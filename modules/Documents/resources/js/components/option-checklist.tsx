import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { Option } from '@/types';

/**
 * Multi-select as a scrollable checklist. Posts `name[]` hidden inputs so it works inside <Form>.
 */
export function OptionChecklist({
    name,
    options,
    initial = [],
}: {
    name: string;
    options: Option<number>[];
    initial?: number[];
}) {
    const [selected, setSelected] = useState<number[]>(initial);

    const toggle = (value: number, checked: boolean) =>
        setSelected((current) =>
            checked ? [...current, value] : current.filter((v) => v !== value),
        );

    return (
        <div className="max-h-48 overflow-y-auto rounded-md border p-2">
            {options.length === 0 && (
                <p className="p-1 text-sm text-muted-foreground">
                    Nobody to choose from.
                </p>
            )}
            {options.map((option) => (
                <div
                    key={option.value}
                    className="flex items-center gap-2 rounded px-1 py-1.5 hover:bg-muted/50"
                >
                    <Checkbox
                        id={`${name}-${option.value}`}
                        checked={selected.includes(option.value)}
                        onCheckedChange={(checked) =>
                            toggle(option.value, checked === true)
                        }
                    />
                    <Label
                        htmlFor={`${name}-${option.value}`}
                        className="flex-1 cursor-pointer font-normal"
                    >
                        {option.label}
                    </Label>
                </div>
            ))}
            {selected.map((value) => (
                <input
                    key={value}
                    type="hidden"
                    name={`${name}[]`}
                    value={value}
                />
            ))}
        </div>
    );
}
