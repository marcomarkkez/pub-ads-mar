export interface Booking {
  id: number;
  client_user_id: number;
  space_id: number;
  ad_id: number | null;
  adset_id: number | null;
  start_date: string;
  end_date: string;
  total_price: number;
  status: 'pending' | 'waiting_approval' | 'confirmed' | 'active' | 'waiting_proof' | 'completed' | 'cancelled' | 'rejected';
  // §5 [F06] — the provider's OPTIONAL reason, written by PUT /provider/bookings/{id}
  // and cleared by the controller on any status other than `rejected`.
  rejection_reason?: string | null;
  // `notes` and `book_for_later` used to live here; NEITHER is a column on `bookings`
  // and nothing ever wrote them, so every template branch reading them was dead.
  config_snapshot?: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
  // The serialized `spaces` row: `location_text` is the column, `location_name` never was.
  space?: { id: number; name: string; type: string; location_text: string | null };
  client?: { id: number; name: string; email: string };
  payment?: Payment;
  proof?: Proof;
  proofs?: Proof[];
}

/**
 * §8 — `payments.status` is the ONLY authority over where the money is; these are the
 * exact values App\Models\Payment::STATUS_* can hold (there is no `approved`/`rejected`).
 *
 * `free_payment` vs `held` is the load-bearing distinction: `free_payment` = the client
 * ACCEPTED the proof, so the payout is merely waiting for Payments to run the release
 * (ready to pay); `held` = the client REJECTED it, so the money is FROZEN inside a live
 * dispute. Both used to write `held`, which is why the state was split — never render
 * the two alike.
 */
export type PaymentStatus =
  | 'pending'
  | 'completed'
  | 'failed'
  | 'refunded'
  | 'held'
  | 'released'
  | 'free_payment';

export interface Payment {
  id: number;
  booking_id: number;
  amount: number;
  status: PaymentStatus;
  payment_method: string | null;
  transaction_id: string | null;
  // No `paid_at`: the column does not exist on `payments`. Use `updated_at`/audit log.
  created_at: string;
  updated_at: string;
}

/** Human label for a payment status — keeps `free_payment` from surfacing raw. */
export function paymentStatusLabel(status: PaymentStatus | string): string {
  switch (status) {
    case 'free_payment': return 'Ready to pay';
    case 'held': return 'Held (dispute)';
    case 'released': return 'Released';
    case 'refunded': return 'Refunded';
    case 'completed': return 'Completed';
    case 'failed': return 'Failed';
    case 'pending': return 'Pending';
    default: return status;
  }
}

export interface Proof {
  id: number;
  ad_id: number;
  booking_id: number;
  // §21/§7 — the UPLOADER (the installator subrole may be someone other than the space
  // owner). Read access derives from the ownership chain, not from this field.
  uploaded_by_user_id: number | null;
  reviewed_by_user_id?: number | null;
  /** §2 — when the proof is due (snapshot of the ad's proof_deadline). */
  deadline?: string | null;
  media_type?: 'image' | 'video';
  file_path: string;
  file_name: string;
  file_url: string;
  notes: string | null;
  // B9: the CLIENT accepts/rejects. Legacy approved/rejected kept for old rows.
  // Q42/Q53 — three values, no legacy. See App\Models\Proof::STATUS_*.
  status: 'proof_uploaded' | 'client_accepted' | 'client_rejected';
  reviewed_at: string | null;
  created_at: string;
  updated_at: string;
}
