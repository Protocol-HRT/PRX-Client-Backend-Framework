-- ─── Types ────────────────────────────────────────────────────────────────────

DROP TYPE IF EXISTS public.protocol_status CASCADE;
CREATE TYPE public.protocol_status AS ENUM ('active', 'pending', 'paused');

DROP TYPE IF EXISTS public.protocol_category CASCADE;
CREATE TYPE public.protocol_category AS ENUM ('TRT', 'Peptide', 'GLP-1');

DROP TYPE IF EXISTS public.order_status CASCADE;
CREATE TYPE public.order_status AS ENUM ('delivered', 'shipped', 'processing', 'pending');

DROP TYPE IF EXISTS public.shipment_status CASCADE;
CREATE TYPE public.shipment_status AS ENUM ('delivered', 'out_for_delivery', 'in_transit', 'label_created');

DROP TYPE IF EXISTS public.refill_status CASCADE;
CREATE TYPE public.refill_status AS ENUM ('approved', 'pending', 'requires_review');

-- ─── Core Tables ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS public.user_profiles (
  id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  email TEXT NOT NULL UNIQUE,
  full_name TEXT NOT NULL DEFAULT '',
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.patient_protocols (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES public.user_profiles(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  status public.protocol_status NOT NULL DEFAULT 'active',
  start_date TEXT NOT NULL,
  next_refill TEXT NOT NULL,
  dosage TEXT NOT NULL,
  frequency TEXT NOT NULL,
  physician TEXT NOT NULL,
  category public.protocol_category NOT NULL DEFAULT 'TRT',
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.patient_orders (
  id TEXT PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES public.user_profiles(id) ON DELETE CASCADE,
  order_date TEXT NOT NULL,
  items TEXT[] NOT NULL DEFAULT '{}',
  total NUMERIC(10,2) NOT NULL DEFAULT 0,
  status public.order_status NOT NULL DEFAULT 'pending',
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.patient_shipments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES public.user_profiles(id) ON DELETE CASCADE,
  medication TEXT NOT NULL,
  carrier TEXT NOT NULL,
  tracking_number TEXT NOT NULL,
  status public.shipment_status NOT NULL DEFAULT 'label_created',
  estimated_delivery TEXT NOT NULL,
  last_update TEXT NOT NULL,
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.patient_refills (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES public.user_profiles(id) ON DELETE CASCADE,
  medication TEXT NOT NULL,
  requested_date TEXT NOT NULL,
  status public.refill_status NOT NULL DEFAULT 'pending',
  next_ship_date TEXT,
  notes TEXT,
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ─── Indexes ──────────────────────────────────────────────────────────────────

CREATE INDEX IF NOT EXISTS idx_patient_protocols_user_id ON public.patient_protocols(user_id);
CREATE INDEX IF NOT EXISTS idx_patient_orders_user_id ON public.patient_orders(user_id);
CREATE INDEX IF NOT EXISTS idx_patient_shipments_user_id ON public.patient_shipments(user_id);
CREATE INDEX IF NOT EXISTS idx_patient_refills_user_id ON public.patient_refills(user_id);

-- ─── Functions ────────────────────────────────────────────────────────────────

CREATE OR REPLACE FUNCTION public.handle_new_user()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
BEGIN
  INSERT INTO public.user_profiles (id, email, full_name)
  VALUES (
    NEW.id,
    NEW.email,
    COALESCE(NEW.raw_user_meta_data->>'full_name', split_part(NEW.email, '@', 1))
  )
  ON CONFLICT (id) DO NOTHING;
  RETURN NEW;
END;
$$;

-- ─── Enable RLS ───────────────────────────────────────────────────────────────

ALTER TABLE public.user_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.patient_protocols ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.patient_orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.patient_shipments ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.patient_refills ENABLE ROW LEVEL SECURITY;

-- ─── RLS Policies ─────────────────────────────────────────────────────────────

DROP POLICY IF EXISTS "users_manage_own_user_profiles" ON public.user_profiles;
CREATE POLICY "users_manage_own_user_profiles"
ON public.user_profiles FOR ALL TO authenticated
USING (id = auth.uid()) WITH CHECK (id = auth.uid());

DROP POLICY IF EXISTS "users_manage_own_patient_protocols" ON public.patient_protocols;
CREATE POLICY "users_manage_own_patient_protocols"
ON public.patient_protocols FOR ALL TO authenticated
USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());

DROP POLICY IF EXISTS "users_manage_own_patient_orders" ON public.patient_orders;
CREATE POLICY "users_manage_own_patient_orders"
ON public.patient_orders FOR ALL TO authenticated
USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());

DROP POLICY IF EXISTS "users_manage_own_patient_shipments" ON public.patient_shipments;
CREATE POLICY "users_manage_own_patient_shipments"
ON public.patient_shipments FOR ALL TO authenticated
USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());

DROP POLICY IF EXISTS "users_manage_own_patient_refills" ON public.patient_refills;
CREATE POLICY "users_manage_own_patient_refills"
ON public.patient_refills FOR ALL TO authenticated
USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());

-- ─── Triggers ─────────────────────────────────────────────────────────────────

DROP TRIGGER IF EXISTS on_auth_user_created ON auth.users;
CREATE TRIGGER on_auth_user_created
  AFTER INSERT ON auth.users
  FOR EACH ROW EXECUTE FUNCTION public.handle_new_user();

-- ─── Demo User + Sample Data ──────────────────────────────────────────────────

DO $$
DECLARE
  demo_uuid UUID := gen_random_uuid();
BEGIN
  -- Create demo auth user
  INSERT INTO auth.users (
    id, instance_id, aud, role, email, encrypted_password, email_confirmed_at,
    created_at, updated_at, raw_user_meta_data, raw_app_meta_data,
    is_sso_user, is_anonymous, confirmation_token, confirmation_sent_at,
    recovery_token, recovery_sent_at, email_change_token_new, email_change,
    email_change_sent_at, email_change_token_current, email_change_confirm_status,
    reauthentication_token, reauthentication_sent_at, phone, phone_change,
    phone_change_token, phone_change_sent_at
  ) VALUES (
    demo_uuid, '00000000-0000-0000-0000-000000000000', 'authenticated', 'authenticated',
    'patient@protocolhr.com', crypt('demo1234', gen_salt('bf', 10)), now(), now(), now(),
    jsonb_build_object('full_name', 'Alex Johnson'),
    jsonb_build_object('provider', 'email', 'providers', ARRAY['email']::TEXT[]),
    false, false, '', null, '', null, '', '', null, '', 0, '', null, null, '', '', null
  ) ON CONFLICT (id) DO NOTHING;

  -- Protocols
  INSERT INTO public.patient_protocols (user_id, name, status, start_date, next_refill, dosage, frequency, physician, category) VALUES
    (demo_uuid, 'Testosterone Cypionate', 'active', 'Feb 12, 2026', 'Apr 28, 2026', '200mg/mL', '0.5mL twice weekly', 'Dr. Sarah Chen, MD', 'TRT'),
    (demo_uuid, 'Sermorelin / GHRP-2', 'active', 'Mar 1, 2026', 'May 1, 2026', '300mcg / 100mcg', 'Nightly subcutaneous', 'Dr. Sarah Chen, MD', 'Peptide'),
    (demo_uuid, 'Anastrozole', 'active', 'Feb 12, 2026', 'Apr 28, 2026', '0.25mg', 'Twice weekly (with T)', 'Dr. Sarah Chen, MD', 'TRT')
  ON CONFLICT (id) DO NOTHING;

  -- Orders
  INSERT INTO public.patient_orders (id, user_id, order_date, items, total, status) VALUES
    ('PHR-882341', demo_uuid, 'Mar 28, 2026', ARRAY['Testosterone Cypionate 200mg/mL x 10mL', 'Anastrozole 0.25mg x 60ct', 'Sermorelin/GHRP-2 blend x 5mg'], 149, 'delivered'),
    ('PHR-771209', demo_uuid, 'Feb 26, 2026', ARRAY['Testosterone Cypionate 200mg/mL x 10mL', 'Anastrozole 0.25mg x 60ct'], 149, 'delivered'),
    ('PHR-660118', demo_uuid, 'Jan 25, 2026', ARRAY['Blueprint Protocol Assessment', 'Initial Lab Kit'], 49, 'delivered')
  ON CONFLICT (id) DO NOTHING;

  -- Shipments
  INSERT INTO public.patient_shipments (user_id, medication, carrier, tracking_number, status, estimated_delivery, last_update) VALUES
    (demo_uuid, 'TRT Protocol - April Refill', 'FedEx', '7489 2341 8823', 'in_transit', 'Apr 12, 2026', 'Departed Memphis hub - Apr 10, 4:22 AM'),
    (demo_uuid, 'Sermorelin/GHRP-2 Blend', 'UPS', '1Z 882 341 09', 'label_created', 'Apr 16, 2026', 'Label created - awaiting pickup')
  ON CONFLICT (id) DO NOTHING;

  -- Refills
  INSERT INTO public.patient_refills (user_id, medication, requested_date, status, next_ship_date, notes) VALUES
    (demo_uuid, 'Testosterone Cypionate 200mg/mL', 'Apr 8, 2026', 'approved', 'Apr 12, 2026', 'Auto-approved - within protocol parameters'),
    (demo_uuid, 'Sermorelin / GHRP-2 Blend', 'Apr 8, 2026', 'approved', 'Apr 14, 2026', 'Physician reviewed and approved'),
    (demo_uuid, 'Anastrozole 0.25mg', 'Apr 9, 2026', 'pending', null, 'Awaiting physician review - typically 24 hours')
  ON CONFLICT (id) DO NOTHING;

EXCEPTION
  WHEN OTHERS THEN
    RAISE NOTICE 'Sample data insertion failed: %', SQLERRM;
END $$;
