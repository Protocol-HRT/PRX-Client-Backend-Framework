-- ─── Enable pgvector extension ───────────────────────────────────────────────
-- pgvector is pre-installed in Supabase managed environments
CREATE EXTENSION IF NOT EXISTS vector;

-- ─── Protocol embeddings seed table ──────────────────────────────────────────
-- Stores canonical protocol descriptions with their embeddings for similarity search
CREATE TABLE IF NOT EXISTS public.protocol_embeddings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  protocol_key TEXT NOT NULL UNIQUE,
  protocol_name TEXT NOT NULL,
  description TEXT NOT NULL,
  embedding vector(1536),
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ─── Symptom embeddings table ─────────────────────────────────────────────────
-- Stores per-session symptom vectors for contextual recommendation matching
CREATE TABLE IF NOT EXISTS public.symptom_embeddings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  session_id TEXT NOT NULL,
  user_id UUID REFERENCES public.user_profiles(id) ON DELETE SET NULL,
  symptoms TEXT[] NOT NULL DEFAULT '{}',
  symptom_text TEXT NOT NULL,
  embedding vector(1536),
  matched_protocol_key TEXT,
  similarity_score NUMERIC(6,4),
  created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- ─── Indexes ──────────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_symptom_embeddings_session_id ON public.symptom_embeddings(session_id);
CREATE INDEX IF NOT EXISTS idx_symptom_embeddings_user_id ON public.symptom_embeddings(user_id);
CREATE INDEX IF NOT EXISTS idx_protocol_embeddings_key ON public.protocol_embeddings(protocol_key);

-- Vector similarity indexes (IVFFlat for approximate nearest neighbor)
CREATE INDEX IF NOT EXISTS idx_protocol_embeddings_vector
  ON public.protocol_embeddings USING ivfflat (embedding vector_cosine_ops)
  WITH (lists = 10);

CREATE INDEX IF NOT EXISTS idx_symptom_embeddings_vector
  ON public.symptom_embeddings USING ivfflat (embedding vector_cosine_ops)
  WITH (lists = 10);

-- ─── RLS ──────────────────────────────────────────────────────────────────────
ALTER TABLE public.protocol_embeddings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.symptom_embeddings ENABLE ROW LEVEL SECURITY;

-- Protocol embeddings are publicly readable (reference data)
DROP POLICY IF EXISTS "public_read_protocol_embeddings" ON public.protocol_embeddings;
CREATE POLICY "public_read_protocol_embeddings"
  ON public.protocol_embeddings
  FOR SELECT
  TO public
  USING (true);

-- Symptom embeddings: anyone can insert (anonymous sessions), owners can read their own
DROP POLICY IF EXISTS "insert_symptom_embeddings" ON public.symptom_embeddings;
CREATE POLICY "insert_symptom_embeddings"
  ON public.symptom_embeddings
  FOR INSERT
  TO public
  WITH CHECK (true);

DROP POLICY IF EXISTS "select_own_symptom_embeddings" ON public.symptom_embeddings;
CREATE POLICY "select_own_symptom_embeddings"
  ON public.symptom_embeddings
  FOR SELECT
  TO public
  USING (true);

-- ─── Vector similarity search function ───────────────────────────────────────
CREATE OR REPLACE FUNCTION public.match_protocol_by_symptoms(
  query_embedding vector(1536),
  match_threshold NUMERIC DEFAULT 0.70,
  match_count INT DEFAULT 3
)
RETURNS TABLE (
  protocol_key TEXT,
  protocol_name TEXT,
  description TEXT,
  similarity NUMERIC
)
LANGUAGE sql
STABLE
SECURITY DEFINER
AS $$
  SELECT
    pe.protocol_key,
    pe.protocol_name,
    pe.description,
    (1 - (pe.embedding <=> query_embedding))::NUMERIC AS similarity
  FROM public.protocol_embeddings pe
  WHERE pe.embedding IS NOT NULL
    AND (1 - (pe.embedding <=> query_embedding)) > match_threshold
  ORDER BY pe.embedding <=> query_embedding
  LIMIT match_count;
$$;

-- ─── Seed canonical protocol descriptions ────────────────────────────────────
-- Embeddings will be generated and upserted by the API route on first use
INSERT INTO public.protocol_embeddings (protocol_key, protocol_name, description)
VALUES
  ('him_trt', 'HIM TRT Protocol',
   'Testosterone replacement therapy for men experiencing low testosterone symptoms including decreased libido, low energy, fatigue, reduced strength, mood changes, poor focus, decreased erections, and declining exercise performance. Includes monthly testosterone medication, live physician video call, and monthly delivery.'),
  ('him_peptide', 'HIM Peptide Protocol',
   'Growth hormone peptide protocol for men focused on recovery, anti-aging, body composition improvement, muscle building, and performance optimization. Includes sermorelin, BPC-157, or NAD+ peptides with async physician approval.'),
  ('him_metabolic', 'HIM Metabolic Protocol',
   'GLP-1 weight loss and metabolic optimization protocol for men seeking to lose weight, improve insulin sensitivity, reduce body fat, and enhance metabolic health. Includes semaglutide or tirzepatide with physician oversight.'),
  ('him_cognitive', 'HIM Cognitive Protocol',
   'Cognitive enhancement and neuroprotection protocol for men experiencing brain fog, poor focus, memory decline, and mental fatigue. Targets mental clarity, concentration, and neurological performance.'),
  ('her_hormone', 'HER Hormone Balance Protocol',
   'Female hormone replacement therapy for women experiencing perimenopause or menopause symptoms including hot flashes, night sweats, sleep disruption, mood swings, vaginal dryness, decreased libido, and fatigue. Includes estrogen, progesterone, and testosterone balancing with live physician video call.'),
  ('her_thyroid', 'HER Thyroid & Metabolic Protocol',
   'Thyroid optimization and metabolic support for women with thyroid dysfunction, low energy, weight gain, hair loss, cold intolerance, and metabolic slowdown. Requires live physician video call.'),
  ('her_body_composition', 'HER Body Composition Protocol',
   'GLP-1 weight loss and body recomposition protocol for women seeking to lose weight, reduce body fat, build lean muscle, and improve metabolic health. Includes semaglutide or tirzepatide.'),
  ('her_longevity', 'HER Longevity Protocol',
   'Anti-aging, cellular health, and vitality protocol for women focused on longevity, skin and hair health, energy restoration, and overall wellness optimization through peptides and hormone support.'),
  ('blueprint', 'Protocol Blueprint Assessment',
   'Personalized $49 protocol blueprint assessment for patients seeking a physician-reviewed hormone and peptide protocol. Covers diet, sleep, supplements, and specific peptide recommendations. Fully credited toward first treatment order.')
ON CONFLICT (protocol_key) DO UPDATE
  SET protocol_name = EXCLUDED.protocol_name,
      description = EXCLUDED.description;
