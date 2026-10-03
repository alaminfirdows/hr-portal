import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import OwnProfileController from '@/actions/Modules/Employees/Http/Controllers/OwnProfileController';
import { EmptyState } from '@/components/portal/empty-state';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { StatusBadge } from '@/components/portal/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { edit } from '@/routes/employees/profile';
import { PhotoUpload } from '../../components/photo-upload';
import {
    cleanLinks,
    ContactFields,
    SkillsLinksFields,
} from '../../components/profile-fields';
import type { SelfFields } from '../../components/profile-fields';
import { STATUS_COLORS } from '../../components/status';

type Employee = SelfFields & {
    id: number;
    employee_code: string | null;
    full_name: string;
    email: string;
    photo_url: string | null;
    department: string | null;
    team: string | null;
    position: string | null;
    location: string | null;
    manager: { id: number; full_name: string } | null;
    status: string;
    status_label: string;
    joined_on: string;
    employment_type: string;
    work_type: string;
};

type Props = {
    employee: Employee | null;
};

function humanize(value: string): string {
    return value.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm">{children || '—'}</dd>
        </div>
    );
}

function OwnForm({ employee }: { employee: Employee }) {
    const [skills, setSkills] = useState<string[]>(employee.skills);
    const [links, setLinks] = useState<string[]>(employee.links);

    return (
        <Form
            {...OwnProfileController.update.form()}
            options={{ preserveScroll: true }}
            transform={(data) => ({
                ...data,
                skills,
                links: cleanLinks(links),
            })}
            className="grid gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Contact</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ContactFields values={employee} errors={errors} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Skills and links
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <SkillsLinksFields
                                skills={skills}
                                links={links}
                                onSkills={setSkills}
                                onLinks={setLinks}
                                errors={errors}
                            />
                        </CardContent>
                    </Card>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            Save profile
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function MyProfile({ employee }: Props) {
    if (employee === null) {
        return (
            <PageBody>
                <Head title="My profile" />
                <PageHeader title="My profile" />
                <EmptyState
                    icon="user-x"
                    title="No employee record linked"
                    description="Your account is not linked to an employee record. Contact HR."
                />
            </PageBody>
        );
    }

    return (
        <PageBody>
            <Head title="My profile" />
            <PageHeader
                title="My profile"
                description="Your employment details are managed by HR. You can update the rest."
            />

            <Card>
                <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <PhotoUpload
                        employeeId={employee.id}
                        name={employee.full_name}
                        url={employee.photo_url}
                        editable
                    />
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-lg font-semibold">
                                {employee.full_name}
                            </h2>
                            <StatusBadge
                                label={employee.status_label}
                                color={STATUS_COLORS[employee.status]}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {employee.email}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Employment</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Detail label="Employee code">
                            {employee.employee_code}
                        </Detail>
                        <Detail label="Position">{employee.position}</Detail>
                        <Detail label="Department">
                            {employee.department}
                        </Detail>
                        <Detail label="Team">{employee.team}</Detail>
                        <Detail label="Location">{employee.location}</Detail>
                        <Detail label="Manager">
                            {employee.manager?.full_name}
                        </Detail>
                        <Detail label="Employment type">
                            {humanize(employee.employment_type)}
                        </Detail>
                        <Detail label="Work type">
                            {humanize(employee.work_type)}
                        </Detail>
                        <Detail label="Joined">
                            {formatDate(employee.joined_on)}
                        </Detail>
                    </dl>
                </CardContent>
            </Card>

            <OwnForm key={employee.id} employee={employee} />
        </PageBody>
    );
}

MyProfile.layout = {
    breadcrumbs: [{ title: 'My profile', href: edit() }],
};
