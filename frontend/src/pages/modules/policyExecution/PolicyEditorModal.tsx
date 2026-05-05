import { useEffect, useState } from "react";
import { apiRequest } from "../../../lib/api";
import type { PolicyRow } from "./policyTypes";

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

export function PolicyEditorModal({ open, policy, disabled, onClose, onSaved }: Props) {
  const [name, setName] = useState("");
  const [action, setAction] = useState<"suspend" | "delete">("suspend");
  const [department, setDepartment] = useState("");
  const [schoolYear, setSchoolYear] = useState("");
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
      setAction(policy.action === "delete" ? "delete" : "suspend");
      const rules = policy.rule_json ?? {};
      setDepartment(typeof rules.department === "string" ? rules.department : "");
      setSchoolYear(typeof rules.school_year === "string" ? rules.school_year : "");
      setExecutionLocal(datetimeLocalFromIso(policy.execution_at));
      setCronExpression(policy.cron_expression ?? "");
      setIsActive(policy.is_active);
    } else {
      setName("");
      setAction("suspend");
      setDepartment("");
      setSchoolYear("");
      setExecutionLocal("");
      setCronExpression("");
      setIsActive(true);
    }
  }, [open, policy]);

  if (!open) {
    return null;
  }

  const formDisabled = disabled || isBusy;

  async function submit() {
    setError("");
    const deptTrimmed = department.trim();
    const yearTrimmed = schoolYear.trim();
    if (!deptTrimmed && !yearTrimmed) {
      setError("Provide at least department or school year for scope.");
      return;
    }

    const ruleJson: Record<string, string> = {};
    if (deptTrimmed) {
      ruleJson.department = deptTrimmed;
    }
    if (yearTrimmed) {
      ruleJson.school_year = yearTrimmed;
    }

    const executionAt = isoFromDatetimeLocal(executionLocal);

    try {
      const body: Record<string, unknown> = {
        name: name.trim(),
        action,
        rule_json: ruleJson,
        cron_expression: cronExpression.trim() || null,
        is_active: isActive,
        execution_at: executionAt,
      };

      if (!policy && !name.trim()) {
        setError("Name is required.");
        return;
      }

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
          <label>
            Execution at (optional)
            <input type="datetime-local" value={executionLocal} onChange={(ev) => setExecutionLocal(ev.target.value)} disabled={formDisabled} />
          </label>
          <label>
            Cron expression
            <input value={cronExpression} onChange={(ev) => setCronExpression(ev.target.value)} placeholder="0 6 * * *" disabled={formDisabled} />
          </label>
          <label className="perm-row">
            <input type="checkbox" checked={isActive} onChange={(ev) => setIsActive(ev.target.checked)} disabled={formDisabled} />
            Active
          </label>
        </div>
        <p className="hint">
          Policies evaluate queued automation jobs against registry rows. Leave datetime empty with cron only when your scheduler triggers evaluation directly.
        </p>
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
