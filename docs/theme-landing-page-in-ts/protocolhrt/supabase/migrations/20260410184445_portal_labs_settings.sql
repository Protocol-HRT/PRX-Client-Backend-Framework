-- ─── Lab Panel Tables ────────────────────────────────────────────────────────

DROP TYPE IF EXISTS public.lab_panel_status CASCADE;
CREATE TYPE public.lab_panel_status AS ENUM ('resulted', 'pending', 'processing');

DROP TYPE IF EXISTS public.lab_result_status CASCADE;
CREATE TYPE public.lab_result_status AS ENUM ('optimal', 'normal', 'low', 'high', 'critical');

DROP TYPE IF EXISTS public.lab_result_trend CASCADE;
CREATE TYPE public.lab_result_trend AS ENUM ('up', 'down', 'stable');

CREATE TABLE IF NOT EXISTS public.patient_lab_panels (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES public.user_profiles(id) ON DELETE CASCADE,
  panel_name TEXT NOT NULL,
  ordered_date TEXT NOT NULL,
  result_date TEXT NOT NULL,
  ordered_by TEXT NOT NULL,
  status public.lab_panel_status NOT NULL DEFAULT 'pending',
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.patient_lab_results (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  panel_id UUID NOT NULL REFERENCES public.patient_lab_panels(id) ON DELETE CASCADE,
  marker TEXT NOT NULL,
  value NUMERIC(10,3) NOT NULL,
  unit TEXT NOT NULL,
  reference_min NUMERIC(10,3) NOT NULL,
  reference_max NUMERIC(10,3) NOT NULL,
  optimal_min NUMERIC(10,3),
  optimal_max NUMERIC(10,3),
  status public.lab_result_status NOT NULL DEFAULT 'normal',
  trend public.lab_result_trend,
  previous_value NUMERIC(10,3),
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ─── Notification Preferences Table ──────────────────────────────────────────

CREATE TABLE IF NOT EXISTS public.patient_notification_prefs (
  user_id UUID PRIMARY KEY REFERENCES public.user_profiles(id) ON DELETE CASCADE,
  refill_reminders BOOLEAN NOT NULL DEFAULT TRUE,
  lab_result_alerts BOOLEAN NOT NULL DEFAULT TRUE,
  physician_messages BOOLEAN NOT NULL DEFAULT TRUE,
  shipment_updates BOOLEAN NOT NULL DEFAULT TRUE,
  monthly_reports BOOLEAN NOT NULL DEFAULT FALSE,
  updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ─── Extended Profile Columns ─────────────────────────────────────────────────

ALTER TABLE public.user_profiles
  ADD COLUMN IF NOT EXISTS phone TEXT DEFAULT '',
  ADD COLUMN IF NOT EXISTS date_of_birth TEXT DEFAULT '',
  ADD COLUMN IF NOT EXISTS address TEXT DEFAULT '',
  ADD COLUMN IF NOT EXISTS city TEXT DEFAULT '',
  ADD COLUMN IF NOT EXISTS state TEXT DEFAULT '',
  ADD COLUMN IF NOT EXISTS zip TEXT DEFAULT '';

-- ─── Indexes ──────────────────────────────────────────────────────────────────

CREATE INDEX IF NOT EXISTS idx_patient_lab_panels_user_id ON public.patient_lab_panels(user_id);
CREATE INDEX IF NOT EXISTS idx_patient_lab_results_panel_id ON public.patient_lab_results(panel_id);

-- ─── Enable RLS ───────────────────────────────────────────────────────────────

ALTER TABLE public.patient_lab_panels ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.patient_lab_results ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.patient_notification_prefs ENABLE ROW LEVEL SECURITY;

-- ─── RLS Policies ─────────────────────────────────────────────────────────────

DROP POLICY IF EXISTS "users_manage_own_patient_lab_panels" ON public.patient_lab_panels;
CREATE POLICY "users_manage_own_patient_lab_panels"
ON public.patient_lab_panels FOR ALL TO authenticated
USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());

DROP POLICY IF EXISTS "users_read_own_patient_lab_results" ON public.patient_lab_results;
CREATE POLICY "users_read_own_patient_lab_results"
ON public.patient_lab_results FOR SELECT TO authenticated
USING (
  panel_id IN (
    SELECT id FROM public.patient_lab_panels WHERE user_id = auth.uid()
  )
);

DROP POLICY IF EXISTS "users_manage_own_notification_prefs" ON public.patient_notification_prefs;
CREATE POLICY "users_manage_own_notification_prefs"
ON public.patient_notification_prefs FOR ALL TO authenticated
USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());
