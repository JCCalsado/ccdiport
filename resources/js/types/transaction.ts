export type TransactionStatus = 'pending' | 'paid' | 'failed' | 'cancelled'
export type TransactionKind = 'charge' | 'payment'

export type TransactionCategory =
  | 'Tuition'
  | 'Laboratory'
  | 'Library'
  | 'Athletic'
  | 'Miscellaneous'
  | 'Payment'
  | string

export type PaymentMethod =
  | 'cash'
  | 'gcash'
  | 'bank_transfer'
  | 'credit_card'
  | 'debit_card'

export interface TransactionMeta {
  fee_name?: string
  description?: string
  assessment_id?: number
  subject_code?: string
  subject_name?: string
  units?: number
  has_lab?: boolean
  fee_code?: string
  reference_number?: string
  payment_id?: number
  [key: string]: any
}

export interface Fee {
  id: number
  name: string
  category: string
  amount: number
  code?: string
}

export interface TransactionUser {
  id: number
  name: string
  student_id: string
  email: string
}

export interface Transaction {
  id: number;
  reference: string;
  kind: TransactionKind;
  type: TransactionCategory;
  year?: string; // ✅ Add
  semester?: string; // ✅ Add
  amount: number;
  status: TransactionStatus;
  payment_channel?: PaymentMethod;
  paid_at?: string;
  created_at: string;
  updated_at?: string;
  fee?: Fee;
  meta?: TransactionMeta;
  user?: TransactionUser;
}

export interface Account {
  id: number
  user_id: number
  balance: number
  created_at?: string
  updated_at?: string
}

export const isChargeTransaction = (t: Transaction): boolean => t.kind === 'charge'
export const isPaymentTransaction = (t: Transaction): boolean => t.kind === 'payment'
export const isPendingTransaction = (t: Transaction): boolean => t.status === 'pending'
export const isPaidTransaction = (t: Transaction): boolean => t.status === 'paid'