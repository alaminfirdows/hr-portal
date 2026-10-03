import { Head } from '@inertiajs/react';
import ProjectController from '@/actions/Modules/Projects/Http/Controllers/ProjectController';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { index } from '@/routes/projects';
import { ProjectForm } from '../../components/project-form';
import type { FieldRow, FormOptions } from '../../components/types';

type Props = {
    options: FormOptions;
    fields: FieldRow[];
    nextCode: string;
};

export default function ProjectCreate({ options, fields, nextCode }: Props) {
    return (
        <PageBody>
            <Head title="New project" />
            <PageHeader
                title="New project"
                description="Set up the project, then add members."
            />
            <ProjectForm
                action={ProjectController.store.form()}
                options={options}
                fields={fields}
                nextCode={nextCode}
                cancelHref={index().url}
                submitLabel="Create project"
            />
        </PageBody>
    );
}

ProjectCreate.layout = {
    breadcrumbs: [{ title: 'Projects', href: index() }],
};
