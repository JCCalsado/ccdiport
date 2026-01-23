<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppShell from '@/components/AppShell.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Separator } from '@/components/ui/separator'
import { Checkbox } from '@/components/ui/checkbox'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { 
  Search, 
  User, 
  BookOpen, 
  Calculator, 
  AlertCircle, 
  CheckCircle,
  FileText,
  Plus,
  Minus,
  Eye,
  Loader2
} from 'lucide-vue-next'

interface Student {
  id: number
  account_id: string
  student_id: string
  name: string
  email: string
  course: string
  year_level: string
}

interface Subject {
  id: number
  code: string
  name: string
  units: number
  price_per_unit: number
  has_lab: boolean
  lab_fee: number
  total_cost: number
}

interface Fee {
  id: number
  name: string
  category: string
  amount: number
}

interface CurriculumPreview {
  id: number
  program: string
  term: string
  courses: Array<{
    code: string
    title: string
    lec_units: number
    lab_units: number
    total_units: number
    has_lab: boolean
  }>
  totals: {
    tuition: number
    lab_fees: number
    registration_fee: number
    misc_fee: number
    total_assessment: number
  }
}

interface Props {
  students: Student[]
  yearLevels: string[]
  semesters: string[]
  schoolYears: string[]
  programs?: Array<{
    id: number
    code: string
    name: string
    full_name: string
  }>
}

const props = defineProps<Props>()

// ============================================
// STATE MANAGEMENT
// ============================================
const selectedStudent = ref<Student | null>(null)
const studentSearch = ref('')
const availableSubjects = ref<Subject[]>([])
const availableFees = ref<Fee[]>([])
const loadingStudentData = ref(false)
const showCurriculumPreview = ref(false)
const curriculumPreview = ref<CurriculumPreview | null>(null)
const loadingCurriculum = ref(false)

// ============================================
// FORM DATA
// ============================================
const form = useForm({
  account_id: '',
  year_level: '',
  semester: '',
  school_year: props.schoolYears[0] || '',
  program_id: null as number | null,
  use_curriculum: false,
  subjects: [] as Array<{
    id: number
    code: string
    name: string
    units: number
    amount: number
    selected: boolean
  }>,
  other_fees: [] as Array<{
    id: number
    name: string
    category: string
    amount: number
    selected: boolean
  }>
})

// ============================================
// COMPUTED PROPERTIES
// ============================================
const filteredStudents = computed(() => {
  if (!studentSearch.value) return props.students
  
  const search = studentSearch.value.toLowerCase()
  return props.students.filter(student => 
    student.name.toLowerCase().includes(search) ||
    student.student_id.toLowerCase().includes(search) ||
    student.account_id.toLowerCase().includes(search) ||
    student.email.toLowerCase().includes(search)
  )
})

const selectedSubjects = computed(() => 
  form.subjects.filter(s => s.selected)
)

const selectedFees = computed(() => 
  form.other_fees.filter(f => f.selected)
)

const totalTuition = computed(() => 
  selectedSubjects.value.reduce((sum, subject) => sum + subject.amount, 0)
)

const totalOtherFees = computed(() => 
  selectedFees.value.reduce((sum, fee) => sum + fee.amount, 0)
)

const grandTotal = computed(() => 
  totalTuition.value + totalOtherFees.value
)

const canSubmit = computed(() => 
  form.account_id &&
  form.year_level &&
  form.semester &&
  form.school_year &&
  (
    (form.use_curriculum && form.program_id && curriculumPreview.value) ||
    (!form.use_curriculum && selectedSubjects.value.length > 0)
  )
)

// ============================================
// METHODS
// ============================================
async function selectStudent(student: Student) {
  selectedStudent.value = student
  form.account_id = student.account_id
  form.year_level = student.year_level
  
  // Reset subjects and fees
  form.subjects = []
  form.other_fees = []
  availableSubjects.value = []
  availableFees.value = []
  
  // Load student data
  await loadStudentData()
}

async function loadStudentData() {
  if (!selectedStudent.value) return
  
  loadingStudentData.value = true
  
  try {
    const response = await fetch(
      route('student-fees.create', { 
        get_data: true, 
        account_id: selectedStudent.value.account_id 
      })
    )
    
    const data = await response.json()
    
    availableSubjects.value = data.subjects
    availableFees.value = data.fees
    
    // Populate form subjects
    form.subjects = data.subjects.map((subject: Subject) => ({
      id: subject.id,
      code: subject.code,
      name: subject.name,
      units: subject.units,
      amount: subject.total_cost,
      selected: false
    }))
    
    // Populate form fees
    form.other_fees = data.fees.map((fee: Fee) => ({
      id: fee.id,
      name: fee.name,
      category: fee.category,
      amount: fee.amount,
      selected: false
    }))
  } catch (error) {
    console.error('Failed to load student data:', error)
  } finally {
    loadingStudentData.value = false
  }
}

async function loadCurriculumPreview() {
  if (!form.program_id || !form.year_level || !form.semester || !form.school_year) {
    return
  }
  
  loadingCurriculum.value = true
  curriculumPreview.value = null
  
  try {
    const response = await fetch(route('student-fees.curriculum.preview'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        program_id: form.program_id,
        year_level: form.year_level,
        semester: form.semester,
        school_year: form.school_year,
      })
    })
    
    const data = await response.json()
    
    if (data.curriculum) {
      curriculumPreview.value = data.curriculum
      showCurriculumPreview.value = true
    } else {
      showCurriculumPreview.value = false
      alert('No curriculum found for selected term. Please use manual assessment.')
      form.use_curriculum = false
    }
  } catch (error) {
    console.error('Failed to load curriculum:', error)
    showCurriculumPreview.value = false
    form.use_curriculum = false
  } finally {
    loadingCurriculum.value = false
  }
}

function toggleSubject(index: number) {
  form.subjects[index].selected = !form.subjects[index].selected
}

function toggleFee(index: number) {
  form.other_fees[index].selected = !form.other_fees[index].selected
}

function selectAllSubjects() {
  form.subjects.forEach(subject => subject.selected = true)
}

function deselectAllSubjects() {
  form.subjects.forEach(subject => subject.selected = false)
}

function selectAllFees() {
  form.other_fees.forEach(fee => fee.selected = true)
}

function deselectAllFees() {
  form.other_fees.forEach(fee => fee.selected = false)
}

function submit() {
  if (!canSubmit.value) return
  
  const payload = {
    account_id: form.account_id,
    year_level: form.year_level,
    semester: form.semester,
    school_year: form.school_year,
    use_curriculum: form.use_curriculum,
    program_id: form.program_id,
    subjects: selectedSubjects.value.map(s => ({
      id: s.id,
      units: s.units,
      amount: s.amount
    })),
    other_fees: selectedFees.value.map(f => ({
      id: f.id,
      amount: f.amount
    }))
  }
  
  form.post(route('student-fees.store'), {
    preserveScroll: true,
    onSuccess: () => {
      // Reset form
      selectedStudent.value = null
      form.reset()
    }
  })
}

// ============================================
// WATCHERS
// ============================================
watch(() => form.use_curriculum, (useCurriculum) => {
  if (useCurriculum && form.program_id) {
    loadCurriculumPreview()
  } else {
    showCurriculumPreview.value = false
    curriculumPreview.value = null
  }
})

watch(() => [form.program_id, form.year_level, form.semester, form.school_year], () => {
  if (form.use_curriculum && form.program_id) {
    loadCurriculumPreview()
  }
})
</script>

<template>
  <AppShell variant="sidebar">
    <AppSidebar />
    
    <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Create Student Assessment</h1>
          <p class="text-muted-foreground">Generate fee assessment for existing student</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <!-- STEP 1: Select Student -->
        <Card class="lg:col-span-1">
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <User class="h-5 w-5" />
              Step 1: Select Student
            </CardTitle>
            <CardDescription>Choose student to assess</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <!-- Search -->
              <div class="relative">
                <Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  v-model="studentSearch"
                  placeholder="Search by name, ID, email..."
                  class="pl-8"
                />
              </div>

              <!-- Student List -->
              <ScrollArea class="h-[400px] rounded-md border">
                <div class="p-4 space-y-2">
                  <button
                    v-for="student in filteredStudents"
                    :key="student.id"
                    @click="selectStudent(student)"
                    class="w-full text-left p-3 rounded-lg border hover:bg-accent transition-colors"
                    :class="{
                      'bg-primary text-primary-foreground': selectedStudent?.id === student.id
                    }"
                  >
                    <div class="font-medium">{{ student.name }}</div>
                    <div class="text-sm opacity-80">{{ student.student_id }}</div>
                    <div class="text-xs opacity-60 mt-1">
                      {{ student.course }} - {{ student.year_level }}
                    </div>
                  </button>
                </div>
              </ScrollArea>

              <!-- Selected Student Info -->
              <Alert v-if="selectedStudent" class="bg-green-50 border-green-200">
                <CheckCircle class="h-4 w-4 text-green-600" />
                <AlertDescription class="text-green-800">
                  <strong>{{ selectedStudent.name }}</strong><br>
                  {{ selectedStudent.student_id }} | {{ selectedStudent.account_id }}
                </AlertDescription>
              </Alert>
            </div>
          </CardContent>
        </Card>

        <!-- STEP 2: Assessment Configuration -->
        <Card class="lg:col-span-2">
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <FileText class="h-5 w-5" />
              Step 2: Configure Assessment
            </CardTitle>
            <CardDescription>Set term and select fees</CardDescription>
          </CardHeader>
          <CardContent>
            <form @submit.prevent="submit" class="space-y-6">
              <!-- Term Selection -->
              <div class="grid gap-4 md:grid-cols-3">
                <div class="space-y-2">
                  <Label for="year_level">Year Level</Label>
                  <Select v-model="form.year_level" :disabled="!selectedStudent">
                    <SelectTrigger id="year_level">
                      <SelectValue placeholder="Select year level" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="level in yearLevels" :key="level" :value="level">
                        {{ level }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div class="space-y-2">
                  <Label for="semester">Semester</Label>
                  <Select v-model="form.semester" :disabled="!selectedStudent">
                    <SelectTrigger id="semester">
                      <SelectValue placeholder="Select semester" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="sem in semesters" :key="sem" :value="sem">
                        {{ sem }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div class="space-y-2">
                  <Label for="school_year">School Year</Label>
                  <Select v-model="form.school_year" :disabled="!selectedStudent">
                    <SelectTrigger id="school_year">
                      <SelectValue placeholder="Select school year" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="year in schoolYears" :key="year" :value="year">
                        {{ year }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <!-- OBE Curriculum Option -->
              <div v-if="programs" class="space-y-4">
                <Separator />
                
                <div class="flex items-center space-x-2">
                  <Checkbox 
                    id="use_curriculum" 
                    v-model:checked="form.use_curriculum"
                    :disabled="!selectedStudent"
                  />
                  <Label 
                    for="use_curriculum" 
                    class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                  >
                    Use OBE Curriculum Assessment
                  </Label>
                </div>

                <div v-if="form.use_curriculum" class="space-y-2">
                  <Label for="program_id">Select Program</Label>
                  <Select v-model="form.program_id">
                    <SelectTrigger id="program_id">
                      <SelectValue placeholder="Select program" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem 
                        v-for="program in programs" 
                        :key="program.id" 
                        :value="program.id"
                      >
                        {{ program.full_name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>

                  <!-- Curriculum Preview -->
                  <Alert v-if="loadingCurriculum" class="bg-blue-50 border-blue-200">
                    <Loader2 class="h-4 w-4 animate-spin text-blue-600" />
                    <AlertDescription class="text-blue-800">
                      Loading curriculum preview...
                    </AlertDescription>
                  </Alert>

                  <Card v-if="showCurriculumPreview && curriculumPreview" class="mt-4">
                    <CardHeader>
                      <CardTitle class="text-base">Curriculum Preview</CardTitle>
                      <CardDescription>{{ curriculumPreview.program }} - {{ curriculumPreview.term }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <ScrollArea class="h-[200px]">
                        <table class="w-full text-sm">
                          <thead>
                            <tr class="border-b">
                              <th class="text-left p-2">Code</th>
                              <th class="text-left p-2">Title</th>
                              <th class="text-right p-2">Units</th>
                              <th class="text-right p-2">Lab</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="course in curriculumPreview.courses" :key="course.code" class="border-b">
                              <td class="p-2 font-mono">{{ course.code }}</td>
                              <td class="p-2">{{ course.title }}</td>
                              <td class="p-2 text-right">{{ course.total_units }}</td>
                              <td class="p-2 text-right">
                                <Badge v-if="course.has_lab" variant="secondary">Lab</Badge>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </ScrollArea>

                      <Separator class="my-4" />

                      <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                          <span>Tuition Fee:</span>
                          <span class="font-semibold">₱{{ curriculumPreview.totals.tuition.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                        <div class="flex justify-between">
                          <span>Lab Fees:</span>
                          <span class="font-semibold">₱{{ curriculumPreview.totals.lab_fees.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                        <div class="flex justify-between">
                          <span>Registration Fee:</span>
                          <span class="font-semibold">₱{{ curriculumPreview.totals.registration_fee.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                        <div class="flex justify-between">
                          <span>Misc Fee:</span>
                          <span class="font-semibold">₱{{ curriculumPreview.totals.misc_fee.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between text-base font-bold">
                          <span>Total Assessment:</span>
                          <span class="text-primary">₱{{ curriculumPreview.totals.total_assessment.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              </div>

              <!-- Manual Subject Selection -->
              <div v-if="!form.use_curriculum && selectedStudent" class="space-y-4">
                <Separator />

                <!-- Subjects -->
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <Label class="text-base font-semibold">
                      <BookOpen class="inline h-4 w-4 mr-1" />
                      Subjects
                    </Label>
                    <div class="space-x-2">
                      <Button 
                        type="button" 
                        variant="outline" 
                        size="sm"
                        @click="selectAllSubjects"
                      >
                        <Plus class="h-4 w-4 mr-1" />
                        Select All
                      </Button>
                      <Button 
                        type="button" 
                        variant="outline" 
                        size="sm"
                        @click="deselectAllSubjects"
                      >
                        <Minus class="h-4 w-4 mr-1" />
                        Clear All
                      </Button>
                    </div>
                  </div>

                  <ScrollArea class="h-[200px] rounded-md border p-4">
                    <div class="space-y-2">
                      <div
                        v-for="(subject, index) in form.subjects"
                        :key="subject.id"
                        class="flex items-center space-x-2 p-2 rounded hover:bg-accent"
                      >
                        <Checkbox
                          :id="`subject-${subject.id}`"
                          :checked="subject.selected"
                          @update:checked="() => toggleSubject(index)"
                        />
                        <Label
                          :for="`subject-${subject.id}`"
                          class="flex-1 cursor-pointer"
                        >
                          <div class="font-medium">{{ subject.code }} - {{ subject.name }}</div>
                          <div class="text-sm text-muted-foreground">
                            {{ subject.units }} units × ₱{{ (subject.amount / subject.units).toFixed(2) }} = 
                            <span class="font-semibold">₱{{ subject.amount.toFixed(2) }}</span>
                          </div>
                        </Label>
                      </div>
                    </div>
                  </ScrollArea>
                </div>

                <!-- Other Fees -->
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <Label class="text-base font-semibold">
                      <Calculator class="inline h-4 w-4 mr-1" />
                      Other Fees
                    </Label>
                    <div class="space-x-2">
                      <Button 
                        type="button" 
                        variant="outline" 
                        size="sm"
                        @click="selectAllFees"
                      >
                        <Plus class="h-4 w-4 mr-1" />
                        Select All
                      </Button>
                      <Button 
                        type="button" 
                        variant="outline" 
                        size="sm"
                        @click="deselectAllFees"
                      >
                        <Minus class="h-4 w-4 mr-1" />
                        Clear All
                      </Button>
                    </div>
                  </div>

                  <ScrollArea class="h-[200px] rounded-md border p-4">
                    <div class="space-y-2">
                      <div
                        v-for="(fee, index) in form.other_fees"
                        :key="fee.id"
                        class="flex items-center space-x-2 p-2 rounded hover:bg-accent"
                      >
                        <Checkbox
                          :id="`fee-${fee.id}`"
                          :checked="fee.selected"
                          @update:checked="() => toggleFee(index)"
                        />
                        <Label
                          :for="`fee-${fee.id}`"
                          class="flex-1 cursor-pointer"
                        >
                          <div class="font-medium">{{ fee.name }}</div>
                          <div class="text-sm text-muted-foreground">
                            {{ fee.category }} - 
                            <span class="font-semibold">₱{{ fee.amount.toFixed(2) }}</span>
                          </div>
                        </Label>
                      </div>
                    </div>
                  </ScrollArea>
                </div>
              </div>

              <!-- Summary -->
              <Card v-if="selectedSubjects.length > 0 || selectedFees.length > 0" class="bg-muted/50">
                <CardHeader>
                  <CardTitle class="text-base">Assessment Summary</CardTitle>
                </CardHeader>
                <CardContent>
                  <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                      <span>Tuition ({{ selectedSubjects.length }} subjects):</span>
                      <span class="font-semibold">₱{{ totalTuition.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span>Other Fees ({{ selectedFees.length }} items):</span>
                      <span class="font-semibold">₱{{ totalOtherFees.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                    </div>
                    <Separator />
                    <div class="flex justify-between text-lg font-bold">
                      <span>Total Assessment:</span>
                      <span class="text-primary">₱{{ grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <!-- Validation Alert -->
              <Alert v-if="!canSubmit && selectedStudent" class="bg-yellow-50 border-yellow-200">
                <AlertCircle class="h-4 w-4 text-yellow-600" />
                <AlertDescription class="text-yellow-800">
                  Please select at least one subject or use OBE curriculum to create assessment.
                </AlertDescription>
              </Alert>

              <!-- Submit Button -->
              <div class="flex justify-end gap-4">
                <Button
                  type="button"
                  variant="outline"
                  @click="() => { selectedStudent = null; form.reset(); }"
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  :disabled="!canSubmit || form.processing"
                >
                  <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                  Create Assessment
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppShell>
</template>