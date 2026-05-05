export type DashboardAuditRow = {
  id: number;
  module: string;
  action: string;
  success: boolean;
  created_at: string;
  target_account_id: string | null;
};

export type DashboardPolicyRow = {
  id: number;
  name: string;
  last_status: string;
  execution_at: string | null;
};

export type DashboardPayload = {
  counts: {
    students: number;
    suspended: number;
    due_for_deletion: number;
    active_policies: number;
  };
  recent_audit: DashboardAuditRow[];
  active_policies: DashboardPolicyRow[];
};
