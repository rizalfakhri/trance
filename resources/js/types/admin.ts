export interface AdminProviderOption {
    name: string;
    label: string;
    logoUrl: string | null;
    darkLogoUrl: string | null;
    enabled: boolean;
}

export interface CostRow {
    generations: number;
    charged: number;
    cost: number;
    profit: number;
}

export interface AdminCostReport {
    summary: {
        charged: number;
        cost: number;
        profit: number;
        succeeded: number;
        failed: number;
        pending: number;
        inputTextTokens: number;
        inputImageTokens: number;
        outputTokens: number;
        reasoningTokens: number;
    };
    daily: Array<CostRow & { date: string; reportedCost: number | null; reportedGenerations: number | null }>;
    users: Array<CostRow & { id: number; name: string; email: string }>;
    providerUsage: { supported: boolean; error: string | null };
}
