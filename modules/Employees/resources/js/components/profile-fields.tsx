import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import type { KeyboardEvent } from 'react';
import { FormField } from '@/components/portal/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export type EmergencyContact = {
    name: string | null;
    relation: string | null;
    phone: string | null;
};

export type SelfFields = {
    phone: string | null;
    personal_email: string | null;
    address: string | null;
    emergency_contact: EmergencyContact;
    skills: string[];
    links: string[];
};

type Errors = Partial<Record<string, string>>;

/** Phone, personal email, address, emergency contact. Uncontrolled inputs for <Form>. */
export function ContactFields({
    values,
    errors,
}: {
    values: Pick<
        SelfFields,
        'phone' | 'personal_email' | 'address' | 'emergency_contact'
    >;
    errors: Errors;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <FormField label="Phone" htmlFor="phone" error={errors.phone}>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    defaultValue={values.phone ?? ''}
                />
            </FormField>
            <FormField
                label="Personal email"
                htmlFor="personal_email"
                error={errors.personal_email}
            >
                <Input
                    id="personal_email"
                    name="personal_email"
                    type="email"
                    defaultValue={values.personal_email ?? ''}
                />
            </FormField>
            <FormField
                label="Address"
                htmlFor="address"
                error={errors.address}
                className="sm:col-span-2"
            >
                <Textarea
                    id="address"
                    name="address"
                    defaultValue={values.address ?? ''}
                />
            </FormField>
            <FormField
                label="Emergency contact name"
                htmlFor="ec_name"
                error={errors['emergency_contact.name']}
            >
                <Input
                    id="ec_name"
                    name="emergency_contact[name]"
                    defaultValue={values.emergency_contact.name ?? ''}
                />
            </FormField>
            <FormField
                label="Relation"
                htmlFor="ec_relation"
                error={errors['emergency_contact.relation']}
            >
                <Input
                    id="ec_relation"
                    name="emergency_contact[relation]"
                    defaultValue={values.emergency_contact.relation ?? ''}
                />
            </FormField>
            <FormField
                label="Emergency contact phone"
                htmlFor="ec_phone"
                error={errors['emergency_contact.phone']}
            >
                <Input
                    id="ec_phone"
                    name="emergency_contact[phone]"
                    type="tel"
                    defaultValue={values.emergency_contact.phone ?? ''}
                />
            </FormField>
        </div>
    );
}

/** Controlled skills tags and links list. Parent sends them via <Form transform>. */
export function SkillsLinksFields({
    skills,
    links,
    onSkills,
    onLinks,
    errors,
}: {
    skills: string[];
    links: string[];
    onSkills: (next: string[]) => void;
    onLinks: (next: string[]) => void;
    errors: Errors;
}) {
    const [draft, setDraft] = useState('');

    const addSkills = (raw: string) => {
        const incoming = raw
            .split(',')
            .map((s) => s.trim())
            .filter((s) => s !== '' && !skills.includes(s));

        if (incoming.length > 0) {
            onSkills([...skills, ...incoming]);
        }

        setDraft('');
    };

    const onKey = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addSkills(draft);
        } else if (e.key === 'Backspace' && draft === '' && skills.length > 0) {
            onSkills(skills.slice(0, -1));
        }
    };

    const skillError =
        errors.skills ??
        Object.entries(errors).find(([k]) => k.startsWith('skills.'))?.[1];

    return (
        <div className="grid gap-4">
            <FormField
                label="Skills"
                htmlFor="skills_input"
                error={skillError}
                hint="Press Enter or comma to add."
            >
                <div className="flex flex-wrap gap-1.5">
                    {skills.map((skill) => (
                        <Badge
                            key={skill}
                            variant="secondary"
                            className="gap-1"
                        >
                            {skill}
                            <button
                                type="button"
                                aria-label={`Remove ${skill}`}
                                onClick={() =>
                                    onSkills(skills.filter((s) => s !== skill))
                                }
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
                <Input
                    id="skills_input"
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={onKey}
                    onBlur={() => addSkills(draft)}
                    placeholder="e.g. Laravel, Figma"
                />
            </FormField>

            <div className="grid gap-2">
                <span className="text-sm font-medium">Links</span>
                {links.map((link, i) => (
                    <FormField
                        key={i}
                        label={`Link ${i + 1}`}
                        htmlFor={`link_${i}`}
                        error={errors[`links.${i}`]}
                    >
                        <div className="flex gap-2">
                            <Input
                                id={`link_${i}`}
                                type="url"
                                value={link}
                                placeholder="https://"
                                onChange={(e) =>
                                    onLinks(
                                        links.map((l, j) =>
                                            j === i ? e.target.value : l,
                                        ),
                                    )
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Remove link ${i + 1}`}
                                onClick={() =>
                                    onLinks(links.filter((_, j) => j !== i))
                                }
                            >
                                <X />
                            </Button>
                        </div>
                    </FormField>
                ))}
                {links.length < 10 && (
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onLinks([...links, ''])}
                        >
                            <Plus /> Add link
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}

/** Drops blank link rows before submit. */
export function cleanLinks(links: string[]): string[] {
    return links.map((l) => l.trim()).filter((l) => l !== '');
}
