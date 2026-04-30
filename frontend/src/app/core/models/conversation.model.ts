export interface Conversation {
  id: number;
  space_id: number;
  client_user_id: number;
  provider_user_id: number;
  created_at: string;
  updated_at: string;
  space?: { id: number; name: string };
  client?: { id: number; name: string };
  provider?: { id: number; name: string };
  latest_message?: Message;
  messages_count?: number;
}

export interface Message {
  id: number;
  conversation_id: number;
  sender_user_id: number;
  body: string;
  is_read: boolean;
  created_at: string;
  updated_at: string;
  sender?: { id: number; name: string; role: string };
}

export interface Ticket {
  id: number;
  user_id: number;
  assigned_to_user_id: number | null;
  subject: string;
  description: string;
  status: 'open' | 'in_progress' | 'resolved' | 'closed';
  priority: 'low' | 'medium' | 'high';
  reference_type: string | null;
  reference_id: number | null;
  created_at: string;
  updated_at: string;
  user?: { id: number; name: string; email: string };
  assigned_to?: { id: number; name: string };
  messages?: TicketMessage[];
}

export interface TicketMessage {
  id: number;
  ticket_id: number;
  user_id: number;
  body: string;
  created_at: string;
  user?: { id: number; name: string; role: string };
}

export interface Invoice {
  id: number;
  campaign_id: number;
  invoice_number: string;
  amount: number;
  status: 'draft' | 'issued' | 'paid' | 'cancelled';
  issued_at: string | null;
  paid_at: string | null;
  created_at: string;
  updated_at: string;
  campaign?: { id: number; name: string };
}

export interface Collaborator {
  id: number;
  campaign_id: number;
  user_id: number;
  email: string;
  created_at: string;
  user?: { id: number; name: string; email: string };
}
