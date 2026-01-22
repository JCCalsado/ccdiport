<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';

interface PaymentGateway {
  id: number;
  name: string;
  slug: string;
  logo_url: string | null;
  description: string | null;
  is_active: boolean;
  supported_methods: string[];
  fees: {
    percentage: number;
    fixed: number;
  };
  config: {
    environment: 'sandbox' | 'production';
    public_key?: string | null;
    secret_key?: string | null;
    webhook_secret?: string | null;
    api_key?: string | null;
  };
  sort_order: number;
  created_at?: string;
  updated_at?: string;
}

interface Props {
  gateways: PaymentGateway[];
}

const props = defineProps<Props>();

const showConfigModal = ref(false);
const selectedGateway = ref<PaymentGateway | null>(null);
const configForm = ref({
  environment: 'sandbox' as 'sandbox' | 'production',
  public_key: '',
  secret_key: '',
  webhook_secret: '',
  api_key: '',
  percentage_fee: 0,
  fixed_fee: 0,
});
const testingConnection = ref(false);
const testResult = ref<{ success: boolean; message: string } | null>(null);

const openConfigModal = (gateway: PaymentGateway) => {
  selectedGateway.value = gateway;
  configForm.value = {
    environment: gateway.config.environment || 'sandbox',
    public_key: gateway.config.public_key || '',
    secret_key: gateway.config.secret_key || '',
    webhook_secret: gateway.config.webhook_secret || '',
    api_key: gateway.config.api_key || '',
    percentage_fee: gateway.fees?.percentage || 0,
    fixed_fee: gateway.fees?.fixed || 0,
  };
  testResult.value = null;
  showConfigModal.value = true;
};

const closeConfigModal = () => {
  showConfigModal.value = false;
  selectedGateway.value = null;
  testResult.value = null;
};

const saveConfiguration = () => {
  if (!selectedGateway.value) return;

  router.post(
    '/admin/payment-gateways',
    {
      gateway_id: selectedGateway.value.id,
      environment: configForm.value.environment,
      public_key: configForm.value.public_key,
      secret_key: configForm.value.secret_key,
      webhook_secret: configForm.value.webhook_secret,
      api_key: configForm.value.api_key,
      percentage_fee: configForm.value.percentage_fee,
      fixed_fee: configForm.value.fixed_fee,
    },
    {
      onSuccess: () => {
        closeConfigModal();
      },
    }
  );
};

const testConnection = async () => {
  if (!selectedGateway.value) return;

  testingConnection.value = true;
  testResult.value = null;

  try {
    const response = await fetch(
      `/admin/payment-gateways/${selectedGateway.value.id}/test`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      }
    );

    const data = await response.json();
    testResult.value = data;
  } catch (error) {
    testResult.value = {
      success: false,
      message: 'Connection test failed',
    };
  } finally {
    testingConnection.value = false;
  }
};

const toggleGateway = (gateway: PaymentGateway) => {
  router.post(`/admin/payment-gateways/${gateway.id}/toggle`, {}, {
    preserveScroll: true,
  });
};

const getStatusColor = (isActive: boolean) => {
  return isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
};

const hasRequiredKeys = (gateway: PaymentGateway) => {
  if (gateway.slug === 'paymongo') {
    return !!(gateway.config.public_key && gateway.config.secret_key);
  }
  return !!gateway.config.api_key;
};

const getWebhookUrl = (gateway: PaymentGateway) => {
  const baseUrl = window.location.origin;
  return `${baseUrl}/webhooks/${gateway.slug}`;
};
</script>

<template>
  <Head title="Payment Gateways" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
          Payment Gateways
        </h2>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <!-- Info Alert -->
        <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                Configure Payment Gateways
              </h3>
              <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                <ul class="list-disc space-y-1 pl-5">
                  <li>Configure API credentials for each gateway before enabling</li>
                  <li>Test the connection after configuration</li>
                  <li>Add webhook URLs to your gateway dashboard</li>
                  <li>Start with sandbox mode for testing</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Gateways Grid -->
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="gateway in gateways"
            :key="gateway.id"
            class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800"
          >
            <!-- Header -->
            <div class="border-b border-gray-200 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-900">
              <div class="flex items-start justify-between">
                <div class="flex items-center space-x-3">
                  <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-white shadow-sm">
                    <span class="text-2xl font-bold text-gray-700">
                      {{ gateway.name.charAt(0) }}
                    </span>
                  </div>
                  <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                      {{ gateway.name }}
                    </h3>
                    <span
                      :class="getStatusColor(gateway.is_active)"
                      class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                    >
                      {{ gateway.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Body -->
            <div class="p-6">
              <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                {{ gateway.description }}
              </p>

              <!-- Configuration Status -->
              <div class="mb-4 space-y-2">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Environment:</span>
                  <span class="font-medium text-gray-900 dark:text-white">
                    {{ gateway.config.environment || 'Not configured' }}
                  </span>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">API Keys:</span>
                  <span :class="hasRequiredKeys(gateway) ? 'text-green-600' : 'text-red-600'" class="font-medium">
                    {{ hasRequiredKeys(gateway) ? 'Configured' : 'Missing' }}
                  </span>
                </div>
              </div>

              <!-- Fees -->
              <div class="mb-4 rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                  Transaction Fees
                </div>
                <div class="mt-2 text-sm text-gray-900 dark:text-white">
                  {{ gateway.fees?.percentage || 0 }}% + ₱{{ gateway.fees?.fixed || 0 }}
                </div>
              </div>

              <!-- Supported Methods -->
              <div class="mb-4">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                  Payment Methods
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                  <span
                    v-for="method in gateway.supported_methods"
                    :key="method"
                    class="inline-flex rounded-md bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                  >
                    {{ method }}
                  </span>
                </div>
              </div>

              <!-- Webhook URL -->
              <div class="mb-4">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                  Webhook URL
                </div>
                <div class="mt-1 flex items-center gap-2">
                  <input
                    type="text"
                    :value="getWebhookUrl(gateway)"
                    readonly
                    class="flex-1 rounded border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-600 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400"
                  />
                  <button
                    @click="navigator.clipboard.writeText(getWebhookUrl(gateway))"
                    class="rounded bg-gray-200 px-2 py-1 text-xs hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
                    title="Copy"
                  >
                    📋
                  </button>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex gap-2">
                <button
                  @click="openConfigModal(gateway)"
                  class="flex-1 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  Configure
                </button>
                <button
                  @click="toggleGateway(gateway)"
                  :disabled="!hasRequiredKeys(gateway)"
                  :class="[
                    gateway.is_active
                      ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500'
                      : 'bg-green-600 hover:bg-green-700 focus:ring-green-500',
                    !hasRequiredKeys(gateway) && 'opacity-50 cursor-not-allowed'
                  ]"
                  class="flex-1 rounded-md px-4 py-2 text-sm font-medium text-white focus:outline-none focus:ring-2"
                >
                  {{ gateway.is_active ? 'Disable' : 'Enable' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Configuration Modal -->
    <div
      v-if="showConfigModal && selectedGateway"
      class="fixed inset-0 z-50 overflow-y-auto"
      aria-labelledby="modal-title"
      role="dialog"
      aria-modal="true"
    >
      <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
        <div
          class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
          aria-hidden="true"
          @click="closeConfigModal"
        ></div>

        <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

        <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all dark:bg-gray-800 sm:my-8 sm:w-full sm:max-w-2xl sm:align-middle">
          <div class="bg-white px-4 pb-4 pt-5 dark:bg-gray-800 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mt-3 w-full text-center sm:ml-4 sm:mt-0 sm:text-left">
                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white" id="modal-title">
                  Configure {{ selectedGateway.name }}
                </h3>
                <div class="mt-4 space-y-4">
                  <!-- Environment -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                      Environment
                    </label>
                    <select
                      v-model="configForm.environment"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                    >
                      <option value="sandbox">Sandbox (Testing)</option>
                      <option value="production">Production (Live)</option>
                    </select>
                  </div>

                  <!-- PayMongo Fields -->
                  <template v-if="selectedGateway.slug === 'paymongo'">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Public Key
                      </label>
                      <input
                        v-model="configForm.public_key"
                        type="text"
                        placeholder="pk_test_..."
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Secret Key
                      </label>
                      <input
                        v-model="configForm.secret_key"
                        type="password"
                        placeholder="sk_test_..."
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Webhook Secret
                      </label>
                      <input
                        v-model="configForm.webhook_secret"
                        type="password"
                        placeholder="whsec_..."
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                      />
                    </div>
                  </template>

                  <!-- Other Gateways -->
                  <template v-else>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        API Key
                      </label>
                      <input
                        v-model="configForm.api_key"
                        type="password"
                        placeholder="Enter API key"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                      />
                    </div>
                  </template>

                  <!-- Fees -->
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Percentage Fee (%)
                      </label>
                      <input
                        v-model.number="configForm.percentage_fee"
                        type="number"
                        step="0.1"
                        min="0"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Fixed Fee (₱)
                      </label>
                      <input
                        v-model.number="configForm.fixed_fee"
                        type="number"
                        step="0.01"
                        min="0"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                      />
                    </div>
                  </div>

                  <!-- Test Connection -->
                  <div>
                    <button
                      @click="testConnection"
                      :disabled="testingConnection"
                      class="w-full rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                      {{ testingConnection ? 'Testing...' : 'Test Connection' }}
                    </button>
                    <div v-if="testResult" class="mt-2">
                      <div
                        :class="[
                          'rounded-md p-3 text-sm',
                          testResult.success
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                        ]"
                      >
                        {{ testResult.message }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 dark:bg-gray-900 sm:flex sm:flex-row-reverse sm:px-6">
            <button
              @click="saveConfiguration"
              type="button"
              class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
            >
              Save Configuration
            </button>
            <button
              @click="closeConfigModal"
              type="button"
              class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 sm:ml-3 sm:mt-0 sm:w-auto sm:text-sm"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>