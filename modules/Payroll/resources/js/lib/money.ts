/** Formats integer minor units (cents) for display in the organization currency. */
export function formatMoney(
    minor: number | null | undefined,
    currency: string,
): string {
    if (minor === null || minor === undefined) {
        return '—';
    }

    const amount = minor / 100;

    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
        }).format(amount);
    } catch {
        return `${currency} ${amount.toFixed(2)}`;
    }
}
