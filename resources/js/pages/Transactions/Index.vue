<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import TransactionDetailsDialog from '@/components/TransactionDetailsDialog.vue'
import { Download, Filter, Search, X, TrendingUp, TrendingDown, DollarSign, FileText } from 'lucide-vue-next'
import type { Transaction } from '@/types/transaction'

interface Props {
  transactionsByTerm: Record<string, Transaction[]>
  transactions: Transaction[]
  filters: {
    search?: string
    kind?: string
    status?: string
    type?: string
    year?: string
    semester?: string
    date_from?: string
    date_to?: string
  }
  filterOptions: {
    years: string[]
    semesters: string[]
    types: string[]
  }
  stats: {
    total_charges: number
    total_payments: number
    pending_charges: number
    net_balance: number
    transaction_count: number
  }
  account: {
    id: number
    balance: number
  }
  currentTerm: string
}

const props = defineProps<Props>()

// Filter state
const search = ref(props.filters.search || '')
const selectedKind = ref(props.filters.kind || '')
const selectedStatus = ref(props.filters.status || '')
const selectedType = ref(props.filters.type || '')
const selectedYear = ref(props.filters.year || '')
const selectedSemester = ref(props.filters.semester || '')
const dateFrom = ref(props.filters.date_from || '')
const dateTo = ref(props.filters.date_to || '')

// Dialog state
const selectedTransaction = ref<Transaction | null>(null)
const showDetailsDialog = ref(false)

// Computed
const hasActiveFilters = computed(() => {
  return !!(search.value || selectedKind.value || selectedStatus.value || 
            selectedType.value || selectedYear.value || selectedSemester.value ||
            dateFrom.value || dateTo.value)
})

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const formatDateTime = (date: string) => {
  return new Date(date).toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getStatusBadge = (status: string) => {
  const config = {
    paid: { class: 'bg-green-100 text-green-800', label: 'Paid' },
    pending: { class: 'bg-yellow-100 text-yellow-800', label: 'Pending' },
    failed: { class: 'bg-red-100 text-red-800', label: 'Failed' },
    cancelled: { class: 'bg-gray-100 text-gray-800', label: 'Cancelled' },
  }
  
  return config[status as keyof typeof config] || config.pending
}

const getKindBadge = (kind: string) => {
  return kind === 'charge'
    ? { class: 'bg-red-100 text-red-800', label: 'Charge' }
    : { class: 'bg-green-100 text-green-800', label: 'Payment' }
}

// Actions
const applyFilters = () => {
  router.get(route('transactions.index'), {
    search: search.value,
    kind: selectedKind.value,
    status: selectedStatus.value,
    type: selectedType.value,
    year: selectedYear.value,
    semester: selectedSemester.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const clearFilters = () => {
  search.value = ''
  selectedKind.value = ''
  selectedStatus.value = ''
  selectedType.value = ''
  selectedYear.value = ''
  selectedSemester.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  
  router.get(route('transactions.index'))
}

const viewTransaction = (transaction: Transaction) => {
  selectedTransaction.value = transaction
  showDetailsDialog.value = true
}

const downloadPdf = () => {
  window.location.href = route('transactions.download')
}
</script>

<template>
  <AppLayout>
    <Head title="Transaction History" />

    <div class="container mx-auto py-6 space-y-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Transaction History</h1>
          <p class="text-muted-foreground">View and manage all your transactions</p>
        </div>

        <Button @click="downloadPdf" variant="outline">
          <Download class="w-4 h-4 mr-2" />
          Download PDF
        </Button>
      </div>

      <!-- Stats Cards -->
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Total Charges</CardTitle>
            <TrendingUp class="h-4 w-4 text-red-600" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-red-600">{{ formatCurrency(stats.total_charges) }}</div>
            <p class="text-xs text-muted-foreground">All assessed fees</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Total Payments</CardTitle>
            <TrendingDown class="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-green-600">{{ formatCurrency(stats.total_payments) }}</div>
            <p class="text-xs text-muted-foreground">Amount paid</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Pending Charges</CardTitle>
            <DollarSign class="h-4 w-4 text-yellow-600" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-yellow-600">{{ formatCurrency(stats.pending_charges) }}</div>
            <p class="text-xs text-muted-foreground">Awaiting payment</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Net Balance</CardTitle>
            <FileText class="h-4 w-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold" :class="stats.net_balance > 0 ? 'text-red-600' : 'text-green-600'">
              {{ formatCurrency(stats.net_balance) }}
            </div>
            <p class="text-xs text-muted-foreground">{{ stats.transaction_count }} transactions</p>
          </CardContent>
        </Card>
      </div>

      <!-- Filters -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle>Filters</CardTitle>
              <CardDescription>Narrow down your transaction history</CardDescription>
            </div>
            <Button v-if="hasActiveFilters" @click="clearFilters" variant="ghost" size="sm">
              <X class="w-4 h-4 mr-2" />
              Clear Filters
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <!-- Search -->
            <div class="space-y-2">
              <label class="text-sm font-medium">Search</label>
              <div class="relative">
                <Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  v-model="search"
                  placeholder="Reference, type..."
                  class="pl-8"
                  @keyup.enter="applyFilters"
                />
              </div>
            </div>

            <!-- Kind -->
            <div class="space-y-2">
              <label class="text-sm font-medium">Transaction Type</label>
              <Select v-model="selectedKind">
                <SelectTrigger>
                  <SelectValue placeholder="All types" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All types</SelectItem>
                  <SelectItem value="charge">Charges</SelectItem>
                  <SelectItem value="payment">Payments</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Status -->
            <div class="space-y-2">
              <label class="text-sm font-medium">Status</label>
              <Select v-model="selectedStatus">
                <SelectTrigger>
                  <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All statuses</SelectItem>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="paid">Paid</SelectItem>
                  <SelectItem value="failed">Failed</SelectItem>
                  <SelectItem value="cancelled">Cancelled</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Category -->
            <div class="space-y-2">
              <label class="text-sm font-medium">Category</label>
              <Select v-model="selectedType">
                <SelectTrigger>
                  <SelectValue placeholder="All categories" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All categories</SelectItem>
                  <SelectItem v-for="type in filterOptions.types" :key="type" :value="type">
                    {{ type }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Year -->
            <div class="space-y-2">
              <label class="text-sm font-medium">Year</label>
              <Select v-model="selectedYear">
                <SelectTrigger>
                  <SelectValue placeholder="All years" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All years</SelectItem>
                  <SelectItem v-for="year in filterOptions.years" :key="year" :value="year">
                    {{ year }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Semester -->
            <div class="space-y-2">
              <label class="text-sm font-medium">Semester</label>
              <Select v-model="selectedSemester">
                <SelectTrigger>
                  <SelectValue placeholder="All semesters" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All semesters</SelectItem>
                  <SelectItem v-for="sem in filterOptions.semesters" :key="sem" :value="sem">
                    {{ sem }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Date From -->
            <div class="space-y-2">
              <label class="text-sm font-medium">Date From</label>
              <Input v-model="dateFrom" type="date" />
            </div>

            <!-- Date To -->
            <div class="space-y-2">
              <label class="text-sm font-medium">Date To</label>
              <Input v-model="dateTo" type="date" />
            </div>
          </div>

          <div class="flex justify-end mt-4">
            <Button @click="applyFilters">
              <Filter class="w-4 h-4 mr-2" />
              Apply Filters
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Transactions by Term -->
      <div class="space-y-4">
        <div v-for="(transactions, term) in transactionsByTerm" :key="term">
          <Card>
            <CardHeader>
              <div class="flex items-center justify-between">
                <div>
                  <CardTitle>{{ term }}</CardTitle>
                  <CardDescription>{{ transactions.length }} transactions</CardDescription>
                </div>
                <Badge variant="secondary">
                  {{ formatCurrency(transactions.reduce((sum, t) => sum + (t.kind === 'charge' ? t.amount : -t.amount), 0)) }}
                </Badge>
              </div>
            </CardHeader>
            <CardContent>
              <div class="space-y-2">
                <div
                  v-for="transaction in transactions"
                  :key="transaction.id"
                  class="flex items-center justify-between p-4 rounded-lg border hover:bg-muted/50 cursor-pointer transition-colors"
                  @click="viewTransaction(transaction)"
                >
                  <div class="flex-1 space-y-1">
                    <div class="flex items-center gap-2">
                      <p class="font-medium">{{ transaction.type }}</p>
                      <Badge :class="getKindBadge(transaction.kind).class">
                        {{ getKindBadge(transaction.kind).label }}
                      </Badge>
                      <Badge :class="getStatusBadge(transaction.status).class">
                        {{ getStatusBadge(transaction.status).label }}
                      </Badge>
                    </div>
                    <div class="flex items-center gap-4 text-sm text-muted-foreground">
                      <span>{{ transaction.reference }}</span>
                      <span>{{ formatDateTime(transaction.created_at) }}</span>
                      <span v-if="transaction.payment_channel">{{ transaction.payment_channel }}</span>
                    </div>
                  </div>

                  <div class="text-right">
                    <p
                      class="text-lg font-bold"
                      :class="transaction.kind === 'charge' ? 'text-red-600' : 'text-green-600'"
                    >
                      {{ transaction.kind === 'charge' ? '+' : '-' }}{{ formatCurrency(transaction.amount) }}
                    </p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Empty State -->
      <Card v-if="!Object.keys(transactionsByTerm).length">
        <CardContent class="flex flex-col items-center justify-center py-12">
          <FileText class="h-12 w-12 text-muted-foreground mb-4" />
          <h3 class="text-lg font-semibold mb-2">No transactions found</h3>
          <p class="text-sm text-muted-foreground text-center">
            {{ hasActiveFilters ? 'Try adjusting your filters' : 'No transaction history available yet' }}
          </p>
          <Button v-if="hasActiveFilters" @click="clearFilters" class="mt-4" variant="outline">
            Clear Filters
          </Button>
        </CardContent>
      </Card>
    </div>

    <!-- Transaction Details Dialog -->
    <TransactionDetailsDialog
      v-model:open="showDetailsDialog"
      :transaction="selectedTransaction"
      :show-student-info="false"
      :show-pay-now-button="false"
      :show-download-button="true"
    />
  </AppLayout>
</template>