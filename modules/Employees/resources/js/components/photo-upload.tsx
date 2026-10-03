import { router } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/employees/photo';
import { EmployeeAvatar } from './employee-avatar';

/** Avatar with a "change photo" button. Uploads via PhotoController. */
export function PhotoUpload({
    employeeId,
    name,
    url,
    editable,
}: {
    employeeId: number;
    name: string;
    url: string | null;
    editable: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);

    const upload = (file: File | undefined) => {
        if (!file) {
            return;
        }

        router.post(
            update.url(employeeId),
            { photo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
            },
        );
    };

    return (
        <div className="relative shrink-0">
            <EmployeeAvatar name={name} url={url} className="size-20 text-xl" />
            {editable && (
                <>
                    <input
                        ref={input}
                        type="file"
                        accept="image/*"
                        className="sr-only"
                        aria-label="Upload photo"
                        tabIndex={-1}
                        onChange={(e) => upload(e.target.files?.[0])}
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        className="absolute -right-1 -bottom-1 size-8 rounded-full border"
                        aria-label="Change photo"
                        disabled={busy}
                        onClick={() => input.current?.click()}
                    >
                        <Camera />
                    </Button>
                </>
            )}
        </div>
    );
}
