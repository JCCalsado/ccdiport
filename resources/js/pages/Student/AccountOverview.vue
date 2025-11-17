<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { CreditCard, DollarSign, TrendingUp, Clock, Receipt, AlertCircle } from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';

interface Transaction {
    id: number;
    reference: string;
    type: string;
    amount: number;
    status: string;
    created_at: string;
}

interface Fee {
    name: string;
    amount: number;
    category: string;
}

interface Props {
    account: {
        balance: number;
    };
    transactions: Transaction[];
    fees: Fee[];
}

const props = defineProps<Props>();
const page = usePage();

// Get URL parameters
const urlParams = computed(() => {
    if (typeof window === 'undefined') return {};
    const params = new URLSearchParams(window.location.search);
    return {
        pay: params.get('pay'),
        transaction_id: params.get('transaction_id'),
        reference: params.get('reference'),
        amount: params.get('amount'),
        category: params.get('category')
    };
});

// Auto-open payment dialog if 'pay' parameter exists
const showPaymentDialog = ref(false);

onMounted(() => {
    if (urlParams.value.pay === 'true') {
        showPaymentDialog.value = true;
    }
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('student.dashboard') },
    { title: 'My Account' },
];

const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    reference_number: '',
    paid_at: new Date().toISOString().split('T')[0],
    description: '',
});

// Populate form when dialog opens with URL params
const openPaymentDialog = () => {
    if (urlParams.value.amount && urlParams.value.reference && urlParams.value.category) {
        paymentForm.amount = urlParams.value.amount;
        paymentForm.reference_number = urlParams.value.reference;
        paymentForm.description = urlParams.value.category || 'Payment';
    }
    showPaymentDialog.value = true;
};

// Clear URL parameters after opening dialog
const clearUrlParams = () => {
    if (typeof window !== 'undefined' && urlParams.value.pay === 'true') {
        const url = new URL(window.location.href);
        url.searchParams.delete('pay');
        url.searchParams.delete('transaction_id');
        url.searchParams.delete('reference');
        url.searchParams.delete('amount');
        url.searchParams.delete('category');
        window.history.replaceState({}, '', url.toString());
    }
};

const closePaymentDialog = () => {
    showPaymentDialog.value = false;
    paymentForm.reset();
    clearUrlParams();
};

const submitPayment = () => {
    paymentForm.post(route('account.pay-now'), {
        preserveScroll: true,
        onSuccess: () => {
            closePaymentDialog();
        },
    });
};

const remainingBalance = computed(() => {
    return Math.abs(props.account?.balance || 0);
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'paid':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'failed':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

// Calculate total fees
const totalFees = computed(() => {
    return props.fees.reduce((sum, fee) => sum + fee.amount, 0);
});

// Group fees by category
const feesByCategory = computed(() => {
    const grouped: Record<string, Fee[]> = {};
    props.fees.forEach(fee => {
        if (!grouped[fee.category]) {
            grouped[fee.category] = [];
        }
        grouped[fee.category].push(fee);
    });
    return grouped;
});
</script>

<template>
    <Head title="My Account" />

    <AppLayout>
        <div class="space-y-6 w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">My Account</h1>
                    <p class="text-gray-500 mt-2">View your account balance and payment history</p>
                </div>

                <Dialog v-model:open="showPaymentDialog">
                    <DialogTrigger as-child>
                        <Button @click="openPaymentDialog" size="lg" class="flex items-center gap-2">
                            <CreditCard class="w-5 h-5" />
                            Make Payment
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-md">
                        <DialogHeader>
                            <DialogTitle>Make a Payment</DialogTitle>
                            <DialogDescription>
                                Enter payment details below
                            </DialogDescription>
                        </DialogHeader>

                        <form @submit.prevent="submitPayment" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="amount">Amount *</Label>
                                <Input
                                    id="amount"
                                    v-model="paymentForm.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    placeholder="0.00"
                                />
                                <p v-if="paymentForm.errors.amount" class="text-sm text-red-500">
                                    {{ paymentForm.errors.amount }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="payment_method">Payment Method *</Label>
                                <select
                                    id="payment_method"
                                    v-model="paymentForm.payment_method"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                </select>
                                <p v-if="paymentForm.errors.payment_method" class="text-sm text-red-500">
                                    {{ paymentForm.errors.payment_method }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="reference_number">Reference Number</Label>
                                <Input
                                    id="reference_number"
                                    v-model="paymentForm.reference_number"
                                    placeholder="Optional reference number"
                                />
                                <p v-if="paymentForm.errors.reference_number" class="text-sm text-red-500">
                                    {{ paymentForm.errors.reference_number }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="paid_at">Payment Date *</Label>
                                <Input
                                    id="paid_at"
                                    v-model="paymentForm.paid_at"
                                    type="date"
                                    required
                                />
                                <p v-if="paymentForm.errors.paid_at" class="text-sm text-red-500">
                                    {{ paymentForm.errors.paid_at }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description *</Label>
                                <Input
                                    id="description"
                                    v-model="paymentForm.description"
                                    placeholder="e.g., Prelim, Midterm, Full Payment"
                                    required
                                />
                                <p v-if="paymentForm.errors.description" class="text-sm text-red-500">
                                    {{ paymentForm.errors.description }}
                                </p>
                            </div>

                            <DialogFooter class="gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="closePaymentDialog"
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" :disabled="paymentForm.processing">
                                    {{ paymentForm.processing ? 'Processing...' : 'Submit Payment' }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Account Balance Card -->
            <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl shadow-lg p-8 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm uppercase tracking-wide mb-2">Current Balance</p>
                        <p class="text-5xl font-bold">{{ formatCurrency(remainingBalance) }}</p>
                        <p class="text-blue-100 text-sm mt-2">
                            {{ remainingBalance > 0 ? 'Outstanding balance' : 'Account is clear' }}
                        </p>
                    </div>
                    <div class="bg-white/10 rounded-full p-4">
                        <DollarSign class="w-12 h-12" />
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">Total Fees</CardTitle>
                        <Receipt class="w-4 h-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatCurrency(totalFees) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Current term assessment</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">Pending Transactions</CardTitle>
                        <Clock class="w-4 h-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ transactions.filter(t => t.status === 'pending').length }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Awaiting payment</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">Recent Payments</CardTitle>
                        <TrendingUp class="w-4 h-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ transactions.filter(t => t.status === 'paid').length }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">Completed transactions</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Fee Breakdown -->
            <Card>
                <CardHeader>
                    <CardTitle>Current Assessment Fees</CardTitle>
                    <CardDescription>Breakdown of fees for the current term</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="(categoryFees, category) in feesByCategory" :key="category" class="space-y-2">
                            <h3 class="font-semibold text-sm text-gray-700 uppercase tracking-wide">{{ category }}</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="fee in categoryFees"
                                    :key="fee.name"
                                    class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border"
                                >
                                    <span class="font-medium">{{ fee.name }}</span>
                                    <span class="text-blue-600 font-bold">{{ formatCurrency(fee.amount) }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="Object.keys(feesByCategory).length === 0" class="text-center py-8">
                            <AlertCircle class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                            <p class="text-gray-500">No fees found for the current term</p>
                        </div>

                        <div v-if="totalFees > 0" class="pt-4 border-t flex justify-between items-center">
                            <span class="font-bold text-lg">Total Assessment</span>
                            <span class="text-2xl font-bold text-blue-600">{{ formatCurrency(totalFees) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Transactions -->
            <Card>
                <CardHeader>
                    <CardTitle>Recent Transactions</CardTitle>
                    <CardDescription>Your latest payment activity</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="border-b">
                                <tr>
                                    <th class="pb-3 font-semibold text-sm">Reference</th>
                                    <th class="pb-3 font-semibold text-sm">Type</th>
                                    <th class="pb-3 font-semibold text-sm">Amount</th>
                                    <th class="pb-3 font-semibold text-sm">Status</th>
                                    <th class="pb-3 font-semibold text-sm">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-if="transactions.length === 0">
                                    <td colspan="5" class="py-8 text-center text-gray-500">
                                        No transactions found
                                    </td>
                                </tr>
                                <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-gray-50">
                                    <td class="py-3 font-mono text-sm">{{ transaction.reference }}</td>
                                    <td class="py-3 text-sm">{{ transaction.type }}</td>
                                    <td class="py-3 font-semibold text-sm">{{ formatCurrency(transaction.amount) }}</td>
                                    <td class="py-3">
                                        <span 
                                            class="px-2 py-1 text-xs font-semibold rounded-full"
                                            :class="getStatusColor(transaction.status)"
                                        >
                                            {{ transaction.status }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-sm text-gray-600">{{ formatDate(transaction.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>