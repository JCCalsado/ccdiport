<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { 
  CreditCard, 
  Eye, 
  EyeOff, 
  Save, 
  RefreshCw, 
  AlertCircle,
  CheckCircle2,
  XCircle,
  Settings,
  Smartphone,
  Building2,
  Globe
} from 'lucide-vue-next'
import { useToast } from '@/components/ui/toast/use-toast'

interface PaymentGateway {
  id: number
  name: string
  slug: string
  is_active: boolean
  is_sandbox: boolean
  config: {
    public_key?: string
    secret_key?: string
    merchant_id?: string
    client_id?: string
    client_secret?: string
    webhook_secret?: string
  }
  fees: {
    type: 'percentage' | 'fixed'
    amount: number
  }
  supported_methods: string[]
  created_at: string
  updated_at: string
  last_used_at?: string
  total_transactions?: number
  total_amount?: number
}

interface Props {
  gateways: PaymentGateway[]
  stats: {
    total_active: number
    total_transactions_today: number
    total_amount_today: number
    success_rate: number
  }
}

const props = defineProps<Props>()
const { toast } = useToast()

// Form state
const editingGateway = ref<PaymentGateway | null>(null)
const showSecrets = ref<Record<string, boolean>>({})
const isSaving = ref(false)
const isTestingConnection = ref(false)

// Toggle secret visibility
const toggleSecretVisibility = (gatewayId: string, field: string) => {
  const key = `${gatewayId}-${field}`
  showSecrets.value[key] = !showSecrets.value[key]
}

const isSecretVisible = (gatewayId: string, field: string): boolean => {
  const key = `${gatewayId}-${field}`
  return showSecrets.value[key] || false
}

// Gateway icons mapping
const gatewayIcons = {
  paymongo: CreditCard,
  gcash: Smartphone,
  maya: Smartphone,
  'bank-transfer': Building2,
  dragonpay: Globe,
}

// Get gateway icon
const getGatewayIcon = (slug: string) => {
  return gatewayIcons[slug as keyof typeof gatewayIcons] || CreditCard
}

// Get status badge variant
const getStatusVariant = (isActive: boolean) => {
  return isActive ? 'default' : 'secondary'
}

// Start editing a gateway
const startEdit = (gateway: PaymentGateway) => {
  editingGateway.value = { ...gateway }
}

// Cancel editing
const cancelEdit = () => {
  editingGateway.value = null
}

// Save gateway configuration
const saveGateway = async () => {
  if (!editingGateway.value) return

  isSaving.value = true

  try {
    await router.put(
      route('admin.payment-gateways.update', editingGateway.value.id),
      editingGateway.value,
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Success',
            description: 'Payment gateway configuration saved successfully.',
          })
          editingGateway.value = null
        },
        onError: (errors) => {
          toast({
            title: 'Error',
            description: Object.values(errors)[0] as string,
            variant: 'destructive',
          })
        },
      }
    )
  } finally {
    isSaving.value = false
  }
}

// Test gateway connection
const testConnection = async (gatewayId: number) => {
  isTestingConnection.value = true

  try {
    await router.post(
      route('admin.payment-gateways.test', gatewayId),
      {},
      {
        preserveScroll: true,
        onSuccess: (page) => {
          const result = page.props.testResult as { success: boolean; message: string }
          toast({
            title: result.success ? 'Connection Successful' : 'Connection Failed',
            description: result.message,
            variant: result.success ? 'default' : 'destructive',
          })
        },
      }
    )
  } finally {
    isTestingConnection.value = false
  }
}

// Toggle gateway active status
const toggleGatewayStatus = async (gateway: PaymentGateway) => {
  try {
    await router.post(
      route('admin.payment-gateways.toggle-status', gateway.id),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast({
            title: 'Success',
            description: `${gateway.name} ${!gateway.is_active ? 'activated' : 'deactivated'} successfully.`,
          })
        },
      }
    )
  } catch (error) {
    toast({
      title: 'Error',
      description: 'Failed to update gateway status.',
      variant: 'destructive',
    })
  }
}

// Format currency
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

// Format date
const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Mask secret key
const maskSecret = (secret: string | undefined, visible: boolean): string => {
  if (!secret) return 'Not configured'
  if (visible) return secret
  return '•'.repeat(Math.min(secret.length, 32))
}
</script>

<template>
  <AppLayout>
    <Head title="Payment Gateways" />

    <div class="space-y-6">
      <!-- Header -->
      <div>
        <h1 class="text-3xl font-bold tracking-tight">Payment Gateways</h1>
        <p class="text-muted-foreground">
          Configure and manage online payment gateway integrations
        </p>
      </div>

      <!-- Stats Overview -->
      <div class="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Active Gateways</CardTitle>
            <CheckCircle2 class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ props.stats.total_active }}</div>
            <p class="text-xs text-muted-foreground">
              out of {{ props.gateways.length }} total
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Transactions Today</CardTitle>
            <CreditCard class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ props.stats.total_transactions_today }}</div>
            <p class="text-xs text-muted-foreground">
              {{ formatCurrency(props.stats.total_amount_today) }}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Success Rate</CardTitle>
            <RefreshCw class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ props.stats.success_rate.toFixed(1) }}%</div>
            <p class="text-xs text-muted-foreground">Last 30 days</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">System Status</CardTitle>
            <Settings class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-green-600">Operational</div>
            <p class="text-xs text-muted-foreground">All systems normal</p>
          </CardContent>
        </Card>
      </div>

      <!-- Alert for sandbox mode -->
      <Alert v-if="props.gateways.some(g => g.is_active && g.is_sandbox)">
        <AlertCircle class="h-4 w-4" />
        <AlertDescription>
          <strong>Warning:</strong> Some gateways are running in sandbox mode. Real transactions will not be processed.
        </AlertDescription>
      </Alert>

      <!-- Gateways List -->
      <div class="grid gap-6 md:grid-cols-2">
        <Card v-for="gateway in props.gateways" :key="gateway.id">
          <CardHeader>
            <div class="flex items-start justify-between">
              <div class="flex items-center gap-3">
                <div class="rounded-lg bg-primary/10 p-2">
                  <component :is="getGatewayIcon(gateway.slug)" class="h-6 w-6 text-primary" />
                </div>
                <div>
                  <CardTitle class="flex items-center gap-2">
                    {{ gateway.name }}
                    <Badge :variant="getStatusVariant(gateway.is_active)">
                      {{ gateway.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                    <Badge v-if="gateway.is_sandbox" variant="outline">Sandbox</Badge>
                  </CardTitle>
                  <CardDescription>
                    {{ gateway.supported_methods.join(', ') }}
                  </CardDescription>
                </div>
              </div>
              <Switch
                :checked="gateway.is_active"
                @update:checked="toggleGatewayStatus(gateway)"
              />
            </div>
          </CardHeader>

          <CardContent class="space-y-4">
            <!-- Usage Stats -->
            <div class="grid grid-cols-2 gap-4 rounded-lg bg-muted/50 p-3">
              <div>
                <p class="text-xs text-muted-foreground">Total Transactions</p>
                <p class="text-lg font-semibold">{{ gateway.total_transactions || 0 }}</p>
              </div>
              <div>
                <p class="text-xs text-muted-foreground">Total Amount</p>
                <p class="text-lg font-semibold">{{ formatCurrency(gateway.total_amount || 0) }}</p>
              </div>
            </div>

            <!-- Configuration Form (when editing) -->
            <div v-if="editingGateway?.id === gateway.id" class="space-y-4">
              <Separator />
              
              <!-- Environment Toggle -->
              <div class="flex items-center justify-between">
                <Label>Sandbox Mode</Label>
                <Switch v-model:checked="editingGateway.is_sandbox" />
              </div>

              <!-- Configuration Fields (PayMongo Example) -->
              <div v-if="gateway.slug === 'paymongo'" class="space-y-4">
                <div class="space-y-2">
                  <Label>Public Key</Label>
                  <div class="relative">
                    <Input
                      v-model="editingGateway.config.public_key"
                      :type="isSecretVisible(String(gateway.id), 'public_key') ? 'text' : 'password'"
                      placeholder="pk_test_..."
                    />
                    <Button
                      variant="ghost"
                      size="sm"
                      class="absolute right-0 top-0 h-full"
                      @click="toggleSecretVisibility(String(gateway.id), 'public_key')"
                    >
                      <Eye v-if="!isSecretVisible(String(gateway.id), 'public_key')" class="h-4 w-4" />
                      <EyeOff v-else class="h-4 w-4" />
                    </Button>
                  </div>
                </div>

                <div class="space-y-2">
                  <Label>Secret Key</Label>
                  <div class="relative">
                    <Input
                      v-model="editingGateway.config.secret_key"
                      :type="isSecretVisible(String(gateway.id), 'secret_key') ? 'text' : 'password'"
                      placeholder="sk_test_..."
                    />
                    <Button
                      variant="ghost"
                      size="sm"
                      class="absolute right-0 top-0 h-full"
                      @click="toggleSecretVisibility(String(gateway.id), 'secret_key')"
                    >
                      <Eye v-if="!isSecretVisible(String(gateway.id), 'secret_key')" class="h-4 w-4" />
                      <EyeOff v-else class="h-4 w-4" />
                    </Button>
                  </div>
                </div>

                <div class="space-y-2">
                  <Label>Webhook Secret</Label>
                  <div class="relative">
                    <Input
                      v-model="editingGateway.config.webhook_secret"
                      :type="isSecretVisible(String(gateway.id), 'webhook_secret') ? 'text' : 'password'"
                      placeholder="whsec_..."
                    />
                    <Button
                      variant="ghost"
                      size="sm"
                      class="absolute right-0 top-0 h-full"
                      @click="toggleSecretVisibility(String(gateway.id), 'webhook_secret')"
                    >
                      <Eye v-if="!isSecretVisible(String(gateway.id), 'webhook_secret')" class="h-4 w-4" />
                      <EyeOff v-else class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </div>

              <!-- Fee Configuration -->
              <Separator />
              <div class="space-y-4">
                <Label>Transaction Fee</Label>
                <div class="grid grid-cols-2 gap-2">
                  <div class="space-y-2">
                    <Label class="text-xs">Type</Label>
                    <select
                      v-model="editingGateway.fees.type"
                      class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                      <option value="percentage">Percentage</option>
                      <option value="fixed">Fixed Amount</option>
                    </select>
                  </div>
                  <div class="space-y-2">
                    <Label class="text-xs">Amount</Label>
                    <Input
                      v-model.number="editingGateway.fees.amount"
                      type="number"
                      step="0.01"
                      :placeholder="editingGateway.fees.type === 'percentage' ? '2.5' : '10.00'"
                    />
                  </div>
                </div>
                <p class="text-xs text-muted-foreground">
                  {{ editingGateway.fees.type === 'percentage' ? 
                    `${editingGateway.fees.amount}% of transaction amount` : 
                    `₱${editingGateway.fees.amount.toFixed(2)} per transaction` 
                  }}
                </p>
              </div>

              <!-- Action Buttons -->
              <div class="flex gap-2">
                <Button @click="saveGateway" :disabled="isSaving" class="flex-1">
                  <Save class="mr-2 h-4 w-4" />
                  {{ isSaving ? 'Saving...' : 'Save Changes' }}
                </Button>
                <Button variant="outline" @click="cancelEdit">Cancel</Button>
              </div>
            </div>

            <!-- View Mode Actions -->
            <div v-else class="flex gap-2">
              <Button variant="outline" class="flex-1" @click="startEdit(gateway)">
                <Settings class="mr-2 h-4 w-4" />
                Configure
              </Button>
              <Button
                variant="outline"
                @click="testConnection(gateway.id)"
                :disabled="isTestingConnection || !gateway.is_active"
              >
                <RefreshCw
                  :class="['h-4 w-4', isTestingConnection && 'animate-spin']"
                />
              </Button>
            </div>

            <!-- Last Used Info -->
            <div v-if="gateway.last_used_at" class="text-xs text-muted-foreground">
              Last used: {{ formatDate(gateway.last_used_at) }}
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Documentation Link -->
      <Card>
        <CardHeader>
          <CardTitle>Need Help?</CardTitle>
          <CardDescription>
            Learn how to configure payment gateways and handle webhooks
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex gap-4">
            <Button variant="outline" as="a" href="/docs/payment-gateways" target="_blank">
              View Documentation
            </Button>
            <Button variant="outline" as="a" href="/docs/webhooks" target="_blank">
              Webhook Guide
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>