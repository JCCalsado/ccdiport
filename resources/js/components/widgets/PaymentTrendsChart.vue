<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { TrendingUp } from 'lucide-vue-next'

interface PaymentTrend {
  date: string
  day: string
  day_full: string
  total: number
  count: number
}

interface Props {
  data: PaymentTrend[]
}

const props = defineProps<Props>()

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

const maxValue = computed(() => {
  return Math.max(...props.data.map(d => d.total), 1000)
})

const chartHeight = 200

const getBarHeight = (value: number) => {
  return (value / maxValue.value) * chartHeight
}

const totalWeek = computed(() => {
  return props.data.reduce((sum, d) => sum + d.total, 0)
})

const averageDaily = computed(() => {
  return totalWeek.value / props.data.length
})
</script>

<template>
  <Card>
    <CardHeader>
      <div class="flex items-center justify-between">
        <div>
          <CardTitle>Payment Trends (Last 7 Days)</CardTitle>
          <CardDescription>Daily payment collection overview</CardDescription>
        </div>
        <div class="flex items-center gap-2 text-green-600">
          <TrendingUp class="h-4 w-4" />
          <span class="text-sm font-medium">{{ formatCurrency(totalWeek) }} this week</span>
        </div>
      </div>
    </CardHeader>
    <CardContent>
      <div class="space-y-4">
        <!-- Chart -->
        <div class="flex items-end justify-between gap-2" :style="{ height: chartHeight + 'px' }">
          <div
            v-for="trend in data"
            :key="trend.date"
            class="flex-1 flex flex-col items-center gap-2"
          >
            <div class="flex flex-col items-center gap-1 text-xs">
              <span class="font-bold text-green-600">{{ formatCurrency(trend.total) }}</span>
              <span class="text-muted-foreground">{{ trend.count }}</span>
            </div>
            <div
              class="w-full bg-green-500 rounded-t hover:bg-green-600 transition-colors cursor-pointer relative group"
              :style="{ height: getBarHeight(trend.total) + 'px', minHeight: '4px' }"
              :title="`${trend.day_full}: ${formatCurrency(trend.total)} (${trend.count} payments)`"
            >
              <div
                class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap"
              >
                {{ trend.day_full }}: {{ formatCurrency(trend.total) }}
              </div>
            </div>
            <span class="text-xs font-medium">{{ trend.day }}</span>
          </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-2 gap-4 pt-4 border-t">
          <div>
            <p class="text-sm text-muted-foreground">Weekly Total</p>
            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(totalWeek) }}</p>
          </div>
          <div>
            <p class="text-sm text-muted-foreground">Daily Average</p>
            <p class="text-2xl font-bold">{{ formatCurrency(averageDaily) }}</p>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>