export interface Space {
  id: number;
  user_id: number;
  name: string;
  description: string | null;
  type: 'billboard' | 'big_screen' | 'little_screen' | 'radio_station' | 'other';
  latitude: number;
  longitude: number;
  location_name: string | null;
  location_text: string | null;
  price_per_day: number;
  width: number | null;
  height: number | null;
  ical_url: string | null;
  calendar_keyword: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  photos?: SpacePhoto[];
  availabilities?: SpaceAvailability[];
  provider?: { id: number; name: string; company_name: string | null };
  distance_km?: number;
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
