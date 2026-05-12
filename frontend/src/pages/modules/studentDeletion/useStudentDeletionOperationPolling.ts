import type { Dispatch, SetStateAction } from "react";
import { useEffect, useRef, useState } from "react";
import { apiRequest } from "../../../lib/api";
import type { OperationTrackerPayload } from "./studentDeletionTypes";

type Params = {
  trackingId: string | null;
  setTrackingId: Dispatch<SetStateAction<string | null>>;
  pushMessage: (msg: string) => void;
  pushError: (msg: string) => void;
  loadStudents: () => Promise<void>;
  loadHistory: () => Promise<void>;
};

export function useStudentDeletionOperationPolling({
  trackingId,
  setTrackingId,
  pushMessage,
  pushError,
  loadStudents,
  loadHistory,
}: Params): OperationTrackerPayload | null {
  const [tracker, setTracker] = useState<OperationTrackerPayload | null>(null);
  const terminalHandledRef = useRef<Set<string>>(new Set());
  const dismissTimerRef = useRef<number | null>(null);

  useEffect(() => {
    if (!trackingId) {
      setTracker(null);
      return;
    }

    let cancelled = false;

    async function pollOnce() {
      try {
        const status = await apiRequest<OperationTrackerPayload>(`/students/actions/${trackingId}`);
        if (!cancelled) {
          setTracker(status);
        }
      } catch (e) {
        if (!cancelled) {
          pushError(e instanceof Error ? e.message : "Failed to load operation status.");
        }
      }
    }

    void pollOnce();
    const intervalId = window.setInterval(() => {
      void pollOnce();
    }, 2000);

    return () => {
      cancelled = true;
      window.clearInterval(intervalId);
    };
  }, [trackingId, pushError]);

  useEffect(() => {
    return () => {
      if (dismissTimerRef.current !== null) {
        window.clearTimeout(dismissTimerRef.current);
      }
    };
  }, []);

  useEffect(() => {
    if (!tracker) {
      return;
    }
    if (tracker.status !== "completed" && tracker.status !== "failed") {
      return;
    }
    const terminalKey = `${tracker.operation_id}:${tracker.status}`;
    if (terminalHandledRef.current.has(terminalKey)) {
      return;
    }
    terminalHandledRef.current.add(terminalKey);

    if (tracker.status === "failed") {
      const detail = tracker.error?.trim()
        ? tracker.error
        : "The job failed before processing finished.";
      pushError(`Bulk ${tracker.action} failed: ${detail}`);
    } else if (tracker.failed > 0) {
      const detail = tracker.error?.trim()
        ? tracker.error
        : `${tracker.failed} account(s) failed; ${tracker.ok} succeeded.`;
      pushError(`Bulk ${tracker.action} completed with errors: ${detail}`);
    } else {
      pushMessage(`Bulk ${tracker.action} finished (${tracker.status}): OK ${tracker.ok}, failed ${tracker.failed}.`);
    }
    void loadStudents();
    void loadHistory();

    if (dismissTimerRef.current !== null) {
      window.clearTimeout(dismissTimerRef.current);
    }
    dismissTimerRef.current = window.setTimeout(() => {
      dismissTimerRef.current = null;
      setTrackingId(null);
      setTracker(null);
    }, 6000);
  }, [tracker, pushMessage, pushError, loadStudents, loadHistory, setTrackingId]);

  return tracker;
}
