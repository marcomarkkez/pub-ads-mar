export interface Booking {
  id: number;
  client_user_id: number;
  space_id: number;
  adset_id: number | null;
  start_date: string;
  end_date: string;
  total_price: number;
  status: 'pending' | 'confirmed' | 'cancelled' | 'completed';
  notes: string | null;
  created_at: string;
  updated_at: string;
  space?: { id: number; name: string; type: string; location_name: string | null };
  client?: { id: number; name: string; email: string };
  payment?: Payment;
  proof?: Proof;
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
  user_id: number;
  file_path: string;
  file_name: string;
  file_url: string;
  notes: string | null;
  status: 'pending' | 'approved' | 'rejected';
  reviewed_at: string | null;
  created_at: string;
  updated_at: string;
}
