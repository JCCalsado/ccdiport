<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Separator } from '@/components/ui/separator'
import { 
  CheckCircle2, 
  Download, 
  Home, 
  Receipt,
  Mail,
  MessageSquare,
  ArrowRight
} from 'lucide-vue-next'
import { computed } from 'vue'

interface Transaction {
  id: number
  reference: string
  amount: number
  payment_channel: string
  paid_at: string
  status: string
}

interface Props {
  transaction: Transaction
  new_balance: number
  receipt_url?: string
  payment_method_name: string
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

const downloadReceipt = () => {
  if (props.receipt_url) {
    window.open(props.receipt_url, '_blank')
  } else {
    // Generate receipt on-the-fly
    router.get(route('student.payment.receipt', props.transaction.id))
  }
}

const goToAccount = () => {
  router.visit(route('student.account'))
}

const goToDashboard = () => {
  router.visit(route('student.dashboard'))
}
</script>

<template>
  <AppLayout>
    <Head title="Payment Successful" />

    <div class="mx-auto max-w-3xl space-y-6 py-8">
      <!-- Success Animation Header -->
      <div class="text-center">
        <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
          <CheckCircle2 class="h-12 w-12 text-green-600" />
        </div>
        <h1 class="text-3xl font-bold tracking-tight text-green-600">Payment Successful!</h1>
        <p class="mt-2 text-lg text-muted-foreground">
          Your payment has been processed successfully
        </p>
      </div>

      <!-- Transaction Details Card -->
      <Card>
        <CardHeader>
          <CardTitle>Transaction Details</CardTitle>
          <CardDescription>
            Reference Number: <span class="font-mono font-semibold">{{ transaction.reference }}</span>
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
          <!-- Amount Paid -->
          <div class="rounded-lg bg-green-50 p-6 text-center">
            <p class="text-sm text-muted-foreground">Amount Paid</p>
            <p class="text-4xl font-bold text-green-600">
              {{ formatCurrency(transaction.amount) }}
            </p>
          </div>

          <Separator />

          <!-- Payment Information Grid -->
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <p class="text-sm text-muted-foreground">Payment Method</p>
              <p class="font-semibold">{{ payment_method_name }}</p>
            </div>

            <div class="space-y-1">
              <p class="text-sm text-muted-foreground">Transaction Date</p>
              <p class="font-semibold">{{ formatDate(transaction.paid_at) }}</p>
            </div>

            <div class="space-y-1">
              <p class="text-sm text-muted-foreground">Transaction Status</p>
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 rounded-full bg-green-500"></div>
                <span class="font-semibold capitalize text-green-600">{{ transaction.status }}</span>
              </div>
            </div>

            <div class="space-y-1">
              <p class="text-sm text-muted-foreground">New Balance</p>
              <p class="font-semibold">{{ formatCurrency(new_balance) }}</p>
            </div>
          </div>

          <Separator />

          <!-- Important Information -->
          <Alert>
            <Receipt class="h-4 w-4" />
            <AlertDescription>
              <strong>Important:</strong> Please save your reference number for your records. 
              A confirmation email has been sent to your registered email address.
            </AlertDescription>
          </Alert>

          <!-- Action Buttons -->
          <div class="grid gap-3 sm:grid-cols-2">
            <Button @click="downloadReceipt" variant="outline" class="w-full">
              <Download class="mr-2 h-4 w-4" />
              Download Receipt
            </Button>

            <Button @click="goToAccount" variant="outline" class="w-full">
              <Receipt class="mr-2 h-4 w-4" />
              View Account
            </Button>
          </div>

          <Button @click="goToDashboard" class="w-full" size="lg">
            <Home class="mr-2 h-4 w-4" />
            Go to Dashboard
            <ArrowRight class="ml-2 h-4 w-4" />
          </Button>
        </CardContent>
      </Card>

      <!-- Next Steps Card -->
      <Card>
        <CardHeader>
          <CardTitle>What's Next?</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="space-y-4">
            <div class="flex items-start gap-3">
              <div class="rounded-lg bg-primary/10 p-2">
                <Mail class="h-5 w-5 text-primary" />
              </div>
              <div class="flex-1">
                <h3 class="font-semibold">Check Your Email</h3>
                <p class="text-sm text-muted-foreground">
                  A payment confirmation and official receipt have been sent to your email address.
                </p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <div class="rounded-lg bg-primary/10 p-2">
                <MessageSquare class="h-5 w-5 text-primary" />
              </div>
              <div class="flex-1">
                <h3 class="font-semibold">SMS Confirmation</h3>
                <p class="text-sm text-muted-foreground">
                  You will receive an SMS confirmation within 5 minutes.
                </p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <div class="rounded-lg bg-primary/10 p-2">
                <Receipt class="h-5 w-5 text-primary" />
              </div>
              <div class="flex-1">
                <h3 class="font-semibold">Update Balance</h3>
                <p class="text-sm text-muted-foreground">
                  Your account balance has been updated. You can view your updated balance in your account overview.
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Support Information -->
      <Card>
        <CardContent class="pt-6">
          <p class="text-center text-sm text-muted-foreground">
            Need help? Contact the Accounting Office at 
            <a href="mailto:accounting@ccdi.edu.ph" class="font-medium text-primary hover:underline">
              accounting@ccdi.edu.ph
            </a>
            {' '}or call 
            <span class="font-medium">09181234502</span>
          </p>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>