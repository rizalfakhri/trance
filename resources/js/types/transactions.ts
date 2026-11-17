export interface TransactionSummary {
    available: number;
    held: number;
    topUps: number;
    spent: number;
    currency: string;
}

export interface TransactionItem {
    id: number;
    type: 'top_up' | 'credit' | 'hold' | 'release' | 'usage' | 'refund' | 'adjustment';
    label: string;
    description: string | null;
    amount: number;
    currency: string;
    isPending: boolean;
    generation: { url: string; prompt: string } | null;
    createdAt: string;
}
