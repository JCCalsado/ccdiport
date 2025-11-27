// Base User type
export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  avatar?: string | null;
  profile_picture?: string;
  email_verified_at?: string | null;
  created_at?: string;
  updated_at?: string;
  
  // ✅ ADD THESE MISSING FIELDS
  paymentTerms?: PaymentTerm[]; // NEW
  account?: Account; // NEW (referenced but not typed)
}

// ✅ ADD NEW INTERFACE
export interface PaymentTerm {
  id: number;
  term_name: string;
  term_order: number;
  amount: number;
  paid_amount: number;
  remaining_balance: number;
  due_date: string | null;
  status: 'pending' | 'paid' | 'partial';
  is_overdue?: boolean;
}

// ✅ ADD ACCOUNT INTERFACE
export interface Account {
  id: number;
  balance: number;
  created_at?: string;
  updated_at?: string;
}

// StudentUser extends User
export interface StudentUser extends User {
  student_id: string;
  course: string;
  year_level: string;

  address?: string;
  phone?: string;
  status?: 'active' | 'graduated' | 'dropped';
}
