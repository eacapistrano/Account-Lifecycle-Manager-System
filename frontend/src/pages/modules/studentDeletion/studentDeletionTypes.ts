import type { LaravelPaginator } from "../../../types/api";

export type StudentRow = {
  id: number;
  external_account_id: string;
  primary_email: string;
  full_name: string | null;
  department: string | null;
  school_year: string | null;
  graduation_date: string | null;
  graduation_status: string | null;
  suspended: boolean;
  deletion_scheduled_at: string | null;
  last_imported_at: string | null;
};

export type StudentsIndexResponse = {
  data: LaravelPaginator<StudentRow>;
};

export type StudentFiltersState = {
  department: string;
  school_year: string;
  graduation_status: string;
  graduated_from: string;
  graduated_to: string;
};

export const INITIAL_STUDENT_FILTERS: StudentFiltersState = {
  department: "",
  school_year: "",
  graduation_status: "",
  graduated_from: "",
  graduated_to: "",
};

export type OperationTrackerPayload = {
  operation_id: string;
  action: string;
  status: string;
  total: number;
  processed: number;
  ok: number;
  failed: number;
  requested_at: string | null;
  started_at: string | null;
  updated_at: string | null;
  completed_at: string | null;
  error: string | null;
};

export type BulkHistoryRow = OperationTrackerPayload & {
  actor?: {
    name: string;
    email: string;
  } | null;
};

export type BulkHistoryResponse = {
  data: LaravelPaginator<BulkHistoryRow>;
};
