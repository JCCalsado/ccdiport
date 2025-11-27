<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ArrowLeft, User, Phone, GraduationCap, AlertCircle, CheckCircle } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

interface Program {
    id: number;
    code: string;
    name: string;
    full_name: string;
    major?: string;
}

interface Props {
    programs: Program[];
    legacyCourses: string[];
    yearLevels: string[];
    semesters: string[];
    schoolYears: string[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: 'Add Student' },
];

const form = useForm({
    // Personal Information
    last_name: '',
    first_name: '',
    middle_initial: '',
    email: '',
    birthday: '',
    
    // Contact Information
    phone: '',
    address: '',
    
    // Academic Information - OBE
    program_id: null as number | null,
    year_level: '',
    semester: '1st Sem',
    school_year: props.schoolYears[0] || '',
    
    // Academic Information - Legacy
    course: '',
    
    // Options
    auto_generate_assessment: true,
    student_id: '', // Optional - will be auto-generated if empty
});

// State for curriculum preview
const curriculumPreview = ref<any>(null);
const isLoadingPreview = ref(false);
const useOBECurriculum = ref(true);

// Computed: total units from curriculum
const totalUnits = computed(() => {
    if (!curriculumPreview.value?.courses) return 0;
    return curriculumPreview.value.courses.reduce((sum: number, course: any) => 
        sum + (course.total_units || 0), 0
    );
});

// Fetch curriculum preview when program/term changes
const fetchCurriculumPreview = async () => {
    if (!form.program_id || !form.year_level || !form.semester || !form.school_year) {
        curriculumPreview.value = null;
        return;
    }

    isLoadingPreview.value = true;
    try {
        const response = await axios.post(route('student-fees.curriculum.preview'), {
            program_id: form.program_id,
            year_level: form.year_level,
            semester: form.semester,
            school_year: form.school_year,
        });
        
        curriculumPreview.value = response.data.curriculum;
    } catch (error: any) {
        console.error('Failed to fetch curriculum preview:', error);
        curriculumPreview.value = null;
};

// Watch for program changes
watch(() => form.program_id, (newVal) => {
    if (newVal) {
        useOBECurriculum.value = true;
        form.course = ''; // Clear legacy course
        fetchCurriculumPreview();
    }
});

// Watch for term changes
watch([() => form.year_level, () => form.semester, () => form.school_year], () => {
    if (form.program_id) {
        fetchCurriculumPreview();
    }
});

// Toggle between OBE and Legacy
const toggleCurriculumMode = () => {
    useOBECurriculum.value = !useOBECurriculum.value;
    if (!useOBECurriculum.value) {
        form.program_id = null;
        curriculumPreview.value = null;
        form.auto_generate_assessment = false;
    } else {
        form.course = '';
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

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
    <Head title="Add New Student" />

    <AppLayout>
        <div class="space-y-6 max-w-5xl mx-auto p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('student-fees.index')">
                        <Button variant="outline" size="sm" class="flex items-center gap-2">
                            <ArrowLeft class="w-4 h-4" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-3xl font-bold">Add New Student</h1>
                        <p class="text-gray-600 mt-1">
                            Register a new student in the system
                        </p>
                    </div>
                </div>
            </div>

            <!-- Global Error Alert -->
            <Alert v-if="form.errors.error" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>{{ form.errors.error }}</AlertDescription>
            </Alert>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Personal Information Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <User class="w-5 h-5" />
                            Personal Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Last Name -->
                            <div class="space-y-2">
                                <Label for="last_name" required>Last Name</Label>
                                <Input
                                    id="last_name"
                                    v-model="form.last_name"
                                    required
                                    placeholder="Dela Cruz"
                                    :class="{ 'border-red-500': form.errors.last_name }"
                                />
                                <p v-if="form.errors.last_name" class="text-sm text-red-500">
                                    {{ form.errors.last_name }}
                                </p>
                            </div>

                            <!-- First Name -->
                            <div class="space-y-2">
                                <Label for="first_name" required>First Name</Label>
                                <Input
                                    id="first_name"
                                    v-model="form.first_name"
                                    required
                                    placeholder="Juan"
                                    :class="{ 'border-red-500': form.errors.first_name }"
                                />
                                <p v-if="form.errors.first_name" class="text-sm text-red-500">
                                    {{ form.errors.first_name }}
                                </p>
                            </div>

                            <!-- Middle Initial -->
                            <div class="space-y-2">
                                <Label for="middle_initial">Middle Initial</Label>
                                <Input
                                    id="middle_initial"
                                    v-model="form.middle_initial"
                                    maxlength="10"
                                    placeholder="P."
                                    :class="{ 'border-red-500': form.errors.middle_initial }"
                                />
                                <p v-if="form.errors.middle_initial" class="text-sm text-red-500">
                                    {{ form.errors.middle_initial }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Email -->
                            <div class="space-y-2">
                                <Label for="email" required>Email Address</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    placeholder="student@ccdi.edu.ph"
                                    :class="{ 'border-red-500': form.errors.email }"
                                />
                                <p v-if="form.errors.email" class="text-sm text-red-500">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <!-- Birthday -->
                            <div class="space-y-2">
                                <Label for="birthday" required>Birthday</Label>
                                <Input
                                    id="birthday"
                                    v-model="form.birthday"
                                    type="date"
                                    required
                                    :class="{ 'border-red-500': form.errors.birthday }"
                                />
                                <p v-if="form.errors.birthday" class="text-sm text-red-500">
                                    {{ form.errors.birthday }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Contact Information Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Phone class="w-5 h-5" />
                            Contact Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Phone -->
                            <div class="space-y-2">
                                <Label for="phone" required>Phone Number</Label>
                                <Input
                                    id="phone"
                                    v-model="form.phone"
                                    required
                                    placeholder="09171234567"
                                    :class="{ 'border-red-500': form.errors.phone }"
                                />
                                <p v-if="form.errors.phone" class="text-sm text-red-500">
                                    {{ form.errors.phone }}
                                </p>
                            </div>

                            <!-- Address -->
                            <div class="space-y-2">
                                <Label for="address" required>Address</Label>
                                <Input
                                    id="address"
                                    v-model="form.address"
                                    required
                                    placeholder="Sorsogon City"
                                    :class="{ 'border-red-500': form.errors.address }"
                                />
                                <p v-if="form.errors.address" class="text-sm text-red-500">
                                    {{ form.errors.address }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Academic Information Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <GraduationCap class="w-5 h-5" />
                                Academic Information
                            </div>
                            <Button 
                                type="button"
                                variant="outline" 
                                size="sm"
                                @click="toggleCurriculumMode"
                            >
                                {{ useOBECurriculum ? 'Use Legacy Course' : 'Use OBE Program' }}
                            </Button>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- OBE Curriculum Mode -->
                        <div v-if="useOBECurriculum" class="space-y-4">
                            <!-- Program Selection -->
                            <div class="space-y-2">
                                <Label for="program_id">Program (OBE Curriculum)</Label>
                                <select
                                    id="program_id"
                                    v-model="form.program_id"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    :class="{ 'border-red-500': form.errors.program_id }"
                                >
                                    <option :value="null">Select Program</option>
                                    <option v-for="program in programs" :key="program.id" :value="program.id">
                                        {{ program.full_name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.program_id" class="text-sm text-red-500">
                                    {{ form.errors.program_id }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Year Level -->
                                <div class="space-y-2">
                                    <Label for="year_level" required>Year Level</Label>
                                    <select
                                        id="year_level"
                                        v-model="form.year_level"
                                        required
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        :class="{ 'border-red-500': form.errors.year_level }"
                                    >
                                        <option value="">Select Year Level</option>
                                        <option v-for="level in yearLevels" :key="level" :value="level">
                                            {{ level }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.year_level" class="text-sm text-red-500">
                                        {{ form.errors.year_level }}
                                    </p>
                                </div>

                                <!-- Semester -->
                                <div class="space-y-2">
                                    <Label for="semester">Semester</Label>
                                    <select
                                        id="semester"
                                        v-model="form.semester"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                        <option v-for="sem in semesters" :key="sem" :value="sem">
                                            {{ sem }}
                                        </option>
                                    </select>
                                </div>

                                <!-- School Year -->
                                <div class="space-y-2">
                                    <Label for="school_year">School Year</Label>
                                    <select
                                        id="school_year"
                                        v-model="form.school_year"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                        <option v-for="sy in schoolYears" :key="sy" :value="sy">
                                            {{ sy }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Auto-generate Assessment Toggle -->
                            <div class="flex items-center space-x-2 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <input
                                    id="auto_generate"
                                    v-model="form.auto_generate_assessment"
                                    type="checkbox"
                                    class="rounded"
                                    :disabled="!form.program_id"
                                />
                                <Label for="auto_generate" class="cursor-pointer">
                                    Automatically generate assessment from OBE curriculum
                                </Label>
                            </div>

                            <!-- Curriculum Preview -->
                            <div v-if="isLoadingPreview" class="p-4 bg-gray-50 rounded-lg border">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                                    <p class="text-gray-600">Loading curriculum preview...</p>
                                </div>
                            </div>

                            <div v-else-if="curriculumPreview" class="p-4 bg-green-50 rounded-lg border border-green-200">
                                <div class="flex items-start gap-2 mb-3">
                                    <CheckCircle class="w-5 h-5 text-green-600 mt-0.5" />
                                    <div>
                                        <h4 class="font-semibold text-green-900">Assessment Preview</h4>
                                        <p class="text-sm text-green-700">Curriculum found for {{ curriculumPreview.term }}</p>
                                    </div>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-gray-600">Program:</p>
                                            <p class="font-medium">{{ curriculumPreview.program }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Total Units:</p>
                                            <p class="font-medium">{{ totalUnits }}</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-2 mt-2 border-t">
                                        <div>
                                            <p class="text-gray-600">Tuition Fee:</p>
                                            <p class="font-medium">{{ formatCurrency(curriculumPreview.totals.tuition) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Lab Fees:</p>
                                            <p class="font-medium">{{ formatCurrency(curriculumPreview.totals.lab_fees) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Registration:</p>
                                            <p class="font-medium">{{ formatCurrency(curriculumPreview.totals.registration_fee) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Misc Fee:</p>
                                            <p class="font-medium">{{ formatCurrency(curriculumPreview.totals.misc_fee) }}</p>
                                        </div>
                                    </div>
                                    <div class="pt-2 mt-2 border-t">
                                        <p class="text-lg font-bold text-green-700">
                                            Total Assessment: {{ formatCurrency(curriculumPreview.totals.total_assessment) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Alert v-else-if="form.program_id && form.year_level" variant="warning">
                                <AlertCircle class="h-4 w-4" />
                                <AlertDescription>
                                    No curriculum found for the selected term. The student will be created without an initial assessment.
                                </AlertDescription>
                            </Alert>
                        </div>

                        <!-- Legacy Course Mode -->
                        <div v-else class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Legacy Course -->
                                <div class="space-y-2">
                                    <Label for="course">Course (Legacy)</Label>
                                    <select
                                        id="course"
                                        v-model="form.course"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        :class="{ 'border-red-500': form.errors.course }"
                                    >
                                        <option value="">Select Course</option>
                                        <option v-for="course in legacyCourses" :key="course" :value="course">
                                            {{ course }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.course" class="text-sm text-red-500">
                                        {{ form.errors.course }}
                                    </p>
                                </div>

                                <!-- Year Level -->
                                <div class="space-y-2">
                                    <Label for="year_level_legacy" required>Year Level</Label>
                                    <select
                                        id="year_level_legacy"
                                        v-model="form.year_level"
                                        required
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        :class="{ 'border-red-500': form.errors.year_level }"
                                    >
                                        <option value="">Select Year Level</option>
                                        <option v-for="level in yearLevels" :key="level" :value="level">
                                            {{ level }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.year_level" class="text-sm text-red-500">
                                        {{ form.errors.year_level }}
                                    </p>
                                </div>
                            </div>

                            <Alert>
                                <AlertCircle class="h-4 w-4" />
                                <AlertDescription>
                                    Legacy course mode: You'll need to manually create the student's assessment after registration.
                                </AlertDescription>
                            </Alert>
                        </div>
                    </CardContent>
                </Card>

                <!-- Password Information Alert -->
                <Alert>
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        <strong>Default Password:</strong> The student's initial password will be set to <code class="px-1 py-0.5 bg-gray-100 rounded">password</code>. 
                        They should change it after their first login.
                    </AlertDescription>
                </Alert>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t">
                    <Link :href="route('student-fees.index')">
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </Link>
                    <Button 
                        type="submit" 
                        :disabled="form.processing"
                        class="min-w-[200px]"
                    >
                        <span v-if="form.processing">Adding Student...</span>
                        <span v-else>Add Student</span>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>