-- ─── Intake Draft Support Migration ──────────────────────────────────────────
-- Adds draft status and section tracking for save-and-resume functionality

-- Add 'draft' to the intake_submission_status enum if not already present
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_enum
    WHERE enumlabel = 'draft'
      AND enumtypid = (
        SELECT oid FROM pg_type WHERE typname = 'intake_submission_status'
      )
  ) THEN
    ALTER TYPE public.intake_submission_status ADD VALUE 'draft';
  END IF;
END $$;

-- Add draft_section_idx column to track which section the patient paused on
ALTER TABLE public.patient_intake_submissions
  ADD COLUMN IF NOT EXISTS draft_section_idx INTEGER DEFAULT NULL;

-- Index to quickly find submissions by user/service/form/status
-- Note: partial index on the new 'draft' enum value cannot be used in the same
-- transaction as ADD VALUE — using a plain composite index instead
CREATE INDEX IF NOT EXISTS idx_patient_intake_submissions_draft
  ON public.patient_intake_submissions(user_id, service_type, form_type, status);
