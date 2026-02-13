<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { useFormatters } from '@/composables/useFormatters'
import { AlertCircle, CheckCircle, Clock } from 'lucide-vue-next'

interface PaymentTerm {
  id: number
  term_name: string
  amount: number
  paid_amount: number
  remaining_balance: number
  due_date: string | null
  status: 'pending' | 'paid' | 'partial' | 'overdue'
  is_overdue?: boolean
  remarks?: string
  term_order?: number
}

interface Props {
  terms: PaymentTerm[]
  totalAssessment: number
}

const props = defineProps<Props>()
const { formatCurrency } = useFormatters()

// Calculate percentages for display
const termPercentages: Record<string, number> = {
  'Upon Registration': 42.15,
  'Prelim': 17.86,
  'Midterm': 17.86,
  'Semi-Final': 14.88,
  'Final': 7.26,
}

const sortedTerms = computed(() => {
  return [...props.terms].sort((a, b) => {
    const orderA = a.term_name === 'Upon Registration' ? 1 : 
                   a.term_name === 'Prelim' ? 2 : 
                   a.term_name === 'Midterm' ? 3 : 
                   a.term_name === 'Semi-Final' ? 4 : 5
    const orderB = b.term_name === 'Upon Registration' ? 1 : 
                   b.term_name === 'Prelim' ? 2 : 
                   b.term_name === 'Midterm' ? 3 : 
                   b.term_name === 'Semi-Final' ? 4 : 5
    return orderA - orderB
  })
})

const paidPercentage = computed(() => {
  if (props.totalAssessment === 0) return 0
  const totalPaid = sortedTerms.value.reduce((sum, term) => sum + term.paid_amount, 0)
  return Math.round((totalPaid / props.totalAssessment) * 100)
})

const getStatusIcon = (status: string) => {
  if (status === 'paid') return CheckCircle
  if (status === 'overdue') return AlertCircle
  return Clock
}

const getStatusColor = (status: string) => {
  if (status === 'paid') return 'text-green-600'
  if (status === 'overdue') return 'text-red-600'
  if (status === 'partial') return 'text-blue-600'
  return 'text-gray-600'
}
</script>

<template>
  <Card class="w-full">
    <CardHeader>
      <CardTitle>Payment Terms Breakdown (5-Term Structure)</CardTitle>
      <CardDescription>
        Total Assessment: {{ formatCurrency(totalAssessment) }}
      </CardDescription>
    </CardHeader>
    <CardContent class="space-y-6">
      <!-- Overall Progress -->
      <div class="space-y-2">
        <div class="flex justify-between items-center">
          <span class="text-sm font-medium">Overall Payment Progress</span>
          <span class="text-sm text-muted-foreground">{{ paidPercentage }}%</span>
        </div>
        <Progress :value="paidPercentage" class="h-2" />
      </div>

      <!-- Terms Table with Carryover Flow -->
      <div class="space-y-3">
        <div class="grid grid-cols-12 gap-2 text-xs font-semibold text-muted-foreground pb-2 border-b">
          <div class="col-span-3">Term</div>
          <div class="col-span-2 text-right">Amount</div>
          <div class="col-span-2 text-right">Percentage</div>
          <div class="col-span-2 text-right">Paid</div>
          <div class="col-span-3 text-center">Status & Carryover</div>
        </div>

        <div 
          v-for="(term, index) in sortedTerms" 
          :key="term.id"
          class="space-y-2"
        >
          <!-- Carryover From Previous Term -->
          <div 
            v-if="index > 0 && sortedTerms[index - 1].remaining_balance > 0"
            class="flex items-center gap-2 text-xs text-orange-600 px-2 py-1 bg-orange-50 rounded border-l-2 border-orange-400"
          >
            <span>↓ Carryover from {{ sortedTerms[index - 1].term_name }}: {{ formatCurrency(sortedTerms[index - 1].remaining_balance) }}</span>
          </div>

          <!-- Term Row -->
          <div 
            class="grid grid-cols-12 gap-2 items-center py-3 border-b last:border-0 hover:bg-slate-50 px-2 rounded"
          >
            <!-- Term Name -->
            <div class="col-span-3">
              <div class="font-medium text-sm">{{ term.term_name }}</div>
              <div v-if="term.due_date" class="text-xs text-muted-foreground">
                Due: {{ new Date(term.due_date).toLocaleDateString() }}
              </div>
            </div>

            <!-- Amount -->
            <div class="col-span-2 text-right">
              <span class="font-semibold">{{ formatCurrency(term.amount) }}</span>
            </div>

            <!-- Percentage -->
            <div class="col-span-2 text-right">
              <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                {{ termPercentages[term.term_name] || 0 }}%
              </span>
            </div>

            <!-- Paid Amount -->
            <div class="col-span-2 text-right">
              <div class="font-medium text-sm">{{ formatCurrency(term.paid_amount) }}</div>
              <div v-if="term.remaining_balance > 0" class="text-xs text-red-600">
                Balance: {{ formatCurrency(term.remaining_balance) }}
              </div>
            </div>

            <!-- Status Badge -->
            <div class="col-span-3 flex justify-center">
              <div :class="['flex items-center gap-1 text-xs px-2 py-1 rounded-full', getStatusColor(term.status)]">
                <component :is="getStatusIcon(term.status)" class="w-3 h-3" />
                <span class="capitalize">{{ term.status }}</span>
              </div>
            </div>
          </div>

          <!-- Carryover to Next Term -->
          <div 
            v-if="index < sortedTerms.length - 1 && term.remaining_balance > 0"
            class="flex items-center gap-2 text-xs text-orange-600 px-2 py-1 bg-orange-50 rounded border-l-2 border-orange-400"
          >
            <span>↓ Carries to {{ sortedTerms[index + 1].term_name }}: {{ formatCurrency(term.remaining_balance) }}</span>
          </div>
        </div>
      </div>

      <!-- Summary Section -->
      <div class="grid grid-cols-3 gap-4 mt-4 p-4 bg-slate-50 rounded-lg">
        <div>
          <p class="text-xs text-muted-foreground">Total Scheduled</p>
          <p class="text-lg font-semibold">{{ formatCurrency(totalAssessment) }}</p>
        </div>
        <div>
          <p class="text-xs text-muted-foreground">Total Paid</p>
          <p class="text-lg font-semibold text-green-600">
            {{ formatCurrency(sortedTerms.reduce((sum, t) => sum + t.paid_amount, 0)) }}
          </p>
        </div>
        <div>
          <p class="text-xs text-muted-foreground">Total Balance</p>
          <p class="text-lg font-semibold text-red-600">
            {{ formatCurrency(sortedTerms.reduce((sum, t) => sum + t.remaining_balance, 0)) }}
          </p>
        </div>
      </div>

      <!-- Payment Structure & Carryover Reference -->
      <div class="mt-6 space-y-3">
        <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
          <h4 class="text-sm font-semibold mb-2 text-blue-900">5-Term Payment Structure</h4>
          <div class="text-xs text-blue-800 space-y-1">
            <p>✓ Upon Registration: 42.15% (Due at enrollment)</p>
            <p>✓ Prelim: 17.86% (Due at week 6)</p>
            <p>✓ Midterm: 17.86% (Due at week 12)</p>
            <p>✓ Semi-Final: 14.88% (Due at week 15)</p>
            <p>✓ Final: 7.26% (Due at week 18)</p>
          </div>
        </div>

        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
          <h4 class="text-sm font-semibold mb-2 text-amber-900">Payment Carryover Policy</h4>
          <div class="text-xs text-amber-800 space-y-1">
            <p><strong>Automatic Carryover:</strong> Unpaid balances from earlier terms automatically carry forward to the next term.</p>
            <p><strong>Example:</strong> If "Upon Registration" has ₱421.50 but only ₱300 is paid, the remaining ₱121.50 balance carries to "Prelim".</p>
            <p><strong>Until Settled:</strong> Balances continue to carry until fully paid across all remaining terms.</p>
            <p><strong>Priority:</strong> When making payments, earlier unpaid terms are settled first.</p>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
