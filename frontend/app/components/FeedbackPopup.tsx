"use client";

import { useEffect } from "react";

type FeedbackPopupProps = {
  message: string;
  onDismiss: () => void;
};

export default function FeedbackPopup({ message, onDismiss }: FeedbackPopupProps) {
  useEffect(() => {
    const timeout = window.setTimeout(onDismiss, 5000);
    return () => window.clearTimeout(timeout);
  }, [onDismiss]);

  return <div className="feedback-popup" role="status" aria-live="polite"><span className="feedback-icon">✓</span><p>{message}</p><button type="button" aria-label="Dismiss message" onClick={onDismiss}>×</button></div>;
}
