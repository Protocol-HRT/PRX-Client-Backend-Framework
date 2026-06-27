import { NextRequest, NextResponse } from 'next/server';
import { createServerClient } from '@supabase/ssr';
import { cookies } from 'next/headers';

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const { planName, planBadge, orderNumber } = body;

    // Get authenticated user from Supabase session
    const cookieStore = await cookies();
    const supabase = createServerClient(
      process.env.NEXT_PUBLIC_SUPABASE_URL!,
      process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!,
      {
        cookies: {
          getAll() {
            return cookieStore.getAll();
          },
          setAll(cookiesToSet) {
            try {
              cookiesToSet.forEach(({ name, value, options }) =>
                cookieStore.set(name, value, options)
              );
            } catch {
              // read-only context
            }
          },
        },
      }
    );

    const {
      data: { user },
    } = await supabase.auth.getUser();

    if (!user?.email) {
      return NextResponse.json({ error: 'Unauthenticated' }, { status: 401 });
    }

    const patientName =
      user.user_metadata?.full_name ||
      user.user_metadata?.name ||
      user.email.split('@')[0];

    const siteUrl =
      process.env.NEXT_PUBLIC_SITE_URL || 'https://protocolhr4988.builtwithrocket.new';
    const portalUrl = `${siteUrl}/patient-portal`;

    // Call the Supabase Edge Function
    const edgeFnUrl = `${process.env.NEXT_PUBLIC_SUPABASE_URL}/functions/v1/send-portal-access-email`;

    const edgeRes = await fetch(edgeFnUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY}`,
      },
      body: JSON.stringify({
        to: user.email,
        patientName,
        planName,
        planBadge,
        orderNumber,
        portalUrl,
      }),
    });

    const result = await edgeRes.json();

    if (!edgeRes.ok) {
      console.error('[send-portal-email] Edge function error:', result);
      return NextResponse.json({ error: result.error || 'Email send failed' }, { status: 500 });
    }

    return NextResponse.json({ success: true, emailId: result.id });
  } catch (err) {
    console.error('[send-portal-email] Unexpected error:', err);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}
