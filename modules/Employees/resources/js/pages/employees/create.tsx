import { Head } from '@inertiajs/react';
import EmployeeController from '@/actions/Modules/Employees/Http/Controllers/EmployeeController';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { index } from '@/routes/employees';
import type { Option } from '@/types';
import { EmployeeForm } from '../../components/employee-form';
import type { FormOptions } from '../../components/employee-form';

type Props = {
    options: FormOptions;
    roles: Option<string>[];
    nextCode: string;
};

export default function EmployeeCreate({ options, roles, nextCode }: Props) {
    return (
        <PageBody>
            <Head title="New employee" />
            <PageHeader
                title="New employee"
                description="Create an employee record and optionally invite them to the portal."
            />
            <EmployeeForm
                action={EmployeeController.store.form()}
                options={options}
                roles={roles}
                nextCode={nextCode}
                submitLabel="Create employee"
            />
        </PageBody>
    );
}

EmployeeCreate.layout = {
    breadcrumbs: [{ title: 'Employees', href: index() }],
};
