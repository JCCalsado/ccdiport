<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface Transaction {
    id: number;
    reference: string;
    user?: {
        id: number;
        name: string;
        student_id: string;
        email: string;
    };
    kind: 'charge' | 'payment';
    type: string;
    year: string;
    semester: string;
    amount: number;
    status: string;
    payment_channel?: string;
    paid_at?: string;
    created_at: string;
}

interface TermSummary {
    total_assessment: number;
    total_paid: number;
    current_balance: number;
}

interface Props {
    auth: {
        user: {
            id: number;
            name: string;
            role: string;
        };
    };
    transactionsByTerm: Record<string, Transaction[]>;
    account: {
        balance: number;
    };
    currentTerm: string;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Transaction History' },
];

const search = ref('');
const expanded = ref<Record<string, boolean>>({});
const showPastSemesters = ref(false);
const selectedTransaction = ref<Transaction | null>(null);
const showDetailsDialog = ref(false);

const isStaff = computed(() => {
    return ['admin', 'accounting'].includes(props.auth.user.role);
});

// Initialize first term as expanded
if (props.currentTerm && props.transactionsByTerm[props.currentTerm]) {
    expanded.value[props.currentTerm] = true;
}

const toggle = (key: string) => {
    expanded.value[key] = !expanded.value[key];
};

// Calculate term summary
const calculateTermSummary = (transactions: Transaction[]): TermSummary => {
    const charges = transactions
        .filter(t => t.kind === 'charge')
        .reduce((sum, t) => sum + parseFloat(String(t.amount)), 0);
    
    const payments = transactions
        .filter(t => t.kind === 'payment' && t.status === 'paid')
        .reduce((sum, t) => sum + parseFloat(String(t.amount)), 0);
    
    return {
        total_assessment: charges,
        total_paid: payments,
        current_balance: charges - payments,
    };
};

// Filter transactions based on search
const filteredTransactionsByTerm = computed(() => {
    if (!search.value) return props.transactionsByTerm;

    const searchLower = search.value.toLowerCase();
    const filtered: Record<string, Transaction[]> = {};

    Object.entries(props.transactionsByTerm).forEach(([term, transactions]) => {
        const matchingTransactions = transactions.filter(txn => 
            txn.reference.toLowerCase().includes(searchLower) ||
            txn.type.toLowerCase().includes(searchLower) ||
            txn.user?.name?.toLowerCase().includes(searchLower) ||
            txn.user?.student_id?.toLowerCase().includes(searchLower)
        );

        if (matchingTransactions.length > 0) {
            filtered[term] = matchingTransactions;
        }
    });

    return filtered;
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const downloadPDF = (termKey: string) => {
    router.get(route('transactions.download', { term: termKey }), {}, { 
        preserveScroll: true 
    });
};

const viewTransaction = (transactionId: number) => {
    router.visit(route('transactions.show', transactionId));
};

const payNow = (transaction: Transaction) => {
    router.visit(route('student.account'));
};
</script>

<template>
    <Head title="Transaction History" />

    <AppLayout>
        <div class="space-y-6 max-w-7xl mx-auto p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- HEADER -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Transaction History</h1>
                    <p class="text-gray-500">View all your financial transactions by term</p>
                </div>
            </div>

            <!-- Current Balance Card (Students only) -->
            <div v-if="!isStaff" class="p-6 rounded-xl border bg-blue-50 shadow-sm">
                <h2 class="font-semibold text-lg">Current Balance</h2>
                <p class="text-gray-500">Your outstanding balance</p>
                <p 
                    class="text-4xl font-bold mt-2"
                    :class="account.balance > 0 ? 'text-red-600' : 'text-green-600'"
                >
                    ₱{{ formatCurrency(Math.abs(account.balance)) }}
                </p>
            </div>

            <!-- Search Bar (Admin + Accounting only) -->
            <div v-if="isStaff" class="p-4 border rounded-xl shadow-sm bg-white">
                <input
                    v-model="search"
                    type="text"
                    class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                    placeholder="Search by reference, type, or student..."
                />
            </div>

            <!-- No Results -->
            <div v-if="Object.keys(filteredTransactionsByTerm).length === 0" class="text-center py-12">
                <p class="text-gray-500 text-lg">No transactions found</p>
                <p class="text-sm text-gray-400 mt-2">Try adjusting your search criteria</p>
            </div>

            <!-- TERMS -->
            <div 
                v-for="(transactions, termKey) in filteredTransactionsByTerm" 
                :key="termKey" 
                class="border rounded-xl shadow-sm bg-white overflow-hidden"
            >
                <!-- Summary Header -->
                <div
                    class="flex justify-between items-center p-5 cursor-pointer hover:bg-gray-50 transition-colors"
                    @click="toggle(termKey)"
                >
                    <div>
                        <div class="flex items-center gap-3">
                            <h2 class="font-bold text-xl">{{ termKey }}</h2>
                            <span 
                                v-if="termKey === currentTerm"
                                class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800"
                            >
                                Current
                            </span>
                        </div>
                        <p class="text-gray-500 mt-1">{{ transactions.length }} transaction{{ transactions.length !== 1 ? 's' : '' }}</p>
                    </div>

                    <!-- Summary Row -->
                    <div class="flex items-center gap-14 text-right">
                        <div>
                            <p class="text-sm text-gray-500">Total Assessment Fee</p>
                            <p class="text-red-600 font-bold">
                                ₱{{ formatCurrency(calculateTermSummary(transactions).total_assessment) }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-500">Total Paid</p>
                            <p class="text-green-600 font-bold">
                                ₱{{ formatCurrency(calculateTermSummary(transactions).total_paid) }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-500">Current Balance</p>
                            <p 
                                class="font-bold"
                                :class="calculateTermSummary(transactions).current_balance > 0 ? 'text-red-600' : 'text-green-600'"
                            >
                                ₱{{ formatCurrency(Math.abs(calculateTermSummary(transactions).current_balance)) }}
                            </p>
                        </div>

                        <!-- Download PDF for this term -->
                        <button
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg border text-sm transition-colors"
                            @click.stop="downloadPDF(termKey)"
                        >
                            Download PDF
                        </button>

                        <div>
                            <svg
                                :class="expanded[termKey] ? 'rotate-180' : ''"
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6 transition-transform"
                                fill="none" 
                                viewBox="0 0 24 24" 
                                stroke="currentColor"
                            >
                                <path 
                                    stroke-linecap="round" 
                                    stroke-linejoin="round" 
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7"
                                />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Expanded Table -->
                <div v-if="expanded[termKey]" class="p-5 border-t">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 text-sm">
                                    <th class="p-3 font-medium">Reference</th>
                                    <th v-if="isStaff" class="p-3 font-medium">Student</th>
                                    <th class="p-3 font-medium">Type</th>
                                    <th class="p-3 font-medium">Category</th>
                                    <th class="p-3 font-medium">Amount</th>
                                    <th class="p-3 font-medium">Status</th>
                                    <th class="p-3 font-medium">Date</th>
                                    <th class="p-3 font-medium">Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="t in transactions"
                                    :key="t.id"
                                    class="border-b hover:bg-gray-50 transition-colors"
                                >
                                    <td class="p-3 font-mono text-sm">{{ t.reference }}</td>
                                    <td v-if="isStaff" class="p-3 text-sm">
                                        <div>
                                            <p class="font-medium">{{ t.user?.name }}</p>
                                            <p class="text-xs text-gray-500">{{ t.user?.student_id }}</p>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <span 
                                            class="px-2 py-1 text-xs font-semibold rounded-full"
                                            :class="t.kind === 'charge' 
                                                ? 'bg-red-100 text-red-800' 
                                                : 'bg-green-100 text-green-800'"
                                        >
                                            {{ t.kind }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-sm">{{ t.type }}</td>
                                    <td 
                                        class="p-3 font-semibold"
                                        :class="t.kind === 'charge' ? 'text-red-600' : 'text-green-600'"
                                    >
                                        {{ t.kind === 'charge' ? '+' : '-' }}₱{{ formatCurrency(t.amount) }}
                                    </td>
                                    <td class="p-3">
                                        <span 
                                            class="px-2 py-1 text-xs font-semibold rounded-full"
                                            :class="{
                                                'bg-green-100 text-green-800': t.status === 'paid',
                                                'bg-yellow-100 text-yellow-800': t.status === 'pending',
                                                'bg-red-100 text-red-800': t.status === 'failed',
                                                'bg-gray-100 text-gray-800': t.status === 'cancelled'
                                            }"
                                        >
                                            {{ t.status }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-sm text-gray-600">{{ formatDate(t.created_at) }}</td>
                                    <td class="p-3 flex gap-2">
                                        <button 
                                            v-if="isStaff"
                                            @click="viewTransaction(t.id)"
                                            class="px-3 py-1 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                                        >
                                            View
                                        </button>
                                        <button 
                                            @click="downloadPDF(termKey)"
                                            class="px-3 py-1 text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors"
                                        >
                                            Download
                                        </button>
                                        <button 
                                            v-if="t.status === 'pending' && t.kind === 'charge' && !isStaff"
                                            @click="payNow(t)"
                                            class="px-3 py-1 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors"
                                        >
                                            Pay Now
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>