import { Head } from '@inertiajs/react';
import EmployeeController from '@/actions/Modules/Employees/Http/Controllers/EmployeeController';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { index } from '@/routes/employees';
import { EmployeeForm } from '../../components/employee-form';
import type {
    EmployeeRecord,
    FormOptions,
} from '../../components/employee-form';

type Props = {
    employee: EmployeeRecord & { full_name: string };
    options: FormOptions;
};

export default function EmployeeEdit({ employee, options }: Props) {
    return (
        <PageBody>
            <Head title={`Edit ${employee.full_name}`} />
            <PageHeader
                title={`Edit ${employee.full_name}`}
                description={employee.employee_code ?? undefined}
            />
            <EmployeeForm
                key={employee.id}
                action={EmployeeController.update.form(employee.id)}
                employee={employee}
                options={options}
                submitLabel="Save changes"
            />
        </PageBody>
    );
}

EmployeeEdit.layout = {
    breadcrumbs: [{ title: 'Employees', href: index() }],
};
