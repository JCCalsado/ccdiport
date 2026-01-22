<script setup lang="ts">
import { ref, computed } from 'vue'
import { CreditCard, Smartphone, Building2, Wallet } from 'lucide-vue-next'

interface Props {
  amount: number
  accountId: string
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'method-selected': [method: string]
}>()

const selectedMethod = ref<string | null>(null)

const paymentMethods = [
  {
    id: 'gcash',
    name: 'GCash',
    icon: Smartphone,
    color: 'bg-blue-500',
    enabled: true,
  },
  {
    id: 'maya',
    name: 'Maya (PayMaya)',
    icon: Wallet,
    color: 'bg-green-500',
    enabled: true,
  },
  {
    id: 'bank_transfer',
    name: 'Bank Transfer',
    icon: Building2,
    color: 'bg-purple-500',
    enabled: true,
  },
  {
    id: 'cash',
    name: 'Pay at Cashier',
    icon: CreditCard,
    color: 'bg-gray-500',
    enabled: true,
  },
]

const handleSelect = (methodId: string) => {
  selectedMethod.value = methodId
  emit('method-selected', methodId)
}
</script>

<template>
  <div class="space-y-6">
    <div class="text-center">
      <h3 class="text-2xl font-bold">Select Payment Method</h3>
      <p class="text-gray-600 mt-2">
        Amount to Pay: <span class="text-2xl font-bold text-green-600">₱{{ amount.toFixed(2) }}</span>
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <button
        v-for="method in paymentMethods"
        :key="method.id"
        @click="handleSelect(method.id)"
        :disabled="!method.enabled"
        :class="[
          'p-6 rounded-lg border-2 transition-all duration-200',
          selectedMethod === method.id
            ? 'border-blue-500 bg-blue-50'
            : 'border-gray-200 hover:border-gray-300',
          !method.enabled && 'opacity-50 cursor-not-allowed'
        ]"
      >
        <div class="flex items-center gap-4">
          <div :class="[method.color, 'p-3 rounded-lg text-white']">
            <component :is="method.icon" :size="32" />
          </div>
          <div class="text-left flex-1">
            <h4 class="font-semibold text-lg">{{ method.name }}</h4>
            <p class="text-sm text-gray-500">
              {{ method.enabled ? 'Available' : 'Coming Soon' }}
            </p>
          </div>
          <div v-if="selectedMethod === method.id" class="text-blue-500">
            ✓
          </div>
        </div>
      </button>
    </div>

    <div v-if="selectedMethod" class="mt-6 text-center">
      <p class="text-sm text-gray-600 mb-4">
        Selected: <strong>{{ paymentMethods.find(m => m.id === selectedMethod)?.name }}</strong>
      </p>
      <slot name="continue-button" />
    </div>
  </div>
</template>