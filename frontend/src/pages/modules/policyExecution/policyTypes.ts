import type { LaravelPaginator } from "../../../types/api";

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
};
