export interface Campaign {
  id: number;
  user_id: number;
  name: string;
  description: string | null;
  status: 'draft' | 'active' | 'paused' | 'completed' | 'cancelled';
  start_date: string | null;
  end_date: string | null;
  budget: number | null;
  created_at: string;
  updated_at: string;
  adsets?: Adset[];
  adsets_count?: number;
}

export interface Adset {
  id: number;
  campaign_id: number;
  name: string;
  latitude: number | null;
  longitude: number | null;
  location_name: string | null;
  radius_km: number | null;
  status: 'active' | 'paused';
  created_at: string;
  updated_at: string;
  ads?: Ad[];
  ads_count?: number;
}

export interface Ad {
  id: number;
  adset_id: number | null;
  space_id: number | null;
  name: string;
  media_type: 'image' | 'video' | 'sound' | 'gif';
  file_path: string | null;
  file_name: string | null;
  file_url: string | null;
  space?: { id: number; name: string } | null;
  created_at: string;
  updated_at: string;
}
