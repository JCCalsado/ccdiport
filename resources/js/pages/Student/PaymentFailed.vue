<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Separator } from '@/components/ui/separator'
import { 
  XCircle, 
  RefreshCw, 
  Home, 
  AlertTriangle,
  HelpCircle,
  Phone,
  Mail,
  ArrowRight
} from 'lucide-vue-next'

interface Transaction {
  id: number
  reference: string
  amount: number
  payment_channel: string
  created_at: string
  status: string
  meta?: {
    failure_reason?: string
    gateway_response?: any
  }
}

interface Props {
  transaction: Transaction
  failure_reason?: string
  support_contact?: {
    email: string
    phone: string
  }
}

const props = defineProps<Props>()

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('en-PH', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getFailureReason = (): string => {
  return props.failure_reason || 
         props.transaction.meta?.failure_reason || 
         'Payment processing failed. Please try again.'
}

const tryAgain = () => {
  router.visit(route('student.payment.create'))
}

const goToAccount = () => {
  router.visit(route('student.account'))
}

const goToDashboard = () => {
  router.visit(route('student.dashboard'))
}

const contactSupport = (method: 'email' | 'phone') => {
  if (method === 'email') {
    window.location.href = `mailto:${props.support_contact?.email || 'accounting@ccdi.edu.ph'}`
  } else {
    window.location.href = `tel:${props.support_contact?.phone || '09181234502'}`
  }
}

// Common failure reasons and solutions
const troubleshootingSteps = [
  {
    issue: 'Insufficient Funds',
    solution: 'Ensure your payment account has sufficient balance to cover the transaction amount plus any fees.',
  },
  {
    issue: 'Invalid Card Details',
    solution: 'Double-check your card number, expiry date, and CVV. Make sure they are entered correctly.',
  },
  {
    issue: 'Transaction Timeout',
    solution: 'The payment process may have timed out. Please try again with a stable internet connection.',
  },
  {
    issue: 'Bank Declined',
    solution: 'Your bank may have declined the transaction. Contact your bank to verify and try again.',
  },
]
</script>

<template>
  <AppLayout>
    <Head title="Payment Failed" />

    <div class="mx-auto max-w-3xl space-y-6 py-8">
      <!-- Failure Header -->
      <div class="text-center">
        <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-red-100">
          <XCircle class="h-12 w-12 text-red-600" />
        </div>
        <h1 class="text-3xl font-bold tracking-tight text-red-600">Payment Failed</h1>
        <p class="mt-2 text-lg text-muted-foreground">
          We couldn't process your payment
        </p>
      </div>

      <!-- Error Details Card -->
      <Card>
        <CardHeader>
          <CardTitle>Transaction Details</CardTitle>
          <CardDescription>
            Reference Number: <span class="font-mono font-semibold">{{ transaction.reference }}</span>
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
          <!-- Failure Reason Alert -->
          <Alert variant="destructive">
            <AlertTriangle class="h-4 w-4" />
            <AlertTitle>Payment Failed</AlertTitle>
            <AlertDescription>
              {{ getFailureReason() }}
            </AlertDescription>
          </Alert>

          <Separator />

          <!-- Transaction Information Grid -->
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <p class="text-sm text-muted-foreground">Attempted Amount</p>
              <p class="font-semibold">{{ formatCurrency(transaction.amount) }}</p>
            </div>

            <div class="space-y-1">
              <p class="text-sm text-muted-foreground">Payment Method</p>
              <p class="font-semibold capitalize">{{ transaction.payment_channel }}</p>
            </div>

            <div class="space-y-1">
              <p class="text-sm text-muted-foreground">Attempt Date</p>
              <p class="font-semibold">{{ formatDate(transaction.created_at) }}</p>
            </div>

            <div class="space-y-1">
              <p class="text-sm text-muted-foreground">Status</p>
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 rounded-full bg-red-500"></div>
                <span class="font-semibold capitalize text-red-600">{{ transaction.status }}</span>
              </div>
            </div>
          </div>

          <Separator />

          <!-- Action Buttons -->
          <div class="grid gap-3 sm:grid-cols-2">
            <Button @click="tryAgain" class="w-full" size="lg">
              <RefreshCw class="mr-2 h-4 w-4" />
              Try Again
              <ArrowRight class="ml-2 h-4 w-4" />
            </Button>

            <Button @click="goToAccount" variant="outline" class="w-full">
              View Account Balance
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Troubleshooting Card -->
      <Card>
        <CardHeader>
          <div class="flex items-center gap-2">
            <HelpCircle class="h-5 w-5" />
            <CardTitle>Troubleshooting Steps</CardTitle>
          </div>
          <CardDescription>
            Common issues and how to resolve them
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-4">
            <div
              v-for="(step, index) in troubleshootingSteps"
              :key="index"
              class="rounded-lg border p-4"
            >
              <h3 class="mb-2 font-semibold">{{ step.issue }}</h3>
              <p class="text-sm text-muted-foreground">{{ step.solution }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- What to Do Next Card -->
      <Card>
        <CardHeader>
          <CardTitle>What Should I Do?</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="space-y-4">
            <div class="flex items-start gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold">
                1
              </div>
              <div class="flex-1">
                <h3 class="font-semibold">Verify Your Payment Details</h3>
                <p class="text-sm text-muted-foreground">
                  Check that you have sufficient funds and your payment information is correct.
                </p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold">
                2
              </div>
              <div class="flex-1">
                <h3 class="font-semibold">Contact Your Bank/Payment Provider</h3>
                <p class="text-sm text-muted-foreground">
                  If the problem persists, contact your bank or payment provider to ensure the transaction isn't being blocked.
                </p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold">
                3
              </div>
              <div class="flex-1">
                <h3 class="font-semibold">Try a Different Payment Method</h3>
                <p class="text-sm text-muted-foreground">
                  Consider using an alternative payment method if available.
                </p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold">
                4
              </div>
              <div class="flex-1">
                <h3 class="font-semibold">Contact Our Support Team</h3>
                <p class="text-sm text-muted-foreground">
                  If you continue to experience issues, our support team is here to help.
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Support Contact Card -->
      <Card>
        <CardHeader>
          <CardTitle>Need Help?</CardTitle>
          <CardDescription>
            Our accounting office is here to assist you
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid gap-3 sm:grid-cols-2">
            <Button
              @click="contactSupport('email')"
              variant="outline"
              class="w-full justify-start"
            >
              <Mail class="mr-2 h-4 w-4" />
              <div class="text-left">
                <p class="text-xs text-muted-foreground">Email us at</p>
                <p class="font-semibold">{{ support_contact?.email || 'accounting@ccdi.edu.ph' }}</p>
              </div>
            </Button>

            <Button
              @click="contactSupport('phone')"
              variant="outline"
              class="w-full justify-start"
            >
              <Phone class="mr-2 h-4 w-4" />
              <div class="text-left">
                <p class="text-xs text-muted-foreground">Call us at</p>
                <p class="font-semibold">{{ support_contact?.phone || '09181234502' }}</p>
              </div>
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Alternative Actions -->
      <div class="flex justify-center">
        <Button @click="goToDashboard" variant="ghost">
          <Home class="mr-2 h-4 w-4" />
          Return to Dashboard
        </Button>
      </div>
    </div>
  </AppLayout>
</template>