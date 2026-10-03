import { Head } from '@inertiajs/react';
import ProjectController from '@/actions/Modules/Projects/Http/Controllers/ProjectController';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { index, show } from '@/routes/projects';
import { ProjectForm } from '../../components/project-form';
import type {
    FieldRow,
    FormOptions,
    ProjectRecord,
} from '../../components/types';

type Props = {
    project: ProjectRecord;
    options: FormOptions;
    fields: FieldRow[];
};

export default function ProjectEdit({ project, options, fields }: Props) {
    return (
        <PageBody>
            <Head title={`Edit ${project.name}`} />
            <PageHeader
                title={`Edit ${project.name}`}
                description={project.code}
            />
            <ProjectForm
                key={project.id}
                action={ProjectController.update.form(project.id)}
                project={project}
                options={options}
                fields={fields}
                cancelHref={show(project.id).url}
                submitLabel="Save changes"
            />
        </PageBody>
    );
}

ProjectEdit.layout = {
    breadcrumbs: [{ title: 'Projects', href: index() }],
};
