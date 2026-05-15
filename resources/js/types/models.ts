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
  certificates?: CompanyCertificate[];
}

export interface CompanyCertificate {
  id: number;
  company_id: number;
  name: string | null;
  type: string;
  status: string;
  valid_until: string | null;
}

export interface Agent {
  id: number;
  uuid: string;
  tenant_id?: number;
  company_id?: number | null;
  name: string;
  machine_name: string | null;
  installation_id?: string | null;
  version: string | null;
  status: string;
  operational_status?: string;
  can_request_diagnostics?: boolean;
  last_seen_at: string | null;
  activated_at?: string | null;
  revoked_at?: string | null;
  company?: Company | null;
}

export interface AgentCertificate {
  id: number;
  uuid: string;
  agent_id: number;
  company_id: number | null;
  type: string;
  status: string;
  store_scope: string | null;
  store_location: string | null;
  subject: string | null;
  subject_name: string | null;
  issuer: string | null;
  issuer_name: string | null;
  serial_number: string | null;
  thumbprint: string;
  cnpj: string | null;
  valid_from: string | null;
  valid_until: string | null;
  not_before: string | null;
  not_after: string | null;
  has_private_key: boolean;
  is_expired: boolean;
  is_valid: boolean;
  validation_message: string | null;
  last_seen_at: string | null;
  last_tested_at: string | null;
  last_test_status: string | null;
  last_test_message: string | null;
  last_test_error_code: string | null;
  agent?: Agent | null;
  company?: Company | null;
}

export interface CompanyCertificate {
  id: number;
  uuid: string;
  company_id: number;
  agent_id: number | null;
  agent_certificate_id: number | null;
  type: string;
  status: string;
  name: string | null;
  subject_name: string | null;
  issuer_name: string | null;
  serial_number: string | null;
  thumbprint: string | null;
  valid_from: string | null;
  valid_until: string | null;
  store_scope: string | null;
  last_tested_at: string | null;
  last_test_status: string | null;
  last_test_message: string | null;
  last_test_error_code: string | null;
  company?: Company | null;
  agent?: Agent | null;
  agent_certificate?: AgentCertificate | null;
}

export interface SefazConnectivityTest {
  id: number;
  uuid: string;
  company_id: number;
  agent_id: number | null;
  company_certificate_id: number | null;
  agent_command_id: number | null;
  mode: string;
  environment: string;
  uf: string;
  endpoint: string | null;
  status: string;
  sefaz_status_code: string | null;
  sefaz_message: string | null;
  error_code: string | null;
  error_message: string | null;
  duration_ms: number | null;
  requested_at: string;
  completed_at: string | null;
  company_certificate?: CompanyCertificate | null;
  agent?: Agent | null;
}

export interface CompanyFiscalState {
  id: number;
  uuid: string;
  tenant_id: number;
  company_id: number;
  environment: string;
  uf: string;
  service: string;
  last_nsu: string;
  max_nsu: string;
  next_distribution_available_at: string | null;
  distribution_blocked_until: string | null;
  distribution_block_reason: string | null;
  last_distribution_status_code: string | null;
  last_distribution_message: string | null;
  last_distribution_attempt_at: string | null;
  last_distribution_success_at: string | null;
  last_distribution_error_at: string | null;
  consecutive_distribution_failures: number;
  last_status_code: string | null;
  last_message: string | null;
  last_success_at: string | null;
  last_error_at: string | null;
  metadata: Record<string, unknown> | null;
}

export interface DistributionAvailability {
  allowed: boolean;
  reason: string | null;
  message: string;
  available_at: string | null;
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
  attempts?: AgentCommandAttempt[];
}

export interface AgentCommandAttempt {
  id: number;
  status: string;
  error_code: string | null;
  error_message: string | null;
  result_payload: {
    error_details?: unknown;
    sefaz_status_code?: string | null;
    sefaz_message?: string | null;
  } | null;
}
