<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ArrowLeft, Save, User, FileText, Trash2, Plus, AlertCircle } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Student {
    id: number;
    student_id: string;
    last_name: string;
    first_name: string;
    middle_initial: string;
    name: string;
    email: string;
    course: string;
    year_level: string;
    status: string;
    birthday: string;
    phone: string;
    address: string;
}

interface Assessment {
    id: number;
    assessment_number: string;
    year_level: string;
    semester: string;
    school_year: string;
    tuition_fee: number;
    other_fees: number;
    total_assessment: number;
    subjects: Array<{
        id: number;
        units: number;
        amount: number;
    }>;
    fee_breakdown: Array<{
        id: number;
        amount: number;
    }>;
    status: string;
}

interface Subject {
    id: number;
    code: string;
    name: string;
    units: number;
    price_per_unit: number;
    has_lab: boolean;
    lab_fee: number;
    total_cost: number;
}

interface Fee {
    id: number;
    name: string;
    category: string;
    amount: number;
}

interface Props {
    student: Student;
    assessment: Assessment;
    subjects: Subject[];
    fees: Fee[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: props.student.name, href: route('student-fees.show', props.student.id) },
    { title: 'Edit' },
];

// Active tab
const activeTab = ref('student-info');

// Student Information Form
const studentForm = useForm({
    last_name: props.student.last_name,
    first_name: props.student.first_name,
    middle_initial: props.student.middle_initial || '',
    email: props.student.email,
    birthday: props.student.birthday?.split('T')[0] || '',
    phone: props.student.phone || '',
    address: props.student.address || '',
    student_id: props.student.student_id,
    course: props.student.course,
    year_level: props.student.year_level,
    status: props.student.status,
});

// Assessment Form
const assessmentForm = useForm({
    year_level: props.assessment.year_level,
    semester: props.assessment.semester,
    school_year: props.assessment.school_year,
    subjects: [...props.assessment.subjects],
    other_fees: [...props.assessment.fee_breakdown],
});

// Options
const yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
const semesters = ['1st Sem', '2nd Sem', 'Summer'];
const courses = [
    'BS Electrical Engineering Technology',
    'BS Electronics Engineering Technology',
];
const statuses = [
    { value: 'active', label: 'Active' },
    { value: 'graduated', label: 'Graduated' },
    { value: 'dropped', label: 'Dropped' },
];

// Calculate totals
const tuitionTotal = computed(() => {
    return assessmentForm.subjects.reduce((sum, s) => sum + parseFloat(String(s.amount || 0)), 0);
});

const otherFeesTotal = computed(() => {
    return assessmentForm.other_fees.reduce((sum, f) => sum + parseFloat(String(f.amount || 0)), 0);
});

const grandTotal = computed(() => {
    return tuitionTotal.value + otherFeesTotal.value;
});

// Subject management
const addSubject = (subject: Subject) => {
    const exists = assessmentForm.subjects.find(s => s.id === subject.id);
    if (!exists) {
        assessmentForm.subjects.push({
            id: subject.id,
            units: subject.units,
            amount: parseFloat(String(subject.total_cost)),
        });
    }
};

const removeSubject = (index: number) => {
    assessmentForm.subjects.splice(index, 1);
};

const getSubjectDetails = (subjectId: number) => {
    return props.subjects.find(s => s.id === subjectId);
};

// Fee management
const addFee = (fee: Fee) => {
    const exists = assessmentForm.other_fees.find(f => f.id === fee.id);
    if (!exists) {
        assessmentForm.other_fees.push({
            id: fee.id,
            amount: parseFloat(String(fee.amount)),
        });
    }
};

const removeFee = (index: number) => {
    assessmentForm.other_fees.splice(index, 1);
};

const getFeeDetails = (feeId: number) => {
    return props.fees.find(f => f.id === feeId);
};

// Available subjects/fees (not already selected)
const availableSubjects = computed(() => {
    const selectedIds = assessmentForm.subjects.map(s => s.id);
    return props.subjects.filter(s => !selectedIds.includes(s.id));
});

const availableFees = computed(() => {
    const selectedIds = assessmentForm.other_fees.map(f => f.id);
    return props.fees.filter(f => !selectedIds.includes(f.id));
});

// Submit functions
const submitStudentInfo = () => {
    studentForm.put(route('student-fees.update', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Success handled by backend redirect
        },
    });
};

const submitAssessment = () => {
    assessmentForm.put(route('student-fees.update', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Success handled by backend redirect
        },
    });
};

// Format currency
const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

// Get status color
const getStatusColor = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-500';
        case 'graduated':
            return 'bg-blue-500';
        case 'dropped':
            return 'bg-red-500';
        default:
            return 'bg-gray-500';
    }
};
</script>

<template>
    <Head :title="`Edit - ${student.name}`" />

    <AppLayout>
        <div class="space-y-6 max-w-6xl mx-auto p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link :href="route('student-fees.show', student.id)">
                    <Button variant="outline" size="sm" class="flex items-center gap-2">
                        <ArrowLeft class="w-4 h-4" />
                        Back
                    </Button>
                </Link>
                <div class="flex-1">
                    <h1 class="text-3xl font-bold">Edit Student Information</h1>
                    <p class="text-gray-600 mt-2">
                        {{ student.name }} - {{ student.student_id }}
                    </p>
                </div>
            </div>

            <!-- Warning Banner -->
            <div class="bg-yellow-50 border-2 border-yellow-200 rounded-lg p-4 flex items-start gap-3">
                <AlertCircle class="w-5 h-5 text-yellow-600 mt-0.5" />
                <div class="flex-1">
                    <h3 class="font-semibold text-yellow-900">Important Notice</h3>
                    <p class="text-sm text-yellow-800 mt-1">
                        Changes to assessment details will recalculate the student's balance. 
                        Make sure all information is correct before saving.
                    </p>
                </div>
            </div>

            <!-- Tabs -->
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="student-info" class="flex items-center gap-2">
                        <User class="w-4 h-4" />
                        Student Information
                    </TabsTrigger>
                    <TabsTrigger value="assessment" class="flex items-center gap-2">
                        <FileText class="w-4 h-4" />
                        Assessment Details
                    </TabsTrigger>
                </TabsList>

                <!-- Student Information Tab -->
                <TabsContent value="student-info" class="space-y-6 mt-6">
                    <form @submit.prevent="submitStudentInfo" class="space-y-6">
                        <!-- Personal Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Personal Information</CardTitle>
                                <CardDescription>Update student's personal details</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="space-y-2">
                                        <Label for="last_name">Last Name *</Label>
                                        <Input
                                            id="last_name"
                                            v-model="studentForm.last_name"
                                            required
                                        />
                                        <p v-if="studentForm.errors.last_name" class="text-sm text-red-500">
                                            {{ studentForm.errors.last_name }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="first_name">First Name *</Label>
                                        <Input
                                            id="first_name"
                                            v-model="studentForm.first_name"
                                            required
                                        />
                                        <p v-if="studentForm.errors.first_name" class="text-sm text-red-500">
                                            {{ studentForm.errors.first_name }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="middle_initial">Middle Initial</Label>
                                        <Input
                                            id="middle_initial"
                                            v-model="studentForm.middle_initial"
                                            maxlength="10"
                                        />
                                        <p v-if="studentForm.errors.middle_initial" class="text-sm text-red-500">
                                            {{ studentForm.errors.middle_initial }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="email">Email *</Label>
                                        <Input
                                            id="email"
                                            v-model="studentForm.email"
                                            type="email"
                                            required
                                        />
                                        <p v-if="studentForm.errors.email" class="text-sm text-red-500">
                                            {{ studentForm.errors.email }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="birthday">Birthday *</Label>
                                        <Input
                                            id="birthday"
                                            v-model="studentForm.birthday"
                                            type="date"
                                            required
                                        />
                                        <p v-if="studentForm.errors.birthday" class="text-sm text-red-500">
                                            {{ studentForm.errors.birthday }}
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
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="phone">Phone Number</Label>
                                        <Input
                                            id="phone"
                                            v-model="studentForm.phone"
                                            placeholder="09171234567"
                                        />
                                        <p v-if="studentForm.errors.phone" class="text-sm text-red-500">
                                            {{ studentForm.errors.phone }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="address">Address</Label>
                                        <Input
                                            id="address"
                                            v-model="studentForm.address"
                                            placeholder="Complete address"
                                        />
                                        <p v-if="studentForm.errors.address" class="text-sm text-red-500">
                                            {{ studentForm.errors.address }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Academic Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Academic Information</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="student_id">Student ID *</Label>
                                        <Input
                                            id="student_id"
                                            v-model="studentForm.student_id"
                                            required
                                            readonly
                                            class="bg-gray-50"
                                        />
                                        <p class="text-xs text-gray-500">Student ID cannot be changed</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="course">Course *</Label>
                                        <select
                                            id="course"
                                            v-model="studentForm.course"
                                            required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        >
                                            <option value="">Select course</option>
                                            <option v-for="course in courses" :key="course" :value="course">
                                                {{ course }}
                                            </option>
                                        </select>
                                        <p v-if="studentForm.errors.course" class="text-sm text-red-500">
                                            {{ studentForm.errors.course }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="year_level">Year Level *</Label>
                                        <select
                                            id="year_level"
                                            v-model="studentForm.year_level"
                                            required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        >
                                            <option value="">Select year level</option>
                                            <option v-for="year in yearLevels" :key="year" :value="year">
                                                {{ year }}
                                            </option>
                                        </select>
                                        <p v-if="studentForm.errors.year_level" class="text-sm text-red-500">
                                            {{ studentForm.errors.year_level }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="status">Status *</Label>
                                        <select
                                            id="status"
                                            v-model="studentForm.status"
                                            required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        >
                                            <option value="">Select status</option>
                                            <option 
                                                v-for="status in statuses" 
                                                :key="status.value" 
                                                :value="status.value"
                                            >
                                                {{ status.label }}
                                            </option>
                                        </select>
                                        <p v-if="studentForm.errors.status" class="text-sm text-red-500">
                                            {{ studentForm.errors.status }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-4">
                            <Link :href="route('student-fees.show', student.id)">
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </Link>
                            <Button 
                                type="submit" 
                                :disabled="studentForm.processing"
                                class="min-w-[150px]"
                            >
                                <Save class="w-4 h-4 mr-2" />
                                {{ studentForm.processing ? 'Saving...' : 'Save Changes' }}
                            </Button>
                        </div>
                    </form>
                </TabsContent>

                <!-- Assessment Details Tab -->
                <TabsContent value="assessment" class="space-y-6 mt-6">
                    <form @submit.prevent="submitAssessment" class="space-y-6">
                        <!-- Assessment Info -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Assessment Information</CardTitle>
                                <CardDescription>
                                    Assessment Number: {{ assessment.assessment_number }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="space-y-2">
                                        <Label for="assessment_year_level">Year Level *</Label>
                                        <select
                                            id="assessment_year_level"
                                            v-model="assessmentForm.year_level"
                                            required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        >
                                            <option value="">Select year level</option>
                                            <option v-for="year in yearLevels" :key="year" :value="year">
                                                {{ year }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="semester">Semester *</Label>
                                        <select
                                            id="semester"
                                            v-model="assessmentForm.semester"
                                            required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        >
                                            <option value="">Select semester</option>
                                            <option v-for="sem in semesters" :key="sem" :value="sem">
                                                {{ sem }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="school_year">School Year *</Label>
                                        <Input
                                            id="school_year"
                                            v-model="assessmentForm.school_year"
                                            required
                                            placeholder="2025-2026"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Subjects Section -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Subjects & Tuition</CardTitle>
                                <CardDescription>Manage enrolled subjects</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Current Subjects -->
                                <div class="space-y-2">
                                    <Label>Enrolled Subjects</Label>
                                    <div class="space-y-2">
                                        <div
                                            v-for="(subject, index) in assessmentForm.subjects"
                                            :key="index"
                                            class="flex items-center justify-between p-3 border rounded-lg bg-gray-50"
                                        >
                                            <div class="flex-1">
                                                <p class="font-medium">
                                                    {{ getSubjectDetails(subject.id)?.code }} - 
                                                    {{ getSubjectDetails(subject.id)?.name }}
                                                </p>
                                                <p class="text-sm text-gray-600">
                                                    {{ subject.units }} units
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                <Input
                                                    v-model="subject.amount"
                                                    type="number"
                                                    step="0.01"
                                                    class="w-32"
                                                />
                                                <button
                                                    type="button"
                                                    class="text-red-500 hover:text-red-700"
                                                    @click="removeSubject(index)"
                                                >
                                                    <Trash2 class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                        <div v-if="assessmentForm.subjects.length === 0" class="text-center py-8 text-gray-500 border rounded-lg">
                                            No subjects enrolled
                                        </div>
                                    </div>
                                </div>

                                <!-- Add Subject -->
                                <div v-if="availableSubjects.length > 0" class="space-y-2">
                                    <Label>Add Subject</Label>
                                    <div class="grid grid-cols-1 gap-2 max-h-48 overflow-y-auto border rounded-lg p-2">
                                        <div
                                            v-for="subject in availableSubjects"
                                            :key="subject.id"
                                            class="flex items-center justify-between p-3 hover:bg-gray-50 rounded cursor-pointer border"
                                            @click="addSubject(subject)"
                                        >
                                            <div>
                                                <p class="font-medium">{{ subject.code }} - {{ subject.name }}</p>
                                                <p class="text-sm text-gray-600">
                                                    {{ subject.units }} units × {{ formatCurrency(subject.price_per_unit) }}
                                                </p>
                                            </div>
                                            <Button type="button" size="sm" variant="outline">
                                                <Plus class="w-4 h-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tuition Total -->
                                <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                                    <span class="font-medium text-lg">Total Tuition Fee</span>
                                    <span class="text-2xl font-bold text-blue-600">{{ formatCurrency(tuitionTotal) }}</span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Other Fees Section -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Other Fees</CardTitle>
                                <CardDescription>Manage additional fees</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Current Fees -->
                                <div class="space-y-2">
                                    <Label>Applied Fees</Label>
                                    <div class="space-y-2">
                                        <div
                                            v-for="(fee, index) in assessmentForm.other_fees"
                                            :key="index"
                                            class="flex items-center justify-between p-3 border rounded-lg bg-gray-50"
                                        >
                                            <div class="flex-1">
                                                <p class="font-medium">{{ getFeeDetails(fee.id)?.name }}</p>
                                                <p class="text-sm text-gray-600">
                                                    {{ getFeeDetails(fee.id)?.category }}
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                <Input
                                                    v-model="fee.amount"
                                                    type="number"
                                                    step="0.01"
                                                    class="w-32"
                                                />
                                                <button
                                                    type="button"
                                                    class="text-red-500 hover:text-red-700"
                                                    @click="removeFee(index)"
                                                >
                                                    <Trash2 class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                        <div v-if="assessmentForm.other_fees.length === 0" class="text-center py-8 text-gray-500 border rounded-lg">
                                            No additional fees
                                        </div>
                                    </div>
                                </div>

                                <!-- Add Fee -->
                                <div v-if="availableFees.length > 0" class="space-y-2">
                                    <Label>Add Fee</Label>
                                    <div class="grid grid-cols-1 gap-2 max-h-48 overflow-y-auto border rounded-lg p-2">
                                        <div
                                            v-for="fee in availableFees"
                                            :key="fee.id"
                                            class="flex items-center justify-between p-3 hover:bg-gray-50 rounded cursor-pointer border"
                                            @click="addFee(fee)"
                                        >
                                            <div>
                                                <p class="font-medium">{{ fee.name }}</p>
                                                <p class="text-sm text-gray-600">{{ fee.category }}</p>
                                            </div>
                                            <Button type="button" size="sm" variant="outline">
                                                <Plus class="w-4 h-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Other Fees Total -->
                                <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                                    <span class="font-medium text-lg">Total Other Fees</span>
                                    <span class="text-2xl font-bold text-blue-600">{{ formatCurrency(otherFeesTotal) }}</span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Grand Total -->
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg p-6 text-white shadow-lg">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-blue-100 text-sm uppercase tracking-wide mb-1">Total Assessment</p>
                                    <p class="text-4xl font-bold">{{ formatCurrency(grandTotal) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-blue-100 text-sm">Tuition: {{ formatCurrency(tuitionTotal) }}</p>
                                    <p class="text-blue-100 text-sm">Other Fees: {{ formatCurrency(otherFeesTotal) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-4">
                            <Link :href="route('student-fees.show', student.id)">
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </Link>
                            <Button 
                                type="submit" 
                                :disabled="assessmentForm.processing"
                                class="min-w-[150px]"
                            >
                                <Save class="w-4 h-4 mr-2" />
                                {{ assessmentForm.processing ? 'Saving...' : 'Save Changes' }}
                            </Button>
                        </div>
                    </form>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>