<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { DollarSign, ArrowRight, CreditCard } from 'lucide-vue-next'

interface Payment {
  id: number
  account_id: string
  amount: number
  payment_method: string
  reference_number: string
  description: string
  paid_at: string
  student: {
    account_id: string
    student_id: string
    name: string
  } | null
}

interface Props {
  payments: Payment[]
}

defineProps<Props>()

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

const formatDateTime = (date: string) => {
  return new Date(date).toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getPaymentMethodBadge = (method: string) => {
  const config: Record<string, { class: string; icon: string }> = {
    cash: { class: 'bg-green-100 text-green-800', icon: '💵' },
    gcash: { class: 'bg-blue-100 text-blue-800', icon: '📱' },
    bank_transfer: { class: 'bg-purple-100 text-purple-800', icon: '🏦' },
    credit_card: { class: 'bg-orange-100 text-orange-800', icon: '💳' },
    debit_card: { class: 'bg-yellow-100 text-yellow-800', icon: '💳' },
  }
  
  return config[method] || { class: 'bg-gray-100 text-gray-800', icon: '💰' }
}
</script>

<template>
  <Card>
    <CardHeader>
      <div class="flex items-center justify-between">
        <div>
          <CardTitle class="flex items-center gap-2">
            <DollarSign class="h-5 w-5 text-green-600" />
            Recent Payments
          </CardTitle>
          <CardDescription>Latest payment transactions</CardDescription>
        </div>
        <Link :href="route('accounting.transactions.index')" class="text-sm text-primary hover:underline flex items-center gap-1">
          View All
          <ArrowRight class="h-3 w-3" />
        </Link>
      </div>
    </CardHeader>
    <CardContent>
      <div v-if="payments.length > 0" class="space-y-3">
        <div
          v-for="payment in payments"
          :key="payment.id"
          class="flex items-start justify-between p-3 rounded-lg border hover:bg-muted/50 transition-colors cursor-pointer"
          @click="$inertia.visit(route('student-fees.show', payment.account_id))"
        >
          <div class="flex-1 space-y-2">
            <div>
              <p class="font-medium">{{ payment.student?.name || 'Unknown Student' }}</p>
              <p class="text-sm text-muted-foreground">
                {{ payment.reference_number }}
              </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
              <Badge :class="getPaymentMethodBadge(payment.payment_method).class">
                {{ getPaymentMethodBadge(payment.payment_method).icon }}
                {{ payment.payment_method.replace('_', ' ') }}
              </Badge>
              <span class="text-xs text-muted-foreground">
                {{ formatDateTime(payment.paid_at) }}
              </span>
            </div>
          </div>

          <div class="text-right">
            <p class="text-lg font-bold text-green-600">
              {{ formatCurrency(payment.amount) }}
            </p>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8 text-muted-foreground">
        <CreditCard class="h-8 w-8 mx-auto mb-2 opacity-50" />
        <p class="text-sm">No recent payments</p>
      </div>
    </CardContent>
  </Card>
</template>