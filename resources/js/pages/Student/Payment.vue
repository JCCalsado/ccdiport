<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Separator } from '@/components/ui/separator'
import { 
  CreditCard, 
  Smartphone, 
  Building2, 
  ArrowRight,
  AlertCircle,
  Loader2,
  CheckCircle2,
  Info
} from 'lucide-vue-next'
import { useToast } from '@/components/ui/toast/use-toast'

interface PaymentGateway {
  id: number
  name: string
  slug: string
  supported_methods: string[]
  fees: {
    type: 'percentage' | 'fixed'
    amount: number
  }
  logo_url?: string
}

interface PaymentTerm {
  id: number
  term_name: string
  amount: number
  remaining_balance: number
  due_date: string
}

interface Props {
  account_id: string
  gateways: PaymentGateway[]
  payment_terms: PaymentTerm[]
  total_balance: number
  min_payment: number
}

const props = defineProps<Props>()
const { toast } = useToast()

// Form state
const selectedGateway = ref<number | null>(null)
const selectedMethod = ref<string>('')
const paymentAmount = ref<number>(props.min_payment)
const selectedTerms = ref<number[]>([])
const isProcessing = ref(false)

// Computed
const selectedGatewayData = computed(() => {
  return props.gateways.find(g => g.id === selectedGateway.value)
})

const transactionFee = computed(() => {
  if (!selectedGatewayData.value) return 0
  
  const fee = selectedGatewayData.value.fees
  if (fee.type === 'percentage') {
    return paymentAmount.value * (fee.amount / 100)
  }
  return fee.amount
})

const totalAmount = computed(() => {
  return paymentAmount.value + transactionFee.value
})

const canProceed = computed(() => {
  return selectedGateway.value && 
         selectedMethod.value && 
         paymentAmount.value >= props.min_payment &&
         paymentAmount.value <= props.total_balance
})

// Methods
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

const getMethodIcon = (method: string) => {
  const icons: Record<string, any> = {
    'gcash': Smartphone,
    'maya': Smartphone,
    'card': CreditCard,
    'bank': Building2,
  }
  return icons[method.toLowerCase()] || CreditCard
}

const selectGateway = (gatewayId: number) => {
  selectedGateway.value = gatewayId
  selectedMethod.value = ''
}

const selectMethod = (method: string) => {
  selectedMethod.value = method
}

const processPayment = async () => {
  if (!canProceed.value) return

  isProcessing.value = true

  try {
    await router.post(
      route('student.payment.process'),
      {
        gateway_id: selectedGateway.value,
        payment_method: selectedMethod.value,
        amount: paymentAmount.value,
        term_ids: selectedTerms.value.length > 0 ? selectedTerms.value : null,
      },
      {
        onSuccess: (page) => {
          const data = page.props as any
          if (data.redirect_url) {
            // Redirect to payment gateway
            window.location.href = data.redirect_url
          } else {
            toast({
              title: 'Payment Initiated',
              description: 'Please complete the payment process.',
            })
          }
        },
        onError: (errors) => {
          toast({
            title: 'Payment Failed',
            description: Object.values(errors)[0] as string,
            variant: 'destructive',
          })
        },
      }
    )
  } finally {
    isProcessing.value = false
  }
}
</script>

<template>
  <AppLayout>
    <Head title="Make Payment" />

    <div class="mx-auto max-w-4xl space-y-6">
      <!-- Header -->
      <div>
        <h1 class="text-3xl font-bold tracking-tight">Make a Payment</h1>
        <p class="text-muted-foreground">
          Choose your payment method and complete the transaction
        </p>
      </div>

      <!-- Current Balance -->
      <Card>
        <CardHeader>
          <CardTitle>Outstanding Balance</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-3xl font-bold text-red-600">
            {{ formatCurrency(props.total_balance) }}
          </div>
        </CardContent>
      </Card>

      <!-- Payment Amount -->
      <Card>
        <CardHeader>
          <CardTitle>Payment Amount</CardTitle>
          <CardDescription>
            Minimum payment: {{ formatCurrency(props.min_payment) }}
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label>Amount to Pay</Label>
            <Input
              v-model.number="paymentAmount"
              type="number"
              :min="props.min_payment"
              :max="props.total_balance"
              step="0.01"
            />
          </div>

          <!-- Quick Amount Buttons -->
          <div class="flex gap-2">
            <Button
              v-for="amount in [props.min_payment, props.total_balance / 2, props.total_balance]"
              :key="amount"
              variant="outline"
              size="sm"
              @click="paymentAmount = amount"
            >
              {{ formatCurrency(amount) }}
            </Button>
          </div>

          <!-- Apply to Specific Terms (Optional) -->
          <div v-if="props.payment_terms.length > 0" class="space-y-2">
            <Label>Apply to Specific Payment Terms (Optional)</Label>
            <div class="space-y-2 rounded-lg border p-3">
              <div
                v-for="term in props.payment_terms.filter(t => t.remaining_balance > 0)"
                :key="term.id"
                class="flex items-center gap-2"
              >
                <input
                  type="checkbox"
                  :value="term.id"
                  v-model="selectedTerms"
                  class="rounded border-gray-300"
                />
                <span class="text-sm">
                  {{ term.term_name }} - {{ formatCurrency(term.remaining_balance) }}
                </span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Payment Gateway Selection -->
      <Card>
        <CardHeader>
          <CardTitle>Select Payment Gateway</CardTitle>
        </CardHeader>
        <CardContent>
          <RadioGroup
            :model-value="selectedGateway"
            @update:model-value="selectGateway"
            class="grid gap-4 sm:grid-cols-2"
          >
            <div
              v-for="gateway in props.gateways"
              :key="gateway.id"
              class="relative"
            >
              <RadioGroupItem
                :value="gateway.id"
                :id="`gateway-${gateway.id}`"
                class="peer sr-only"
              />
              <Label
                :for="`gateway-${gateway.id}`"
                class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary"
              >
                <div class="flex-1">
                  <p class="font-semibold">{{ gateway.name }}</p>
                  <p class="text-xs text-muted-foreground">
                    Fee: {{ gateway.fees.type === 'percentage' ? 
                      `${gateway.fees.amount}%` : 
                      formatCurrency(gateway.fees.amount) 
                    }}
                  </p>
                </div>
              </Label>
            </div>
          </RadioGroup>
        </CardContent>
      </Card>

      <!-- Payment Method Selection -->
      <Card v-if="selectedGatewayData">
        <CardHeader>
          <CardTitle>Select Payment Method</CardTitle>
        </CardHeader>
        <CardContent>
          <RadioGroup
            :model-value="selectedMethod"
            @update:model-value="selectMethod"
            class="grid gap-4 sm:grid-cols-2"
          >
            <div
              v-for="method in selectedGatewayData.supported_methods"
              :key="method"
              class="relative"
            >
              <RadioGroupItem
                :value="method"
                :id="`method-${method}`"
                class="peer sr-only"
              />
              <Label
                :for="`method-${method}`"
                class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary"
              >
                <component :is="getMethodIcon(method)" class="h-6 w-6" />
                <span class="font-semibold capitalize">{{ method }}</span>
              </Label>
            </div>
          </RadioGroup>
        </CardContent>
      </Card>

      <!-- Payment Summary -->
      <Card v-if="selectedGatewayData">
        <CardHeader>
          <CardTitle>Payment Summary</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <div class="flex justify-between">
              <span>Payment Amount</span>
              <span>{{ formatCurrency(paymentAmount) }}</span>
            </div>
            <div class="flex justify-between text-sm text-muted-foreground">
              <span>Transaction Fee</span>
              <span>{{ formatCurrency(transactionFee) }}</span>
            </div>
            <Separator />
            <div class="flex justify-between text-lg font-bold">
              <span>Total Amount</span>
              <span>{{ formatCurrency(totalAmount) }}</span>
            </div>
          </div>

          <Alert>
            <Info class="h-4 w-4" />
            <AlertDescription>
              You will be redirected to {{ selectedGatewayData.name }} to complete your payment securely.
            </AlertDescription>
          </Alert>

          <Button
            @click="processPayment"
            :disabled="!canProceed || isProcessing"
            class="w-full"
            size="lg"
          >
            <Loader2 v-if="isProcessing" class="mr-2 h-4 w-4 animate-spin" />
            <span v-else>Proceed to Payment</span>
            <ArrowRight class="ml-2 h-4 w-4" />
          </Button>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>