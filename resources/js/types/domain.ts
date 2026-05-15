export type Status =
  | 'pending'
  | 'processing'
  | 'completed'
  | 'failed'
  | 'canceled'
  | 'cancelled'
  | 'online'
  | 'offline'
  | 'revoked'
  | 'active'
  | 'expired';

export interface Paginated<T> {
  data: T[];
  links: Array<{ url: string | null; label: string; active: boolean }>;
  current_page: number;
  last_page: number;
  total: number;
}

export interface Company {
  id: number;
  legal_name: string;
  trade_name?: string | null;
  cnpj: string;
  uf: string;
  fiscal_environment: string;
  is_active: boolean;
}

export interface FiscalDocument {
  id: number;
  access_key: string;
  number?: string | null;
  series?: string | null;
  issued_at?: string | null;
  issuer_cnpj?: string | null;
  issuer_name?: string | null;
  total_amount?: string | number | null;
  manifestation_status: string;
  xml_download_status: string;
  company?: Company;
}
