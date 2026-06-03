import { useEffect, useState } from "react";
import { apiRequest } from "../../../lib/api";
import type { PolicyRow, PolicyRuleType } from "./policyTypes";

type Props = {
  open: boolean;
  policy: PolicyRow | null;
  disabled: boolean;
  onClose: () => void;
  onSaved: () => Promise<void>;
};

function datetimeLocalFromIso(iso: string | null): string {
  if (!iso) {
    return "";
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return "";
  }
  const pad = (value: number) => String(value).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function isoFromDatetimeLocal(value: string): string | null {
  if (!value.trim()) {
    return null;
  }
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return null;
  }
  return parsed.toISOString();
}

function resolvePolicyType(ruleJson: Record<string, unknown> | undefined): PolicyRuleType {
  return ruleJson?.type === "student_graduation" ? "student_graduation" : "scope";
}

export function PolicyEditorModal({ open, policy, disabled, onClose, onSaved }: Props) {
  const [name, setName] = useState("");
  const [policyType, setPolicyType] = useState<PolicyRuleType>("scope");
  const [action, setAction] = useState<"suspend" | "delete">("suspend");
  const [department, setDepartment] = useState("");
  const [schoolYear, setSchoolYear] = useState("");
  const [suspendAfterDays, setSuspendAfterDays] = useState(60);
  const [warningDaysBefore, setWarningDaysBefore] = useState(14);
  const [permanentDeleteAfterDays, setPermanentDeleteAfterDays] = useState(0);
  const [warningDaysBeforeDelete, setWarningDaysBeforeDelete] = useState(14);
  const [graduationStatus, setGraduationStatus] = useState("graduated");
  const [executionLocal, setExecutionLocal] = useState("");
  const [cronExpression, setCronExpression] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [error, setError] = useState("");
  const [isBusy, setIsBusy] = useState(false);

  useEffect(() => {
    if (!open) {
      return;
    }
    setError("");
    if (policy) {
      setName(policy.name);
      const rules = policy.rule_json ?? {};
      const type = resolvePolicyType(rules);
      setPolicyType(type);
      setAction(policy.action === "delete" ? "delete" : "suspend");
      setDepartment(typeof rules.department === "string" ? rules.department : "");
      setSchoolYear(typeof rules.school_year === "string" ? rules.school_year : "");
      setSuspendAfterDays(typeof rules.suspend_after_days === "number" ? rules.suspend_after_days : 60);
      setWarningDaysBefore(typeof rules.warning_days_before_suspend === "number" ? rules.warning_days_before_suspend : 14);
      setPermanentDeleteAfterDays(typeof rules.permanent_delete_after_days === "number" ? rules.permanent_delete_after_days : 0);
      setWarningDaysBeforeDelete(typeof rules.warning_days_before_delete === "number" ? rules.warning_days_before_delete : 14);
      setGraduationStatus(typeof rules.graduation_status === "string" ? rules.graduation_status : "graduated");
      setExecutionLocal(datetimeLocalFromIso(policy.execution_at));
      setCronExpression(policy.cron_expression ?? "");
      setIsActive(policy.is_active);
    } else {
      setName("");
      setPolicyType("student_graduation");
      setAction("suspend");
      setDepartment("");
      setSchoolYear("");
      setSuspendAfterDays(60);
      setWarningDaysBefore(14);
      setPermanentDeleteAfterDays(0);
      setWarningDaysBeforeDelete(14);
      setGraduationStatus("graduated");
      setExecutionLocal("");
      setCronExpression("");
      setIsActive(true);
    }
  }, [open, policy]);

  if (!open) {
    return null;
  }

  const formDisabled = disabled || isBusy;
  const isGraduation = policyType === "student_graduation";

  async function submit() {
    setError("");

    let ruleJson: Record<string, unknown>;
    let resolvedAction = action;

    if (isGraduation) {
      if (suspendAfterDays < 1) {
        setError("Suspend after days must be at least 1.");
        return;
      }
      if (warningDaysBefore < 0 || warningDaysBefore >= suspendAfterDays) {
        setError("Warning days must be less than suspend after days.");
        return;
      }
      if (permanentDeleteAfterDays < 0) {
        setError("Permanent delete after days must be 0 or greater.");
        return;
      }
      if (warningDaysBeforeDelete < 0) {
        setError("Deletion warning days must be 0 or greater.");
        return;
      }
      if (permanentDeleteAfterDays === 0 && warningDaysBeforeDelete > 0) {
        setError("Deletion warning requires a permanent delete after days value.");
        return;
      }
      if (permanentDeleteAfterDays > 0 && warningDaysBeforeDelete >= permanentDeleteAfterDays) {
        setError("Deletion warning days must be less than permanent delete after days.");
        return;
      }
      ruleJson = {
        type: "student_graduation",
        graduation_status: graduationStatus.trim() || "graduated",
        suspend_after_days: suspendAfterDays,
        warning_days_before_suspend: warningDaysBefore,
      };
      if (permanentDeleteAfterDays > 0) {
        ruleJson.permanent_delete_after_days = permanentDeleteAfterDays;
      }
      if (warningDaysBeforeDelete > 0) {
        ruleJson.warning_days_before_delete = warningDaysBeforeDelete;
      }
      resolvedAction = "suspend";
    } else {
      const deptTrimmed = department.trim();
      const yearTrimmed = schoolYear.trim();
      if (!deptTrimmed && !yearTrimmed) {
        setError("Provide at least department or school year for scope.");
        return;
      }
      ruleJson = { type: "scope" };
      if (deptTrimmed) {
        ruleJson.department = deptTrimmed;
      }
      if (yearTrimmed) {
        ruleJson.school_year = yearTrimmed;
      }
    }

    if (!name.trim()) {
      setError("Name is required.");
      return;
    }

    const executionAt = isoFromDatetimeLocal(executionLocal);

    try {
      const body: Record<string, unknown> = {
        name: name.trim(),
        action: resolvedAction,
        rule_json: ruleJson,
        cron_expression: cronExpression.trim() || null,
        is_active: isActive,
        execution_at: executionAt,
      };

      const path = policy ? `/policies/${policy.id}` : "/policies";
      const method = policy ? "PATCH" : "POST";

      setIsBusy(true);
      await apiRequest(path, {
        method,
        body: JSON.stringify(body),
      });
      await onSaved();
      onClose();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Save failed.");
    } finally {
      setIsBusy(false);
    }
  }

  const title = policy ? "Edit policy" : "New policy";

  return (
    <div className="modal-overlay" role="presentation">
      <div className="modal-panel modal-panel-wide" role="dialog" aria-modal="true" aria-labelledby="policy-editor-title">
        <h3 id="policy-editor-title">{title}</h3>
        {error ? <p className="toast toast-error">{error}</p> : null}
        <div className="policy-form-grid">
          <label>
            Name
            <input value={name} onChange={(ev) => setName(ev.target.value)} disabled={formDisabled} />
          </label>
          <label>
            Policy type
            <select
              value={policyType}
              onChange={(ev) => {
                const next = ev.target.value as PolicyRuleType;
                setPolicyType(next);
                if (next === "student_graduation") {
                  setAction("suspend");
                }
              }}
              disabled={formDisabled}
            >
              <option value="student_graduation">Student graduation (warn + suspend)</option>
              <option value="scope">Department / school year scope</option>
            </select>
          </label>

          {isGraduation ? (
            <>
              <label>
                Graduation status
                <input value={graduationStatus} onChange={(ev) => setGraduationStatus(ev.target.value)} disabled={formDisabled} />
              </label>
              <label>
                Suspend after (days)
                <input
                  type="number"
                  min={1}
                  value={suspendAfterDays}
                  onChange={(ev) => setSuspendAfterDays(Number(ev.target.value))}
                  disabled={formDisabled}
                />
              </label>
              <label>
                Warning email before suspend (days)
                <input
                  type="number"
                  min={0}
                  value={warningDaysBefore}
                  onChange={(ev) => setWarningDaysBefore(Number(ev.target.value))}
                  disabled={formDisabled}
                />
              </label>
              <label>
                Permanent delete after suspension (days)
                <input
                  type="number"
                  min={0}
                  value={permanentDeleteAfterDays}
                  onChange={(ev) => setPermanentDeleteAfterDays(Number(ev.target.value))}
                  disabled={formDisabled}
                />
              </label>
              <label>
                Warning email before deletion (days)
                <input
                  type="number"
                  min={0}
                  value={warningDaysBeforeDelete}
                  onChange={(ev) => setWarningDaysBeforeDelete(Number(ev.target.value))}
                  disabled={formDisabled}
                />
              </label>
              <p className="hint policy-form-span">
                Graduated students receive a backup reminder email starting {warningDaysBefore} day(s) before suspension.
                {permanentDeleteAfterDays > 0 ? ` A separate deletion warning email will be sent ${warningDaysBeforeDelete} day(s) before permanent deletion.` : ''}
                Accounts are suspended {suspendAfterDays} day(s) after their graduation date.
              </p>
            </>
          ) : (
            <>
              <label>
                Action
                <select value={action} onChange={(ev) => setAction(ev.target.value as "suspend" | "delete")} disabled={formDisabled}>
                  <option value="suspend">Suspend matched accounts</option>
                  <option value="delete">Delete matched accounts</option>
                </select>
              </label>
              <label>
                Department scope
                <input value={department} onChange={(ev) => setDepartment(ev.target.value)} placeholder="Science" disabled={formDisabled} />
              </label>
              <label>
                School year scope
                <input value={schoolYear} onChange={(ev) => setSchoolYear(ev.target.value)} placeholder="2026" disabled={formDisabled} />
              </label>
            </>
          )}

          <label>
            Execution at (optional)
            <input type="datetime-local" value={executionLocal} onChange={(ev) => setExecutionLocal(ev.target.value)} disabled={formDisabled} />
          </label>
          <label>
            Cron expression
            <input value={cronExpression} onChange={(ev) => setCronExpression(ev.target.value)} placeholder="*/15 * * * *" disabled={formDisabled} />
          </label>
          <label className="perm-row">
            <input type="checkbox" checked={isActive} onChange={(ev) => setIsActive(ev.target.checked)} disabled={formDisabled} />
            Active
          </label>
        </div>
        <div className="modal-actions">
          <button type="button" className="secondary" onClick={onClose} disabled={disabled || isBusy}>
            Cancel
          </button>
          <button type="button" onClick={() => void submit()} disabled={disabled || isBusy}>
            Save policy
          </button>
        </div>
      </div>
    </div>
  );
}

