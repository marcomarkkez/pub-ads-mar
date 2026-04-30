export interface User {
  id: number;
  name: string;
  email: string;
  role: 'client' | 'provider' | 'admin' | 'support' | 'payments';
  phone: string | null;
  company_name: string | null;
  address: string | null;
  is_active: boolean;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: 'client' | 'provider';
}

export interface AuthResponse {
  user: User;
  token: string;
  permissions: Record<string, boolean>;
}

export interface MeResponse {
  user: User;
  permissions: Record<string, boolean>;
}

export type Permissions = Record<string, boolean>;
