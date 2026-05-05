import type { OperationTrackerPayload } from "./studentDeletionTypes";

type Props = {
  messages: string[];
  errors: string[];
  trackingId: string | null;
  tracker: OperationTrackerPayload | null;
};

export function StudentDeletionAlerts({ messages, errors, trackingId, tracker }: Props) {
  return (
    <>
      {messages.map((msg, idx) => (
        <p key={`msg-${idx}-${msg.slice(0, 24)}`} className="toast toast-success">
          {msg}
        </p>
      ))}
      {errors.map((msg, idx) => (
        <p key={`err-${idx}-${msg.slice(0, 24)}`} className="toast toast-error">
          {msg}
        </p>
      ))}

      {trackingId && tracker ? (
        <div className="tracker-banner">
          <strong>Operation {tracker.operation_id}</strong>
          <span className="hint-inline">
            {" "}
            — {tracker.action} · {tracker.status} · progress {tracker.processed}/{tracker.total} · OK {tracker.ok} · failed {tracker.failed}
          </span>
        </div>
      ) : null}
    </>
  );
}
