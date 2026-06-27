-- ─── Clinical Intake Forms Migration ─────────────────────────────────────────
-- Tracks service purchases, form submissions, and re-assessment scheduling

-- ─── Types ────────────────────────────────────────────────────────────────────

DROP TYPE IF EXISTS public.intake_service_type CASCADE;
CREATE TYPE public.intake_service_type AS ENUM ('TRT', 'GLP1', 'FEMALE_HRT');

DROP TYPE IF EXISTS public.intake_form_type CASCADE;
CREATE TYPE public.intake_form_type AS ENUM ('SCREENING', 'REASSESSMENT');

DROP TYPE IF EXISTS public.intake_submission_status CASCADE;
CREATE TYPE public.intake_submission_status AS ENUM ('pending', 'submitted', 'reviewed', 'approved', 'flagged');

-- ─── Service Purchases Table ──────────────────────────────────────────────────
-- Records which services a patient has purchased (drives which forms appear)

CREATE TABLE IF NOT EXISTS public.patient_service_purchases (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES public.user_profiles(id) ON DELETE CASCADE,
  service_type public.intake_service_type NOT NULL,
  purchased_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  order_id TEXT,
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_patient_service_purchases_user_service
  ON public.patient_service_purchases(user_id, service_type);

CREATE INDEX IF NOT EXISTS idx_patient_service_purchases_user_id
  ON public.patient_service_purchases(user_id);

-- ─── Intake Form Submissions Table ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS public.patient_intake_submissions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES public.user_profiles(id) ON DELETE CASCADE,
  service_type public.intake_service_type NOT NULL,
  form_type public.intake_form_type NOT NULL DEFAULT 'SCREENING',
  status public.intake_submission_status NOT NULL DEFAULT 'pending',
  answers JSONB NOT NULL DEFAULT '{}',
  flagged_questions JSONB DEFAULT '[]',
  submitted_at TIMESTAMPTZ,
  reviewed_at TIMESTAMPTZ,
  reviewer_notes TEXT,
  emr_sync_status TEXT DEFAULT 'pending',
  emr_synced_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_patient_intake_submissions_user_id
  ON public.patient_intake_submissions(user_id);

CREATE INDEX IF NOT EXISTS idx_patient_intake_submissions_service_type
  ON public.patient_intake_submissions(service_type, form_type);

-- ─── Re-Assessment Schedule Table ────────────────────────────────────────────
-- Tracks when re-assessment forms should appear in the portal

CREATE TABLE IF NOT EXISTS public.patient_reassessment_schedule (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES public.user_profiles(id) ON DELETE CASCADE,
  service_type public.intake_service_type NOT NULL,
  screening_submitted_at TIMESTAMPTZ NOT NULL,
  reassessment_due_at TIMESTAMPTZ NOT NULL,
  reassessment_notified_at TIMESTAMPTZ,
  reassessment_completed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_patient_reassessment_schedule_user_service
  ON public.patient_reassessment_schedule(user_id, service_type);

CREATE INDEX IF NOT EXISTS idx_patient_reassessment_schedule_user_id
  ON public.patient_reassessment_schedule(user_id);

-- ─── Enable RLS ───────────────────────────────────────────────────────────────

ALTER TABLE public.patient_service_purchases ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.patient_intake_submissions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.patient_reassessment_schedule ENABLE ROW LEVEL SECURITY;

-- ─── RLS Policies ─────────────────────────────────────────────────────────────

DROP POLICY IF EXISTS "users_manage_own_patient_service_purchases" ON public.patient_service_purchases;
CREATE POLICY "users_manage_own_patient_service_purchases"
ON public.patient_service_purchases FOR ALL TO authenticated
USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());

DROP POLICY IF EXISTS "users_manage_own_patient_intake_submissions" ON public.patient_intake_submissions;
CREATE POLICY "users_manage_own_patient_intake_submissions"
ON public.patient_intake_submissions FOR ALL TO authenticated
USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());

DROP POLICY IF EXISTS "users_manage_own_patient_reassessment_schedule" ON public.patient_reassessment_schedule;
CREATE POLICY "users_manage_own_patient_reassessment_schedule"
ON public.patient_reassessment_schedule FOR ALL TO authenticated
USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());

-- ─── Updated At Trigger ───────────────────────────────────────────────────────

CREATE OR REPLACE FUNCTION public.update_intake_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS intake_submissions_updated_at ON public.patient_intake_submissions;
CREATE TRIGGER intake_submissions_updated_at
  BEFORE UPDATE ON public.patient_intake_submissions
  FOR EACH ROW EXECUTE FUNCTION public.update_intake_updated_at();

-- ─── Demo Data: Seed TRT purchase for demo user ───────────────────────────────

DO $$
DECLARE
  demo_user_id UUID;
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_schema = 'public' AND table_name = 'user_profiles'
  ) THEN
    SELECT id INTO demo_user_id FROM public.user_profiles LIMIT 1;

    IF demo_user_id IS NOT NULL THEN
      INSERT INTO public.patient_service_purchases (user_id, service_type, order_id)
      VALUES (demo_user_id, 'TRT', 'PHR-882341')
      ON CONFLICT (user_id, service_type) DO NOTHING;
    END IF;
  END IF;
EXCEPTION
  WHEN OTHERS THEN
    RAISE NOTICE 'Demo data insertion failed: %', SQLERRM;
END $$;
