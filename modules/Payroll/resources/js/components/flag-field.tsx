import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/** Checkbox that always submits 1 or 0, so "unchecked" is explicit on the server. */
export function FlagField({
    id,
    name,
    label,
    defaultChecked,
}: {
    id: string;
    name: string;
    label: string;
    defaultChecked: boolean;
}) {
    const [checked, setChecked] = useState(defaultChecked);

    return (
        <div className="flex items-center gap-2">
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(next) => setChecked(next === true)}
            />
            <Label htmlFor={id} className="font-normal">
                {label}
            </Label>
            <input type="hidden" name={name} value={checked ? '1' : '0'} />
        </div>
    );
}
