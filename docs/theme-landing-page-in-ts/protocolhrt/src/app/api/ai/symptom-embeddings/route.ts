import { NextRequest, NextResponse } from 'next/server';
import { createClient } from '@supabase/supabase-js';

// Use service role for server-side embedding operations
function getSupabaseAdmin() {
  return createClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL!,
    process.env.SUPABASE_SERVICE_ROLE_KEY || process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!
  );
}

async function generateEmbedding(text: string): Promise<number[]> {
  const apiKey = process.env.OPENAI_API_KEY;
  if (!apiKey || apiKey === 'your-openai-api-key-here') {
    throw new Error('OpenAI API key is not configured. Please set OPENAI_API_KEY in your environment variables.');
  }

  const response = await fetch('https://api.openai.com/v1/embeddings', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${apiKey}`,
    },
    body: JSON.stringify({
      model: 'text-embedding-3-small',
      input: text,
      dimensions: 1536,
    }),
  });

  if (!response.ok) {
    const err = await response.json().catch(() => ({}));
    throw new Error(`OpenAI embeddings API error: ${response.status} — ${err?.error?.message || 'Unknown error'}`);
  }

  const data = await response.json();
  return data.data[0].embedding as number[];
}

// Ensure all protocol embeddings are seeded (run once, idempotent)
async function ensureProtocolEmbeddings(supabase: ReturnType<typeof getSupabaseAdmin>) {
  const { data: protocols } = await supabase
    .from('protocol_embeddings')
    .select('protocol_key, embedding')
    .is('embedding', null);

  if (!protocols || protocols.length === 0) return;

  // Fetch descriptions for protocols missing embeddings
  const { data: allProtocols } = await supabase
    .from('protocol_embeddings')
    .select('protocol_key, description')
    .in('protocol_key', protocols.map((p) => p.protocol_key));

  if (!allProtocols) return;

  for (const protocol of allProtocols) {
    try {
      const embedding = await generateEmbedding(protocol.description);
      await supabase
        .from('protocol_embeddings')
        .update({ embedding: JSON.stringify(embedding) })
        .eq('protocol_key', protocol.protocol_key);
    } catch (err) {
      console.error(`Failed to embed protocol ${protocol.protocol_key}:`, err);
    }
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { symptoms, sessionId, userId } = body as {
      symptoms: string[];
      sessionId: string;
      userId?: string;
    };

    if (!symptoms?.length || !sessionId) {
      return NextResponse.json(
        { error: 'Missing required fields: symptoms, sessionId' },
        { status: 400 }
      );
    }

    const supabase = getSupabaseAdmin();

    // Ensure protocol embeddings are seeded
    await ensureProtocolEmbeddings(supabase);

    // Build symptom text for embedding
    const symptomText = `Patient symptoms and health concerns: ${symptoms.join(', ')}`;

    // Generate embedding for the patient's symptoms
    const embedding = await generateEmbedding(symptomText);

    // Store symptom embedding in database
    const { data: savedEmbedding, error: saveError } = await supabase
      .from('symptom_embeddings')
      .insert({
        session_id: sessionId,
        user_id: userId || null,
        symptoms,
        symptom_text: symptomText,
        embedding: JSON.stringify(embedding),
      })
      .select('id')
      .single();

    if (saveError) {
      console.error('Failed to save symptom embedding:', saveError);
    }

    // Run vector similarity search against protocol embeddings
    const { data: matches, error: matchError } = await supabase.rpc('match_protocol_by_symptoms', {
      query_embedding: JSON.stringify(embedding),
      match_threshold: 0.65,
      match_count: 3,
    });

    if (matchError) {
      console.error('Vector similarity search error:', matchError);
      return NextResponse.json({ recommendations: [], embeddingId: savedEmbedding?.id || null });
    }

    const recommendations = (matches || []).map((m: any) => ({
      protocolKey: m.protocol_key,
      protocolName: m.protocol_name,
      description: m.description,
      similarity: parseFloat(m.similarity),
    }));

    // Update the stored embedding with the top match
    if (savedEmbedding?.id && recommendations.length > 0) {
      await supabase
        .from('symptom_embeddings')
        .update({
          matched_protocol_key: recommendations[0].protocolKey,
          similarity_score: recommendations[0].similarity,
        })
        .eq('id', savedEmbedding.id);
    }

    return NextResponse.json({
      recommendations,
      embeddingId: savedEmbedding?.id || null,
    });
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Unknown error';
    console.error('Symptom embeddings API error:', message);
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
