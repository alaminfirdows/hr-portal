import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import EmployeeDocumentController from '@/actions/Modules/Documents/Http/Controllers/EmployeeDocumentController';
import { EmptyState } from '@/components/portal/empty-state';
import { formatDate } from '@/lib/format';

type EmployeeDocument = {
    id: number;
    title: string;
    category: string | null;
    visibility_label: string;
    expires_on: string | null;
    created_at: string;
    href: string;
};

/**
 * Documents attached to an employee. Loaded lazily from the Documents module JSON endpoint
 * (which applies its own visibility rules); the parent only renders this when `documents.view` is granted.
 */
export function EmployeeDocuments({ employeeId }: { employeeId: number }) {
    const [documents, setDocuments] = useState<EmployeeDocument[] | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const controller = new AbortController();

        fetch(EmployeeDocumentController.url(employeeId), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) =>
                response.ok
                    ? (response.json() as Promise<{
                          documents: EmployeeDocument[];
                      }>)
                    : Promise.reject(new Error(String(response.status))),
            )
            .then((body) => setDocuments(body.documents))
            .catch((error: unknown) => {
                if (!(error instanceof DOMException)) {
                    setFailed(true);
                }
            });

        return () => controller.abort();
    }, [employeeId]);

    if (failed) {
        return <EmptyState icon="file-text" title="Documents unavailable" />;
    }

    if (documents === null) {
        return <p className="text-sm text-muted-foreground">Loading…</p>;
    }

    if (documents.length === 0) {
        return <EmptyState icon="file-text" title="No documents" />;
    }

    return (
        <ul className="divide-y rounded-lg border">
            {documents.map((d) => (
                <li key={d.id}>
                    <Link
                        href={d.href}
                        className="flex items-center justify-between gap-3 p-3 hover:bg-muted/50"
                    >
                        <span>
                            <span className="block text-sm font-medium">
                                {d.title}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                {[d.category, d.visibility_label]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {d.expires_on
                                ? `Expires ${formatDate(d.expires_on)}`
                                : formatDate(d.created_at)}
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
