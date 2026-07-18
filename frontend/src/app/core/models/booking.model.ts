export interface Booking {
  id: number;
  client_user_id: number;
  space_id: number;
  adset_id: number | null;
  start_date: string;
  end_date: string;
  total_price: number;
  status: 'pending' | 'waiting_approval' | 'confirmed' | 'active' | 'waiting_proof' | 'completed' | 'cancelled' | 'rejected';
  notes: string | null;
  book_for_later?: boolean;
  config_snapshot?: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
  space?: { id: number; name: string; type: string; location_name: string | null };
  client?: { id: number; name: string; email: string };
  payment?: Payment;
  proof?: Proof;
  proofs?: Proof[];
}

export interface Payment {
  id: number;
  booking_id: number;
  amount: number;
  status: 'pending' | 'approved' | 'rejected';
  payment_method: string | null;
  transaction_id: string | null;
  paid_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface Proof {
  id: number;
  booking_id: number;
  uploaded_by_user_id: number;
  media_type?: 'image' | 'video';
  file_path: string;
  file_name: string;
  file_url: string;
  notes: string | null;
  // B9: the CLIENT accepts/rejects. Legacy approved/rejected kept for old rows.
  status: 'pending_review' | 'client_accepted' | 'client_rejected' | 'approved' | 'rejected';
  reviewed_at: string | null;
  created_at: string;
  updated_at: string;
}
