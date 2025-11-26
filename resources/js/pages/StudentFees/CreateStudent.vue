<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, AlertCircle } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

interface Program {
  id: number;
  code: string;
  name: string;
}

interface CurriculumPreview {
  id: number;
  program: string;
  term: string;
  tuition_per_unit: number;
  lab_fee: number;
  registration_fee: number;
  misc_fee: number;
  courses: Array<{
    code: string;
    title: string;
    lec_units: number;
    lab_units: number;
    total_units: number;
    tuition: number;
    lab_fee: number;
    total: number;
  }>;
  totals: {
    tuition: number;
    lab_fees: number;
    registration_fee: number;
    misc_fee: number;
    total_assessment: number;
  };
  payment_terms: Record<string, number>;
}

interface Props {
  programs: Program[];
  legacyCourses: string[];
  yearLevels: string[];
}

const props = defineProps<Props>();

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Student Fee Management', href: route('student-fees.index') },
  { title: 'Add Student' },
];

const form = useForm({
  last_name: '',
  first_name: '',
  middle_initial: '',
  email: '',
  password: 'password',
  password_confirmation: 'password',
  birthday: '',
  year_level: '',
  course: '',
  address: '',
  phone: '',
  student_id: '',
  program_id: null as number | null,
  semester: '1st Sem',
  school_year: '2025-2026',
  auto_generate_assessment: true,
});

const curriculumPreview = ref<CurriculumPreview | null>(null);
const loadingPreview = ref(false);
const previewError = ref<string | null>(null);

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount);
};

const totalUnits = computed(() => {
  if (!curriculumPreview.value?.courses) return 0;
  return curriculumPreview.value.courses.reduce(
    (sum, course) => sum + course.total_units,
    0
  );
});

const yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
const semesters = ['1st Sem', '2nd Sem', 'Summer'];

// Handle program selection
const handleProgramChange = () => {
  // Clear legacy course if program is selected
  if (form.program_id) {
    form.course = '';
  }
  fetchCurriculumPreview();
};

// Fetch curriculum preview when term changes
const fetchCurriculumPreview = async () => {
  if (!form.program_id || !form.year_level || !form.semester || !form.school_year) {
    curriculumPreview.value = null;
    return;
  }

  loadingPreview.value = true;
  previewError.value = null;

  try {
    const response = await axios.post<{ curriculum: CurriculumPreview }>(
      route('student-fees.curriculum.preview'),
      {
        program_id: form.program_id,
        year_level: form.year_level,
        semester: form.semester,
        school_year: form.school_year,
      }
    );

    curriculumPreview.value = response.data.curriculum;
    previewError.value = null;
  } catch (error: any) {
    console.error('Failed to fetch curriculum preview:', error);
    curriculumPreview.value = null;
    
    if (error.response?.status === 404) {
      previewError.value = 'No curriculum found for the selected term. Please contact the administrator.';
    } else {
      previewError.value = 'Failed to load curriculum preview. Please try again.';
    }
  } finally {
    loadingPreview.value = false;
  }
};

// Watch for changes in term selection
watch([() => form.program_id, () => form.year_level, () => form.semester, () => form.school_year], () => {
  fetchCurriculumPreview();
});

const submit = () => {
  form.post(route('student-fees.store-student'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
    },
  });
};
</script>

<template>
  <Head title="Add Student" />

  <AppLayout>
    <div class="space-y-6 max-w-4xl mx-auto p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="flex items-center gap-4">
        <Link :href="route('student-fees.index')">
          <Button variant="outline" size="sm" class="flex items-center gap-2">
            <ArrowLeft class="w-4 h-4" />
            Back
          </Button>
        </Link>
        <div>
          <h1 class="text-3xl font-bold">Add New Student</h1>
          <p class="text-gray-600 mt-2">
            Register a new student in the system
          </p>
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- Personal Information -->
        <Card>
          <CardHeader>
            <CardTitle>Personal Information</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="space-y-2">
                <Label for="last_name">Last Name *</Label>
                <Input
                  id="last_name"
                  v-model="form.last_name"
                  required
                  placeholder="Dela Cruz"
                />
                <p v-if="form.errors.last_name" class="text-sm text-red-500">
                  {{ form.errors.last_name }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="first_name">First Name *</Label>
                <Input
                  id="first_name"
                  v-model="form.first_name"
                  required
                  placeholder="Juan"
                />
                <p v-if="form.errors.first_name" class="text-sm text-red-500">
                  {{ form.errors.first_name }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="middle_initial">Middle Initial</Label>
                <Input
                  id="middle_initial"
                  v-model="form.middle_initial"
                  maxlength="10"
                  placeholder="P"
                />
                <p v-if="form.errors.middle_initial" class="text-sm text-red-500">
                  {{ form.errors.middle_initial }}
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
              <div class="space-y-2">
                <Label for="email">Email *</Label>
                <Input
                  id="email"
                  v-model="form.email"
                  type="email"
                  required
                  placeholder="student@ccdi.edu.ph"
                />
                <p v-if="form.errors.email" class="text-sm text-red-500">
                  {{ form.errors.email }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="birthday">Birthday *</Label>
                <Input
                  id="birthday"
                  v-model="form.birthday"
                  type="date"
                  required
                />
                <p v-if="form.errors.birthday" class="text-sm text-red-500">
                  {{ form.errors.birthday }}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Contact Information -->
        <Card>
          <CardHeader>
            <CardTitle>Contact Information</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="phone">Phone Number *</Label>
                <Input
                  id="phone"
                  v-model="form.phone"
                  required
                  placeholder="09171234567"
                />
                <p v-if="form.errors.phone" class="text-sm text-red-500">
                  {{ form.errors.phone }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="address">Address *</Label>
                <Input
                  id="address"
                  v-model="form.address"
                  required
                  placeholder="Sorsogon City"
                />
                <p v-if="form.errors.address" class="text-sm text-red-500">
                  {{ form.errors.address }}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Academic Information Section -->
        <Card>
          <CardHeader>
            <CardTitle>Academic Information</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <!-- Program Selection (OBE) -->
            <div class="space-y-2">
              <Label for="program_id">Program (OBE Curriculum)</Label>
              <select
                id="program_id"
                v-model="form.program_id"
                @change="handleProgramChange"
                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
              >
                <option :value="null">Select Program (Optional - for OBE)</option>
                <option v-for="program in programs" :key="program.id" :value="program.id">
                  {{ program.name }}
                </option>
              </select>
              <p class="text-xs text-gray-500">
                Select a program to use OBE curriculum and auto-generate assessment
              </p>
            </div>

            <!-- Legacy Course (only if no program selected) -->
            <div v-if="!form.program_id" class="space-y-2">
              <Label for="course">Course (Legacy) *</Label>
              <select
                id="course"
                v-model="form.course"
                required
                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
              >
                <option value="">Select Course</option>
                <option v-for="course in legacyCourses" :key="course" :value="course">
                  {{ course }}
                </option>
              </select>
              <p class="text-xs text-gray-500">
                Legacy course selection (for students without OBE curriculum)
              </p>
            </div>

            <!-- Year Level -->
            <div class="space-y-2">
              <Label for="year_level">Year Level *</Label>
              <select
                id="year_level"
                v-model="form.year_level"
                required
                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
              >
                <option value="">Select Year Level</option>
                <option v-for="level in yearLevels" :key="level" :value="level">
                  {{ level }}
                </option>
              </select>
            </div>

            <!-- Semester -->
            <div class="space-y-2">
              <Label for="semester">Semester *</Label>
              <select
                id="semester"
                v-model="form.semester"
                required
                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
              >
                <option value="">Select Semester</option>
                <option v-for="sem in semesters" :key="sem" :value="sem">
                  {{ sem }}
                </option>
              </select>
            </div>

            <!-- School Year -->
            <div class="space-y-2">
              <Label for="school_year">School Year *</Label>
              <select
                id="school_year"
                v-model="form.school_year"
                required
                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
              >
                <option value="">Select School Year</option>
                <option value="2025-2026">2025-2026</option>
                <option value="2026-2027">2026-2027</option>
              </select>
            </div>

            <!-- Auto-generate Assessment Toggle -->
            <div class="flex items-center space-x-2 pt-4 border-t">
              <input
                id="auto_generate"
                v-model="form.auto_generate_assessment"
                type="checkbox"
                class="rounded"
              />
              <Label for="auto_generate" class="font-normal cursor-pointer">
                Automatically generate assessment from curriculum
              </Label>
            </div>

            <!-- Loading State -->
            <div v-if="loadingPreview" class="mt-4 p-4 bg-gray-50 rounded-lg border">
              <div class="flex items-center gap-3">
                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                <p class="text-sm text-gray-600">Loading curriculum preview...</p>
              </div>
            </div>

            <!-- Error State -->
            <div v-if="previewError" class="mt-4 p-4 bg-red-50 rounded-lg border border-red-200">
              <div class="flex items-start gap-3">
                <AlertCircle class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                <div>
                  <p class="text-sm font-medium text-red-800">{{ previewError }}</p>
                  <p class="text-xs text-red-600 mt-1">
                    Please select a different term or contact the administrator to set up the curriculum.
                  </p>
                </div>
              </div>
            </div>

            <!-- Assessment Preview (if curriculum found) -->
            <div v-if="curriculumPreview && !loadingPreview" class="mt-4 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border-2 border-blue-200 shadow-sm">
              <h4 class="font-bold text-blue-900 mb-4 text-lg flex items-center gap-2">
                ✓ Assessment Preview
              </h4>
              
              <div class="space-y-3">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <p class="text-xs text-gray-600 font-medium">Program</p>
                    <p class="text-sm font-semibold text-gray-900">{{ curriculumPreview.program }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-gray-600 font-medium">Total Units</p>
                    <p class="text-sm font-semibold text-gray-900">{{ totalUnits }}</p>
                  </div>
                </div>

                <div class="border-t border-blue-200 pt-3 space-y-2">
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-700">Tuition Fee:</span>
                    <span class="font-semibold">{{ formatCurrency(curriculumPreview.totals.tuition) }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-700">Lab Fees:</span>
                    <span class="font-semibold">{{ formatCurrency(curriculumPreview.totals.lab_fees) }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-700">Registration:</span>
                    <span class="font-semibold">{{ formatCurrency(curriculumPreview.totals.registration_fee) }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-700">Misc Fee:</span>
                    <span class="font-semibold">{{ formatCurrency(curriculumPreview.totals.misc_fee) }}</span>
                  </div>
                </div>

                <div class="border-t-2 border-blue-300 pt-3">
                  <div class="flex justify-between items-center">
                    <span class="text-base font-bold text-gray-900">Total Assessment:</span>
                    <span class="text-2xl font-black text-blue-700">
                      {{ formatCurrency(curriculumPreview.totals.total_assessment) }}
                    </span>
                  </div>
                </div>

                <p class="text-xs text-gray-600 pt-2 border-t border-blue-200">
                  ✓ This assessment will be automatically generated when you submit this form
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Password Information -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <p class="text-sm text-blue-800">
            <strong>Note:</strong> Default password will be set to "password". Student can change it after first login.
          </p>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-4">
          <Link :href="route('student-fees.index')">
            <Button type="button" variant="outline">
              Cancel
            </Button>
          </Link>
          <Button 
            type="submit" 
            :disabled="form.processing"
            class="min-w-[150px]"
          >
            {{ form.processing ? 'Adding Student...' : 'Add Student' }}
          </Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>