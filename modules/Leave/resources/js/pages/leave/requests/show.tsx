import { Form, Head } from '@inertiajs/react';
import { Check, Circle, Download, X } from 'lucide-react';
import { useState } from 'react';
import LeaveApprovalController from '@/actions/Modules/Leave/Http/Controllers/LeaveApprovalController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { FormField } from '@/components/portal/form-field';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { StatusBadge } from '@/components/portal/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, timeAgo } from '@/lib/format';
import { attachment, cancel, index } from '@/routes/leave/requests';
import type { LeaveRequestRow } from '../../../types';
import { formatDays } from '../../../types';

type Step = { role: string; state: 'done' | 'current' | 'waiting' };

type Approval = {
    id: number;
    step_role: string;
    approver: string | null;
    decision: string;
    decision_label: string;
    decision_color: string;
    comment: string | null;
    created_at: string | null;
};

type Props = {
    leave: LeaveRequestRow;
    approvals: Approval[];
    steps: Step[];
    can: { act: boolean; cancel: boolean };
};

export default function LeaveRequestShow({
    leave,
    approvals,
    steps,
    can,
}: Props) {
    const [deciding, setDeciding] = useState<'approve' | 'reject' | null>(null);
    const action =
        deciding === 'reject'
            ? LeaveApprovalController.reject.form(leave.id)
            : LeaveApprovalController.approve.form(leave.id);

    return (
        <PageBody>
            <Head title={`${leave.type.name} leave`} />

            <PageHeader
                title={`${leave.type.name} · ${leave.employee.full_name}`}
                description={
                    <StatusBadge
                        label={leave.status_label}
                        color={leave.status_color}
                    />
                }
                actions={
                    <>
                        {can.act && (
                            <>
                                <Button onClick={() => setDeciding('approve')}>
                                    <Check /> Approve
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setDeciding('reject')}
                                >
                                    <X /> Reject
                                </Button>
                            </>
                        )}
                        {can.cancel && (
                            <ConfirmAction
                                trigger={
                                    <Button variant="outline">
                                        Cancel request
                                    </Button>
                                }
                                title="Cancel this leave request?"
                                description="Reserved days go back to the balance."
                                action={cancel(leave.id)}
                                confirmLabel="Cancel request"
                                destructive
                            />
                        )}
                    </>
                }
            />

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="text-base">Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">Dates</dt>
                                <dd>
                                    {formatDate(leave.start_date)}
                                    {leave.start_date !== leave.end_date &&
                                        ` – ${formatDate(leave.end_date)}`}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Days</dt>
                                <dd>
                                    {formatDays(leave.days)} (
                                    {leave.day_part_label})
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Department
                                </dt>
                                <dd>{leave.department ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Submitted
                                </dt>
                                <dd>{timeAgo(leave.created_at)}</dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-muted-foreground">
                                    Reason
                                </dt>
                                <dd className="whitespace-pre-line">
                                    {leave.reason ?? '—'}
                                </dd>
                            </div>
                            {leave.has_attachment && (
                                <div className="sm:col-span-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={attachment(leave.id).url}>
                                            <Download /> Attachment
                                        </a>
                                    </Button>
                                </div>
                            )}
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Approval</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {steps.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Approved automatically.
                            </p>
                        ) : (
                            <ol className="space-y-3">
                                {steps.map((s, i) => (
                                    <li
                                        key={i}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        {s.state === 'done' ? (
                                            <Check className="size-4 text-emerald-600" />
                                        ) : (
                                            <Circle
                                                className={
                                                    s.state === 'current'
                                                        ? 'size-4 text-amber-500'
                                                        : 'size-4 text-muted-foreground'
                                                }
                                            />
                                        )}
                                        <span
                                            className={
                                                s.state === 'waiting'
                                                    ? 'text-muted-foreground'
                                                    : undefined
                                            }
                                        >
                                            {s.role}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        )}

                        {approvals.length > 0 && (
                            <ul className="mt-4 space-y-3 border-t pt-4">
                                {approvals.map((a) => (
                                    <li key={a.id} className="text-sm">
                                        <div className="flex items-center gap-2">
                                            <StatusBadge
                                                label={a.decision_label}
                                                color={a.decision_color}
                                            />
                                            <span>
                                                {a.step_role}
                                                {a.approver &&
                                                    ` · ${a.approver}`}
                                            </span>
                                        </div>
                                        {a.comment && (
                                            <p className="mt-1 text-muted-foreground">
                                                {a.comment}
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            {timeAgo(a.created_at)}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={deciding !== null}
                onOpenChange={(o) => !o && setDeciding(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {deciding === 'reject'
                                ? 'Reject request'
                                : 'Approve request'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={deciding ?? 'none'}
                        {...action}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setDeciding(null)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label="Comment"
                                    htmlFor="comment"
                                    error={errors.comment}
                                >
                                    <Textarea
                                        id="comment"
                                        name="comment"
                                        rows={3}
                                    />
                                </FormField>
                                <DialogFooter>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        variant={
                                            deciding === 'reject'
                                                ? 'destructive'
                                                : 'default'
                                        }
                                    >
                                        {deciding === 'reject'
                                            ? 'Reject'
                                            : 'Approve'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}

LeaveRequestShow.layout = {
    breadcrumbs: [{ title: 'My leave', href: index() }],
};
