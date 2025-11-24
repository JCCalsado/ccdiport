<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { Button } from '@/components/ui/button'
import { 
  ArrowLeft, 
  Edit, 
  Trash2,
  Power,
  PowerOff,
  Calculator,
  BookOpen,
  GraduationCap,
  AlertCircle
} from 'lucide-vue-next'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'

interface Program {
  id: number
  code: string
  name: string
  major: string | null
  full_name: string
}

interface Course {
  id: number
  code: string
  title: string
  lec_units: number
  lab_units: number
  total_units: number
  has_lab: boolean
  pivot: {
    order: number
  }
}

interface Curriculum {
  id: number
  program: Program
  school_year: string
  year_level: string
  semester: string
  tuition_per_unit: number
  lab_fee: number
  registration_fee: number
  misc_fee: number
  term_count: number
  notes: string | null
  is_active: boolean
  created_at: string
  courses: Course[]
}

interface Props {
  curriculum: Curriculum
  totals: {
    total_units: number
    tuition: number
    lab_fees: number
    total_assessment: number
  }
  paymentTerms: {
    upon_registration: number
    prelim: number
    midterm: number
    semi_final: number
    final: number
  }
  enrolledStudentsCount: number
}

const props = defineProps<Props>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Curricula', href: route('curricula.index') },
  { title: 'Curriculum Details' },
]

// Toggle status
const toggleStatus = () => {
  router.post(
    route('curricula.toggleStatus', props.curriculum.id),
    {},
    { preserveScroll: true }
  )
}

// Delete curriculum
const deleteCurriculum = () => {
  if (confirm(`Are you sure you want to delete this curriculum?\n\nProgram: ${props.curriculum.program.code}\nTerm: ${props.curriculum.year_level} - ${props.curriculum.semester}\n\nThis action cannot be undone.`)) {
    router.delete(route('curricula.destroy', props.curriculum.id), {
      onSuccess: () => {
        router.visit(route('curricula.index'))
      },
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
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}
</script>

<template>
  <Head :title="`Curriculum - ${curriculum.program.code} ${curriculum.year_level} ${curriculum.semester}`" />

  <AppLayout>
    <div class="min-h-screen bg-gray-50">
      <div class="mx-auto max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <Breadcrumbs :items="breadcrumbs" />

        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                {{ curriculum.program.code }} - {{ curriculum.year_level }}
              </h1>
              <span
                :class="[
                  'inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-semibold',
                  curriculum.is_active 
                    ? 'bg-green-100 text-green-800' 
                    : 'bg-gray-100 text-gray-800'
                ]"
              >
                <component :is="curriculum.is_active ? Power : PowerOff" class="h-3 w-3" />
                {{ curriculum.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
            <p class="mt-2 text-sm text-gray-600">
              {{ curriculum.program.major }} • {{ curriculum.semester }} • {{ curriculum.school_year }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <Button variant="outline" @click="toggleStatus">
              <component :is="curriculum.is_active ? PowerOff : Power" class="mr-2 h-4 w-4" />
              {{ curriculum.is_active ? 'Deactivate' : 'Activate' }}
            </Button>
            <Link :href="route('curricula.edit', curriculum.id)">
              <Button variant="outline">
                <Edit class="mr-2 h-4 w-4" />
                Edit
              </Button>
            </Link>
            <Button
              variant="outline"
              class="text-red-600 hover:bg-red-50 hover:text-red-700"
              @click="deleteCurriculum"
            >
              <Trash2 class="mr-2 h-4 w-4" />
              Delete
            </Button>
            <Link :href="route('curricula.index')">
              <Button variant="ghost">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
              </Button>
            </Link>
          </div>
        </div>

        <!-- Warning if active -->
        <div 
          v-if="curriculum.is_active" 
          class="flex items-start gap-3 rounded-lg border border-yellow-200 bg-yellow-50 p-4"
        >
          <AlertCircle class="mt-0.5 h-5 w-5 text-yellow-600" />
          <div>
            <p class="font-medium text-yellow-900">Active Curriculum</p>
            <p class="mt-1 text-sm text-yellow-700">
              This curriculum is currently active. Changes may affect enrolled students. 
              Consider deactivating before making major modifications.
            </p>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardContent class="pt-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm text-gray-600">Total Courses</p>
                  <p class="mt-2 text-3xl font-bold text-gray-900">{{ curriculum.courses.length }}</p>
                </div>
                <div class="rounded-full bg-blue-100 p-3">
                  <BookOpen class="h-6 w-6 text-blue-600" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent class="pt-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm text-gray-600">Total Units</p>
                  <p class="mt-2 text-3xl font-bold text-gray-900">{{ totals.total_units }}</p>
                </div>
                <div class="rounded-full bg-green-100 p-3">
                  <Calculator class="h-6 w-6 text-green-600" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent class="pt-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm text-gray-600">Total Assessment</p>
                  <p class="mt-2 text-xl font-bold text-gray-900">{{ formatCurrency(totals.total_assessment) }}</p>
                </div>
                <div class="rounded-full bg-purple-100 p-3">
                  <Calculator class="h-6 w-6 text-purple-600" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent class="pt-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm text-gray-600">Enrolled Students</p>
                  <p class="mt-2 text-3xl font-bold text-gray-900">{{ enrolledStudentsCount }}</p>
                </div>
                <div class="rounded-full bg-orange-100 p-3">
                  <GraduationCap class="h-6 w-6 text-orange-600" />
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
          <!-- Main Content -->
          <div class="space-y-6 lg:col-span-2">
            <!-- Courses Table -->
            <Card>
              <CardHeader>
                <CardTitle>Course List</CardTitle>
                <CardDescription>All courses included in this curriculum</CardDescription>
              </CardHeader>
              <CardContent>
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Course Title</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Lec</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Lab</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Cost</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                      <tr v-for="(course, index) in curriculum.courses" :key="course.id" class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ index + 1 }}</td>
                        <td class="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-900">{{ course.code }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                          {{ course.title }}
                          <span v-if="course.has_lab" class="ml-2 text-xs text-blue-600">● Lab</span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-900">{{ course.lec_units }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-900">{{ course.lab_units }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-center text-sm font-medium text-gray-900">{{ course.total_units }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-900">
                          {{ formatCurrency(course.total_units * curriculum.tuition_per_unit + (course.has_lab ? curriculum.lab_fee : 0)) }}
                        </td>
                      </tr>
                      <tr class="bg-gray-50 font-semibold">
                        <td colspan="5" class="px-6 py-4 text-right text-sm text-gray-900">Total:</td>
                        <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-900">{{ totals.total_units }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-900">{{ formatCurrency(totals.tuition + totals.lab_fees) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>

            <!-- Notes -->
            <Card v-if="curriculum.notes">
              <CardHeader>
                <CardTitle>Notes</CardTitle>
              </CardHeader>
              <CardContent>
                <p class="whitespace-pre-line text-gray-700">{{ curriculum.notes }}</p>
              </CardContent>
            </Card>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <!-- Fee Structure -->
            <Card>
              <CardHeader>
                <CardTitle>Fee Structure</CardTitle>
                <CardDescription>Per-student fees for this curriculum</CardDescription>
              </CardHeader>
              <CardContent class="space-y-3">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Tuition per Unit:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(curriculum.tuition_per_unit) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Lab Fee (per subject):</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(curriculum.lab_fee) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Registration Fee:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(curriculum.registration_fee) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Miscellaneous Fee:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(curriculum.misc_fee) }}</span>
                </div>
              </CardContent>
            </Card>

            <!-- Assessment Summary -->
            <Card>
              <CardHeader>
                <CardTitle>Assessment Summary</CardTitle>
              </CardHeader>
              <CardContent class="space-y-3">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Tuition Fee:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(totals.tuition) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Lab Fees:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(totals.lab_fees) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Registration:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(curriculum.registration_fee) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Miscellaneous:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(curriculum.misc_fee) }}</span>
                </div>
                <div class="border-t pt-3">
                  <div class="flex justify-between">
                    <span class="font-semibold text-gray-900">Total Assessment:</span>
                    <span class="text-lg font-bold text-blue-600">
                      {{ formatCurrency(totals.total_assessment) }}
                    </span>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Payment Terms -->
            <Card>
              <CardHeader>
                <CardTitle>Payment Terms</CardTitle>
                <CardDescription>{{ curriculum.term_count }} payment periods</CardDescription>
              </CardHeader>
              <CardContent class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Upon Registration:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(paymentTerms.upon_registration) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Prelim:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(paymentTerms.prelim) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Midterm:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(paymentTerms.midterm) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Semi-Final:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(paymentTerms.semi_final) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Final:</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(paymentTerms.final) }}</span>
                </div>
              </CardContent>
            </Card>

            <!-- Metadata -->
            <Card>
              <CardHeader>
                <CardTitle>Metadata</CardTitle>
              </CardHeader>
              <CardContent class="space-y-2 text-sm">
                <div>
                  <p class="text-gray-600">Created:</p>
                  <p class="font-medium text-gray-900">{{ formatDate(curriculum.created_at) }}</p>
                </div>
                <div>
                  <p class="text-gray-600">Curriculum ID:</p>
                  <p class="font-mono text-xs text-gray-900">{{ curriculum.id }}</p>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>