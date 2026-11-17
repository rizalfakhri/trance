const nanoDollarsPerCent = 10_000_000;

export function nanoDollarsToCents(amount: number, rounding: 'floor' | 'ceil'): number {
    return rounding === 'floor' ? Math.floor(amount / nanoDollarsPerCent) : Math.ceil(amount / nanoDollarsPerCent);
}

export function formatCents(cents: number, currency: string): string {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency.toUpperCase() }).format(cents / 100);
}

export function formatNanoDollars(amount: number, currency: string, rounding: 'floor' | 'ceil'): string {
    return formatCents(nanoDollarsToCents(amount, rounding), currency);
}

/** Exact amount with its fractions of a cent, e.g. $0.403875. */
export function formatNanoDollarsPrecisely(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency.toUpperCase(),
        minimumFractionDigits: 2,
        maximumFractionDigits: 9,
    }).format(amount / 1_000_000_000);
}
