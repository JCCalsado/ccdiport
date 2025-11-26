<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowLeft } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import axios from 'axios';

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
    program_id: '',
    semester: '1st Sem',
    school_year: '2025-2026',
    auto_generate_assessment: true,
});

const curriculumPreview = ref(null);

const fetchCurriculumPreview = async () => {
  if (!form.program_id || !form.year_level || !form.semester || !form.school_year) {
    return;
  }

  try {
    const response = await axios.post(route('student-fees.curriculum.preview'), {
      program_id: form.program_id,
      year_level: form.year_level,
      semester: form.semester,
      school_year: form.school_year,
    });
    
    curriculumPreview.value = response.data.curriculum;
  } catch (error) {
    console.error('Failed to fetch curriculum preview:', error);
    curriculumPreview.value = null;
  }
};

const totalUnits = computed(() => {
  if (!curriculumPreview.value?.courses) return 0;
  return curriculumPreview.value.courses.reduce((sum, course) => sum + course.total_units, 0);
});

const yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
const courses = [
    'BS Electrical Engineering Technology',
    'BS Electronics Engineering Technology',
];

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
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Personal Information</h2>
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
                </div>

                <!-- Contact Information -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Contact Information</h2>
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
                </div>

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
                        class="w-full px-3 py-2 border rounded-lg"
                        >
                        <option value="">Select Program</option>
                        <option v-for="program in programs" :key="program.id" :value="program.id">
                            {{ program.name }}
                        </option>
                        </select>
                    </div>

                    <!-- Year Level -->
                    <div class="space-y-2">
                        <Label for="year_level">Year Level *</Label>
                        <select
                        id="year_level"
                        v-model="form.year_level"
                        @change="fetchAvailableTerms"
                        required
                        class="w-full px-3 py-2 border rounded-lg"
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
                        @change="fetchCurriculumPreview"
                        required
                        class="w-full px-3 py-2 border rounded-lg"
                        >
                        <option value="">Select Semester</option>
                        <option value="1st Sem">1st Semester</option>
                        <option value="2nd Sem">2nd Semester</option>
                        <option value="Summer">Summer</option>
                        </select>
                    </div>

                    <!-- School Year -->
                    <div class="space-y-2">
                        <Label for="school_year">School Year *</Label>
                        <select
                        id="school_year"
                        v-model="form.school_year"
                        @change="fetchCurriculumPreview"
                        required
                        class="w-full px-3 py-2 border rounded-lg"
                        >
                        <option value="">Select School Year</option>
                        <option value="2025-2026">2025-2026</option>
          <option value="2026-2027">2026-2027</option>
        </select>
      </div>

      <!-- Auto-generate Assessment Toggle -->
      <div class="flex items-center space-x-2">
        <input
          id="auto_generate"
          v-model="form.auto_generate_assessment"
          type="checkbox"
          class="rounded"
          checked
        />
        <Label for="auto_generate">
          Automatically generate assessment from curriculum
        </Label>
      </div>

      <!-- Assessment Preview (if curriculum found) -->
      <div v-if="curriculumPreview" class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
        <h4 class="font-semibold text-blue-900 mb-2">Assessment Preview</h4>
        <div class="space-y-1 text-sm">
          <p><strong>Program:</strong> {{ curriculumPreview.program }}</p>
          <p><strong>Total Units:</strong> {{ totalUnits }}</p>
          <p><strong>Tuition Fee:</strong> {{ formatCurrency(curriculumPreview.totals.tuition) }}</p>
          <p><strong>Lab Fees:</strong> {{ formatCurrency(curriculumPreview.totals.lab_fees) }}</p>
          <p><strong>Registration:</strong> {{ formatCurrency(curriculumPreview.totals.registration_fee) }}</p>
          <p><strong>Misc Fee:</strong> {{ formatCurrency(curriculumPreview.totals.misc_fee) }}</p>
          <p class="text-lg font-bold text-blue-700 mt-2">
            Total Assessment: {{ formatCurrency(curriculumPreview.totals.total_assessment) }}
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
                    >
                        {{ form.processing ? 'Adding Student...' : 'Add Student' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>