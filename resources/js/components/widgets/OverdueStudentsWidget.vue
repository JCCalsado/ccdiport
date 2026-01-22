<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { AlertCircle, ArrowRight, Calendar } from 'lucide-vue-next'

interface OverdueStudent {
  account_id: string
  student_id: string
  name: string
  course: string
  year_level: string
  total_overdue: number
  overdue_terms_count: number
  oldest_due_date: string
  days_past_due: number
}

interface Props {
  students: OverdueStudent[]
}

defineProps<Props>()

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const getUrgencyClass = (days: number) => {
  if (days > 30) return 'bg-red-100 text-red-800'
  if (days > 14) return 'bg-orange-100 text-orange-800'
  return 'bg-yellow-100 text-yellow-800'
}
</script>

<template>
  <Card>
    <CardHeader>
      <div class="flex items-center justify-between">
        <div>
          <CardTitle class="flex items-center gap-2">
            <AlertCircle class="h-5 w-5 text-red-600" />
            Overdue Accounts
          </CardTitle>
          <CardDescription>Students with past-due payments</CardDescription>
        </div>
        <Badge variant="destructive">{{ students.length }}</Badge>
      </div>
    </CardHeader>
    <CardContent>
      <div v-if="students.length > 0" class="space-y-3">
        <div
          v-for="student in students"
          :key="student.account_id"
          class="flex items-start justify-between p-3 rounded-lg border border-red-200 bg-red-50/50 hover:bg-red-100/50 transition-colors cursor-pointer"
          @click="$inertia.visit(route('student-fees.show', student.account_id))"
        >
          <div class="flex-1 space-y-2">
            <div>
              <p class="font-medium">{{ student.name }}</p>
              <p class="text-sm text-muted-foreground">
                {{ student.student_id }} • {{ student.year_level }}
              </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
              <Badge :class="getUrgencyClass(student.days_past_due)">
                {{ student.days_past_due }} days overdue
              </Badge>
              <Badge variant="outline">
                {{ student.overdue_terms_count }} {{ student.overdue_terms_count === 1 ? 'term' : 'terms' }}
              </Badge>
              <div class="flex items-center gap-1 text-xs text-muted-foreground">
                <Calendar class="h-3 w-3" />
                <span>Due: {{ formatDate(student.oldest_due_date) }}</span>
              </div>
            </div>
          </div>

          <div class="text-right">
            <p class="text-lg font-bold text-red-600">
              {{ formatCurrency(student.total_overdue) }}
            </p>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8 text-muted-foreground">
        <AlertCircle class="h-8 w-8 mx-auto mb-2 opacity-50" />
        <p class="text-sm">No overdue accounts</p>
      </div>
    </CardContent>
  </Card>
</template>