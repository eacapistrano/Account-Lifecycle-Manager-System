import type { LaravelPaginator } from "../../../types/api";

export type PolicyRuleType = "scope" | "student_graduation";

export type PolicyRow = {
  id: number;
  name: string;
  action: string;
  rule_json: Record<string, unknown>;
  execution_at: string | null;
  cron_expression: string | null;
  is_active: boolean;
  last_evaluated_at: string | null;
  last_status: string;
  hold_reason: string | null;
};

export type PoliciesIndexResponse = {
  data: LaravelPaginator<PolicyRow>;
};

export type PolicyNextRunResponse = {
  policy_id: number;
  execution_at: string | null;
  cron_expression: string | null;
  last_evaluated_at: string | null;
  last_status: string;
  policy_type?: PolicyRuleType;
  suspend_after_days?: number;
  warning_days_before_suspend?: number;
  permanent_delete_after_days?: number;
  warning_days_before_delete?: number;
  graduation_preview?: {
    eligible_warnings: number;
    eligible_suspensions: number;
    eligible_deletion_warnings?: number;
  };
};

export type AutomationScheduleTask = {
  key: string;
  name: string;
  cron: string;
  description: string;
};

export type AutomationQueueJobRow = {
  id: number;
  queue: string;
  job_name: string;
  attempts: number;
  status: string;
  available_at: string | null;
  created_at: string | null;
};

export type AutomationQueueResponse = {
  queue_connection: string;
  pending_count: number;
  failed_count: number;
  google_workspace?: {
    suspend_enabled: boolean;
    suspend_dry_run: boolean;
    delete_enabled: boolean;
    delete_dry_run: boolean;
    credentials_configured: boolean;
    credentials_readable: boolean;
    impersonation_configured: boolean;
    impersonate_email: string | null;
    scopes: string[];
    suspend_user_key: string;
    delete_user_key: string;
    ready_for_suspend: boolean;
    ready_for_delete: boolean;
  };
  schedules: AutomationScheduleTask[];
  recent_pending: AutomationQueueJobRow[];
  recent_failed: Array<{
    id: number;
    uuid: string;
    queue: string;
    failed_at: string | null;
  }>;
};
