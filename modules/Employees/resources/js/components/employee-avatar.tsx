import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { initials } from '@/lib/format';
import { cn } from '@/lib/utils';

export function EmployeeAvatar({
    name,
    url,
    className,
}: {
    name: string;
    url: string | null | undefined;
    className?: string;
}) {
    return (
        <Avatar className={cn('size-8', className)}>
            {url && <AvatarImage src={url} alt={name} />}
            <AvatarFallback>{initials(name)}</AvatarFallback>
        </Avatar>
    );
}
