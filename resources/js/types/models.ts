export type Status = 'pending' | 'processing' | 'completed' | 'failed' | 'canceled';

export interface Paginated<T> {
  data: T[];
  links: Array<{ url: string | null; label: string; active: boolean }>;
  meta?: Record<string, unknown>;
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
}

export interface Tenant {
  id: number;
  name: string;
}

export interface Company {
  id: number;
  uuid: string;
  tenant_id: number;
  legal_name: string;
  trade_name: string | null;
  cnpj: string;
  uf: string;
  fiscal_environment: string;
  is_active: boolean;
  agent?: Agent | null;
  agents?: Agent[];
}

export interface Agent {
  id: number;
  uuid: string;
  name: string;
  machine_name: string | null;
  version: string | null;
  status: string;
  last_seen_at: string | null;
  company?: Company | null;
}

export interface FiscalDocument {
  id: number;
  uuid: string;
  company_id: number;
  access_key: string;
  nsu: string | null;
  issuer_cnpj: string | null;
  issuer_name: string | null;
  number: string | null;
  series: string | null;
  issued_at: string | null;
  total_amount: string | number | null;
  manifestation_status: string;
  xml_download_status: string;
  last_sefaz_status_code: string | null;
  last_sefaz_message: string | null;
  company?: Company | null;
}

export interface AgentCommand {
  id: number;
  uuid: string;
  type: string;
  status: Status | string;
  priority: number;
  created_at: string | null;
  completed_at: string | null;
  failed_at: string | null;
  company?: Company | null;
}
