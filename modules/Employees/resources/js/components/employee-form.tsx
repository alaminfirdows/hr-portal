import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Option } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';
import { cleanLinks, ContactFields, SkillsLinksFields } from './profile-fields';
import type { EmergencyContact } from './profile-fields';

export type EmployeeRecord = {
    id: number;
    employee_code: string | null;
    first_name: string;
    last_name: string;
    email: string;
    personal_email: string | null;
    phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
    department_id: number | null;
    team_id: number | null;
    position_id: number | null;
    location_id: number | null;
    manager_id: number | null;
    joined_on: string;
    employment_type: string;
    work_type: string;
    address: string | null;
    emergency_contact: EmergencyContact;
    skills: string[];
    links: string[];
};

export type TeamOption = Option<number> & { department_id: number | null };

export type FormOptions = {
    departments: Option<number>[];
    teams: TeamOption[];
    positions: Option<number>[];
    locations: Option<number>[];
    managers: Option<number>[];
    genders: Option<string>[];
    employment_types: Option<string>[];
    work_types: Option<string>[];
};

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

/** Shared create/edit form. Pass `employee` to edit. */
export function EmployeeForm({
    action,
    employee,
    options,
    roles = [],
    nextCode,
    submitLabel,
}: {
    action: RouteFormDefinition<'post'>;
    employee?: EmployeeRecord;
    options: FormOptions;
    roles?: Option<string>[];
    nextCode?: string;
    submitLabel: string;
}) {
    const editing = employee !== undefined;
    const [departmentId, setDepartmentId] = useState<number | null>(
        employee?.department_id ?? null,
    );
    const [teamId, setTeamId] = useState<number | null>(
        employee?.team_id ?? null,
    );
    const [skills, setSkills] = useState<string[]>(employee?.skills ?? []);
    const [links, setLinks] = useState<string[]>(employee?.links ?? []);
    const [invite, setInvite] = useState(true);
    const [selectedRoles, setSelectedRoles] = useState<string[]>([]);

    const teams = options.teams
        .filter(
            (t) => departmentId === null || t.department_id === departmentId,
        )
        .map(({ value, label }) => ({ value, label }));

    const emergency: EmergencyContact = employee?.emergency_contact ?? {
        name: '',
        relation: '',
        phone: '',
    };

    return (
        <Form
            {...action}
            transform={(data) => ({
                ...data,
                skills,
                links: cleanLinks(links),
                ...(editing
                    ? {}
                    : {
                          invite,
                          ...(roles.length > 0 ? { roles: selectedRoles } : {}),
                      }),
            })}
            className="grid gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <Section title="Personal">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="First name"
                                htmlFor="first_name"
                                error={errors.first_name}
                                required
                            >
                                <Input
                                    id="first_name"
                                    name="first_name"
                                    defaultValue={employee?.first_name}
                                    required
                                />
                            </FormField>
                            <FormField
                                label="Last name"
                                htmlFor="last_name"
                                error={errors.last_name}
                                required
                            >
                                <Input
                                    id="last_name"
                                    name="last_name"
                                    defaultValue={employee?.last_name}
                                    required
                                />
                            </FormField>
                            <FormField
                                label="Work email"
                                htmlFor="email"
                                error={errors.email}
                                required
                            >
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={employee?.email}
                                    required
                                />
                            </FormField>
                            <FormField
                                label="Employee code"
                                htmlFor="employee_code"
                                error={errors.employee_code}
                                hint={
                                    !editing && nextCode
                                        ? `Leave blank to use ${nextCode}.`
                                        : undefined
                                }
                            >
                                <Input
                                    id="employee_code"
                                    name="employee_code"
                                    defaultValue={employee?.employee_code ?? ''}
                                    placeholder={nextCode}
                                />
                            </FormField>
                            <FormField
                                label="Date of birth"
                                htmlFor="date_of_birth"
                                error={errors.date_of_birth}
                            >
                                <Input
                                    id="date_of_birth"
                                    name="date_of_birth"
                                    type="date"
                                    defaultValue={employee?.date_of_birth ?? ''}
                                />
                            </FormField>
                            <FormField
                                label="Gender"
                                htmlFor="gender"
                                error={errors.gender}
                            >
                                <OptionSelect
                                    id="gender"
                                    name="gender"
                                    value={employee?.gender ?? null}
                                    options={options.genders}
                                    allowEmpty
                                    emptyLabel="Not specified"
                                />
                            </FormField>
                        </div>
                    </Section>

                    <Section title="Employment">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Department"
                                htmlFor="department_id"
                                error={errors.department_id}
                            >
                                <OptionSelect
                                    id="department_id"
                                    name="department_id"
                                    value={departmentId}
                                    options={options.departments}
                                    onChange={(v) => {
                                        setDepartmentId(v);
                                        setTeamId(null);
                                    }}
                                    allowEmpty
                                    emptyLabel="No department"
                                />
                            </FormField>
                            <FormField
                                label="Team"
                                htmlFor="team_id"
                                error={errors.team_id}
                            >
                                <OptionSelect
                                    key={departmentId ?? 'all'}
                                    id="team_id"
                                    name="team_id"
                                    value={teamId}
                                    options={teams}
                                    onChange={setTeamId}
                                    allowEmpty
                                    emptyLabel="No team"
                                />
                            </FormField>
                            <FormField
                                label="Position"
                                htmlFor="position_id"
                                error={errors.position_id}
                            >
                                <OptionSelect
                                    id="position_id"
                                    name="position_id"
                                    value={employee?.position_id ?? null}
                                    options={options.positions}
                                    allowEmpty
                                    emptyLabel="No position"
                                />
                            </FormField>
                            <FormField
                                label="Location"
                                htmlFor="location_id"
                                error={errors.location_id}
                            >
                                <OptionSelect
                                    id="location_id"
                                    name="location_id"
                                    value={employee?.location_id ?? null}
                                    options={options.locations}
                                    allowEmpty
                                    emptyLabel="No location"
                                />
                            </FormField>
                            <FormField
                                label="Manager"
                                htmlFor="manager_id"
                                error={errors.manager_id}
                            >
                                <OptionSelect
                                    id="manager_id"
                                    name="manager_id"
                                    value={employee?.manager_id ?? null}
                                    options={options.managers}
                                    allowEmpty
                                    emptyLabel="No manager"
                                />
                            </FormField>
                            <FormField
                                label="Joined on"
                                htmlFor="joined_on"
                                error={errors.joined_on}
                                required
                            >
                                <Input
                                    id="joined_on"
                                    name="joined_on"
                                    type="date"
                                    defaultValue={employee?.joined_on}
                                    required
                                />
                            </FormField>
                            <FormField
                                label="Employment type"
                                htmlFor="employment_type"
                                error={errors.employment_type}
                                required
                            >
                                <OptionSelect
                                    id="employment_type"
                                    name="employment_type"
                                    value={
                                        employee?.employment_type ??
                                        options.employment_types[0]?.value ??
                                        null
                                    }
                                    options={options.employment_types}
                                />
                            </FormField>
                            <FormField
                                label="Work type"
                                htmlFor="work_type"
                                error={errors.work_type}
                                required
                            >
                                <OptionSelect
                                    id="work_type"
                                    name="work_type"
                                    value={
                                        employee?.work_type ??
                                        options.work_types[0]?.value ??
                                        null
                                    }
                                    options={options.work_types}
                                />
                            </FormField>
                        </div>
                    </Section>

                    <Section title="Contact">
                        <ContactFields
                            values={{
                                phone: employee?.phone ?? null,
                                personal_email:
                                    employee?.personal_email ?? null,
                                address: employee?.address ?? null,
                                emergency_contact: emergency,
                            }}
                            errors={errors}
                        />
                    </Section>

                    <Section title="Skills and links">
                        <SkillsLinksFields
                            skills={skills}
                            links={links}
                            onSkills={setSkills}
                            onLinks={setLinks}
                            errors={errors}
                        />
                    </Section>

                    {editing ? (
                        <Section title="Change details">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label="Reason for change"
                                    htmlFor="change_reason"
                                    error={errors.change_reason}
                                    hint="Recorded in the history timeline."
                                >
                                    <Input
                                        id="change_reason"
                                        name="change_reason"
                                        maxLength={255}
                                    />
                                </FormField>
                                <FormField
                                    label="Effective on"
                                    htmlFor="effective_on"
                                    error={errors.effective_on}
                                >
                                    <Input
                                        id="effective_on"
                                        name="effective_on"
                                        type="date"
                                    />
                                </FormField>
                            </div>
                        </Section>
                    ) : (
                        <Section title="Access">
                            <div className="grid gap-4">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="invite"
                                        checked={invite}
                                        onCheckedChange={(v) =>
                                            setInvite(v === true)
                                        }
                                    />
                                    <Label htmlFor="invite">
                                        Send invitation email
                                    </Label>
                                </div>
                                {roles.length > 0 && (
                                    <fieldset className="grid gap-2">
                                        <legend className="mb-1 text-sm font-medium">
                                            Roles
                                        </legend>
                                        {roles.map((role) => (
                                            <div
                                                key={role.value}
                                                className="flex items-center gap-2"
                                            >
                                                <Checkbox
                                                    id={`role_${role.value}`}
                                                    checked={selectedRoles.includes(
                                                        role.value,
                                                    )}
                                                    onCheckedChange={(v) =>
                                                        setSelectedRoles(
                                                            v === true
                                                                ? [
                                                                      ...selectedRoles,
                                                                      role.value,
                                                                  ]
                                                                : selectedRoles.filter(
                                                                      (r) =>
                                                                          r !==
                                                                          role.value,
                                                                  ),
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`role_${role.value}`}
                                                >
                                                    {role.label}
                                                </Label>
                                            </div>
                                        ))}
                                        {errors.roles && (
                                            <p className="text-sm text-destructive">
                                                {errors.roles}
                                            </p>
                                        )}
                                    </fieldset>
                                )}
                            </div>
                        </Section>
                    )}

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
