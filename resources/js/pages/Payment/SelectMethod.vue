<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import PaymentMethodSelector from '@/components/PaymentMethodSelector.vue'
import { Button } from '@/components/ui/button'

interface Props {
  amount: number
  accountId: string
  termId?: number
}

const props = defineProps<Props>()
const selectedMethod = ref<string | null>(null)

const handleMethodSelected = (method: string) => {
  selectedMethod.value = method
}

const handleContinue = () => {
  if (!selectedMethod.value) return

  // Route to appropriate payment flow
  const routes = {
    gcash: route('payment.gcash.create', { accountId: props.accountId }),
    maya: route('payment.maya.create', { accountId: props.accountId }),
    bank_transfer: route('payment.bank.create', { accountId: props.accountId }),
    cash: route('student.account'), // Just show instructions
  }

  router.visit(routes[selectedMethod.value as keyof typeof routes])
}
</script>

<template>
  <AppLayout title="Select Payment Method">
    <div class="max-w-4xl mx-auto py-12 px-4">
      <PaymentMethodSelector
        :amount="amount"
        :account-id="accountId"
        @method-selected="handleMethodSelected"
      >
        <template #continue-button>
          <Button
            @click="handleContinue"
            :disabled="!selectedMethod"
            size="lg"
            class="px-8"
          >
            Continue to Payment
          </Button>
        </template>
      </PaymentMethodSelector>
    </div>
  </AppLayout>
</template>