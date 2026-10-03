import { Form, Head } from '@inertiajs/react';
import OrganizationSettingsController from '@/actions/Modules/Settings/Http/Controllers/OrganizationSettingsController';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/settings/organization';

type OrganizationSettings = {
    company_name: string;
    timezone: string;
    currency: string;
    fiscal_year_start_month: number;
    leave_year_start_month: number;
    working_days: number[];
    date_format: string;
};

type Props = {
    settings: OrganizationSettings;
    timezones: string[];
};

const MONTHS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
].map((label, i) => ({ value: i + 1, label }));

const DAYS = [
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
    { value: 7, label: 'Sunday' },
];

export default function OrganizationSettingsPage({
    settings,
    timezones,
}: Props) {
    const timezoneOptions = timezones.map((tz) => ({ value: tz, label: tz }));

    return (
        <PageBody>
            <Head title="Organization settings" />

            <PageHeader
                title="Organization settings"
                description="Company-wide defaults for dates, leave and working time."
            />

            <Card className="max-w-2xl">
                <CardContent>
                    <Form
                        {...OrganizationSettingsController.update.form()}
                        options={{ preserveScroll: true }}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label="Company name"
                                    htmlFor="company_name"
                                    error={errors.company_name}
                                    required
                                >
                                    <Input
                                        id="company_name"
                                        name="company_name"
                                        defaultValue={settings.company_name}
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Timezone"
                                    htmlFor="timezone"
                                    error={errors.timezone}
                                    required
                                >
                                    <OptionSelect
                                        id="timezone"
                                        name="timezone"
                                        value={settings.timezone}
                                        options={timezoneOptions}
                                    />
                                </FormField>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="Currency"
                                        htmlFor="currency"
                                        error={errors.currency}
                                        hint="3-letter code, e.g. USD."
                                        required
                                    >
                                        <Input
                                            id="currency"
                                            name="currency"
                                            defaultValue={settings.currency}
                                            maxLength={3}
                                            className="uppercase"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        label="Date format"
                                        htmlFor="date_format"
                                        error={errors.date_format}
                                        hint="PHP format, e.g. d M Y."
                                        required
                                    >
                                        <Input
                                            id="date_format"
                                            name="date_format"
                                            defaultValue={settings.date_format}
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        label="Fiscal year starts"
                                        htmlFor="fiscal_year_start_month"
                                        error={errors.fiscal_year_start_month}
                                        required
                                    >
                                        <OptionSelect
                                            id="fiscal_year_start_month"
                                            name="fiscal_year_start_month"
                                            value={
                                                settings.fiscal_year_start_month
                                            }
                                            options={MONTHS}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Leave year starts"
                                        htmlFor="leave_year_start_month"
                                        error={errors.leave_year_start_month}
                                        required
                                    >
                                        <OptionSelect
                                            id="leave_year_start_month"
                                            name="leave_year_start_month"
                                            value={
                                                settings.leave_year_start_month
                                            }
                                            options={MONTHS}
                                        />
                                    </FormField>
                                </div>
                                <fieldset className="grid gap-2">
                                    <legend className="mb-1 text-sm font-medium">
                                        Working days
                                    </legend>
                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        {DAYS.map((day) => (
                                            <div
                                                key={day.value}
                                                className="flex items-center gap-2"
                                            >
                                                <Checkbox
                                                    id={`working_day_${day.value}`}
                                                    name="working_days[]"
                                                    value={String(day.value)}
                                                    defaultChecked={settings.working_days.includes(
                                                        day.value,
                                                    )}
                                                />
                                                <Label
                                                    htmlFor={`working_day_${day.value}`}
                                                >
                                                    {day.label}
                                                </Label>
                                            </div>
                                        ))}
                                    </div>
                                    {(errors.working_days ??
                                        errors['working_days.0']) && (
                                        <p className="text-sm text-destructive">
                                            {errors.working_days ??
                                                errors['working_days.0']}
                                        </p>
                                    )}
                                </fieldset>
                                <div>
                                    <Button type="submit" disabled={processing}>
                                        Save settings
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>
        </PageBody>
    );
}

OrganizationSettingsPage.layout = {
    breadcrumbs: [{ title: 'Organization settings', href: edit() }],
};
