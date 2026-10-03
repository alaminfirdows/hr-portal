import type { Option } from '@/types';

export type ProjectCard = {
    id: number;
    name: string;
    code: string;
    status: string;
    status_label: string;
    status_color: string;
    visibility: string;
    visibility_label: string;
    visibility_color: string;
    can_open: boolean;
    description?: string | null;
    client?: string | null;
    priority?: string;
    priority_label?: string;
    priority_color?: string;
    manager?: string | null;
    members_count?: number;
    start_date?: string | null;
    end_date?: string | null;
};

export type ProjectRecord = ProjectCard & {
    manager_employee_id: number | null;
    budget: number | null;
};

export type FieldRow = {
    id: number;
    key: string;
    label: string;
    type: 'text' | 'number' | 'date' | 'select' | 'url';
    options: string[];
    required: boolean;
    value: string | null;
};

export type FormOptions = {
    statuses: Option<string>[];
    visibilities: Option<string>[];
    priorities: Option<string>[];
    managers: Option<number>[];
};
