export * from './user.model';
export * from './campaign.model';
export * from './space.model';
export * from './booking.model';
export * from './conversation.model';

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
}
