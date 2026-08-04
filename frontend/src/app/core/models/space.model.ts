/**
 * §12 · UC-37 — what the CATALOG reads, kept apart from `is_active` on purpose:
 *   paused       the PROVIDER's own lever (is_active = false), reversible by them.
 *   unpublished  a CONSEQUENCE — a deletion is programmed. The provider's lever cannot
 *                give it back; only POST /provider/spaces/{id}/cancel-deletion can.
 */
export type SpacePublicationStatus = 'published' | 'paused' | 'unpublished';

export interface Space {
  id: number;
  user_id: number;
  name: string;
  description: string | null;
  type: 'billboard' | 'big_screen' | 'little_screen' | 'radio_station' | 'other';
  latitude: number;
  longitude: number;
  // `location_name` and `address` used to live here; NEITHER is a column on `spaces`
  // (the column is `location_text`), so every template reading them rendered empty.
  location_text: string | null;
  price_per_day: number;
  price_per_month: number | null;
  pricing_unit: 'day' | 'month' | 'custom';
  width: number | null;
  height: number | null;
  ical_url: string | null;
  calendar_keyword: string | null;
  calendar_synced_at: string | null;
  is_active: boolean;
  // §12 · UC-37 — the three levels the provider screen has to keep apart.
  publication_status?: SpacePublicationStatus;
  delete_scheduled_at?: string | null;
  taken_down_at?: string | null;
  takedown_reason?: string | null;
  created_at: string;
  updated_at: string;
  photos?: SpacePhoto[];
  availabilities?: SpaceAvailability[];
  provider?: { id: number; name: string; company_name: string | null };
  distance_km?: number;
  available?: boolean;
}

export interface SpacePhoto {
  id: number;
  space_id: number;
  file_path: string;
  file_name: string;
  file_url: string;
  is_primary: boolean;
  sort_order: number;
}

export interface SpaceAvailability {
  id: number;
  space_id: number;
  start_date: string;
  end_date: string;
  status: 'available' | 'booked' | 'blocked';
  source?: string;
}
