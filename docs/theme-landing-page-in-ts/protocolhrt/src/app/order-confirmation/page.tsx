'use client';
import React, { useEffect, useState, useRef, Suspense } from 'react';
import Link from 'next/link';
import { useSearchParams, useRouter } from 'next/navigation';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import { createClient } from '@/lib/supabase/client';
import { triggerCheckoutComplete } from '@/lib/n8n/webhooks';

// ─── Plan Configs ────────────────────────────────────────────────────────────
const PLANS = {
  trt: {
    name: 'Men\'s TRT Protocol',
    badge: 'TRT Program',
    price: 149,
    originalPrice: 599,
    billingCycle: 'month',
    savingsLabel: '🎉 Launch Price Savings',
    savings: 450,
    tagline: 'All-in monthly · medication included · physician call',
    includes: [
      'Live physician video call — required before prescribing',
      'Testosterone medication included — all-in monthly pricing',
      'Full AI protocol build delivered before your physician visit',
      'Blood work kit included if clinically indicated',
      'Monthly refill — delivered to your door',
      'Stack up to 3 peptides at checkout (async approval)',
    ],
    subscriptionDetails: [
      { label: 'Billing Cycle', value: 'Monthly — renews automatically' },
      { label: 'First Charge', value: '$149 today' },
      { label: 'Subsequent Charges', value: '$149/mo — cancel anytime' },
      { label: 'Price Lock', value: 'Launch rate locked for life of subscription' },
      { label: 'Cancellation', value: 'Cancel anytime — no penalty' },
    ],
  },
  blueprint: {
    name: 'Protocol Blueprint Assessment',
    badge: 'Blueprint',
    price: 49,
    originalPrice: 149,
    billingCycle: 'one-time',
    savingsLabel: '💡 Assessment Credit',
    savings: 49,
    tagline: 'One-time assessment · $49 credited toward TRT if you upgrade',
    includes: [
      'Full AI-generated protocol blueprint based on your intake',
      'Physician async review of your protocol',
      'Personalized hormone optimization roadmap',
      'Lab panel recommendations included',
      '$49 credited toward TRT or peptide order if you upgrade',
      'Access to patient portal for 90 days',
    ],
    subscriptionDetails: [
      { label: 'Billing Type', value: 'One-time charge — no subscription' },
      { label: 'Charge Today', value: '$49 (non-recurring)' },
      { label: 'Assessment Credit', value: '$49 applied toward any protocol upgrade' },
      { label: 'Portal Access', value: '90-day patient portal access included' },
      { label: 'Upgrade Path', value: 'Upgrade to TRT or peptide protocol anytime' },
    ],
  },
};

// ─── Next Steps ───────────────────────────────────────────────────────────────
const NEXT_STEPS_TRT = [
  {
    step: '01',
    title: 'Check Your Inbox',
    description: 'A confirmation email with your order details and intake instructions has been sent. Check spam if you don\'t see it within 5 minutes.',
    icon: (
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
        <rect x="2" y="4" width="20" height="16" rx="2"/>
        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
      </svg>
    ),
    timing: 'Within 5 min',
  },
  {
    step: '02',
    title: 'AI Protocol Build',
    description: 'Your AI-generated TRT protocol blueprint will be ready before your physician visit. You\'ll receive a link to review it in your portal.',
    icon: (
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 2a10 10 0 1 0 10 10"/>
        <path d="M12 6v6l4 2"/>
        <path d="M22 2 12 12"/>
      </svg>
    ),
    timing: 'Within 24 hrs',
  },
  {
    step: '03',
    title: 'Physician Video Call',
    description: 'A licensed physician will review your protocol and labs. You\'ll receive a scheduling link to book your required video consultation.',
    icon: (
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
        <path d="M15 10l4.553-2.069A1 1 0 0 1 21 8.82v6.36a1 1 0 0 1-1.447.89L15 14"/>
        <rect x="1" y="6" width="15" height="12" rx="2"/>
      </svg>
    ),
    timing: '24–72 hrs',
  },
  {
    step: '04',
    title: 'Medication Shipped',
    description: 'Once your physician approves your protocol, your medication is compounded and shipped directly to your door — discreet packaging.',
    icon: (
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
        <path d="M5 12H3l3-9h12l3 9h-2"/>
        <path d="M3 12h18v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7z"/>
        <path d="M10 12v4"/>
        <path d="M14 12v4"/>
      </svg>
    ),
    timing: '5–7 business days',
  },
];

const NEXT_STEPS_BLUEPRINT = [
  {
    step: '01',
    title: 'Check Your Inbox',
    description: 'Your confirmation email with intake instructions has been sent. Complete the intake form to unlock your AI protocol build.',
    icon: (
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
        <rect x="2" y="4" width="20" height="16" rx="2"/>
        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
      </svg>
    ),
    timing: 'Within 5 min',
  },
  {
    step: '02',
    title: 'AI Blueprint Generated',
    description: 'Your personalized hormone optimization blueprint is built by our AI and reviewed by a physician — no live call required for this tier.',
    icon: (
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2"/>
        <path d="M9 9h6M9 12h6M9 15h4"/>
      </svg>
    ),
    timing: 'Within 24 hrs',
  },
  {
    step: '03',
    title: 'Review in Your Portal',
    description: 'Access your blueprint, lab recommendations, and upgrade options directly in your patient portal. Your $49 is credited if you upgrade.',
    icon: (
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
        <rect x="2" y="3" width="20" height="14" rx="2"/>
        <path d="M8 21h8M12 17v4"/>
      </svg>
    ),
    timing: '24–48 hrs',
  },
  {
    step: '04',
    title: 'Upgrade When Ready',
    description: 'If your blueprint points to TRT or peptides, upgrade directly from your portal. Your $49 assessment fee is credited toward your first order.',
    icon: (
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 19V5M5 12l7-7 7 7"/>
      </svg>
    ),
    timing: 'Anytime',
  },
];

// ─── Redirect Countdown ───────────────────────────────────────────────────────
const REDIRECT_SECONDS = 15;

function OrderConfirmationContent() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const [mounted, setMounted] = useState(false);
  const [orderNumber] = useState(() => `PHR-${Math.floor(100000 + Math.random() * 900000)}`);
  const [countdown, setCountdown] = useState(REDIRECT_SECONDS);
  const [redirectCancelled, setRedirectCancelled] = useState(false);
  const [sessionVerified, setSessionVerified] = useState<boolean | null>(null);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const emailSentRef = useRef(false);

  const planKey = (searchParams?.get('plan') === 'blueprint' ? 'blueprint' : 'trt') as keyof typeof PLANS;
  const plan = PLANS[planKey];
  const nextSteps = planKey === 'blueprint' ? NEXT_STEPS_BLUEPRINT : NEXT_STEPS_TRT;

  useEffect(() => {
    setMounted(true);
    // Verify user session — gate webhooks and emails behind real auth
    async function verifySession() {
      try {
        const supabase = createClient();
        const { data: { user } } = await supabase.auth.getUser();
        setSessionVerified(!!user);
        if (!user) {
          // Redirect unauthenticated visitors to login
          router.replace('/login');
        }
      } catch {
        setSessionVerified(false);
        router.replace('/login');
      }
    }
    verifySession();
  }, [router]);

  // Send portal access email once after checkout — only for authenticated users
  useEffect(() => {
    if (!mounted || emailSentRef.current || !sessionVerified) return;
    emailSentRef.current = true;

    async function sendPortalEmail() {
      try {
        await fetch('/api/email/send-portal-access', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            planName: plan.name,
            planBadge: plan.badge,
            orderNumber,
          }),
        });
      } catch (err) {
        // Non-blocking — confirmation page still works without email
        console.error('[Order Confirmation] Portal email send failed:', err);
      }
    }

    async function fireCheckoutWebhook() {
      try {
        const supabase = createClient();
        const { data: { user } } = await supabase.auth.getUser();
        await triggerCheckoutComplete({
          userId: user?.id,
          email: user?.email,
          orderNumber,
          planKey,
          planName: plan.name,
          planPrice: plan.price,
        });
      } catch (err) {
        console.error('[n8n] Checkout complete webhook failed:', err);
      }
    }

    sendPortalEmail();
    fireCheckoutWebhook();
  }, [mounted, plan.name, plan.badge, plan.price, planKey, orderNumber]);

  // Seed service purchase in Supabase so intake forms appear in portal
  useEffect(() => {
    async function seedServicePurchase() {
      try {
        const supabase = createClient();
        const { data: { user } } = await supabase.auth.getUser();
        if (!user) return;

        // Map plan key to service type
        const serviceTypeMap: Record<string, string> = {
          trt: 'TRT',
          blueprint: 'TRT', // Blueprint also unlocks TRT screening
        };
        const serviceType = serviceTypeMap[planKey];
        if (!serviceType) return;

        await supabase
          .from('patient_service_purchases')
          .upsert(
            {
              user_id: user.id,
              service_type: serviceType,
              order_id: orderNumber,
            },
            { onConflict: 'user_id,service_type' }
          );
      } catch (err) {
        // Non-blocking — portal still works without this
        console.error('[Order Confirmation] Service purchase seed failed:', err);
      }
    }
    seedServicePurchase();
  }, [planKey, orderNumber]);

  // Auto-redirect countdown
  useEffect(() => {
    if (!mounted || redirectCancelled) return;
    intervalRef.current = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          clearInterval(intervalRef.current!);
          router.push('/patient-portal');
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
    };
  }, [mounted, redirectCancelled, router]);

  const cancelRedirect = () => {
    setRedirectCancelled(true);
    if (intervalRef.current) clearInterval(intervalRef.current);
  };

  // Progress bar width
  const progressPct = ((REDIRECT_SECONDS - countdown) / REDIRECT_SECONDS) * 100;

  return (
    <div style={{ background: '#F7F4F0', minHeight: '100vh' }}>
      <Header />

      {/* ── Hero Banner ─────────────────────────────────────────────────────── */}
      <div
        className="pt-32 pb-14 px-4"
        style={{
          background: 'linear-gradient(135deg, #0D0D0D 0%, #1C1C1C 60%, #141414 100%)',
          position: 'relative',
          overflow: 'hidden',
        }}
      >
        {/* Ambient glows */}
        <div
          style={{
            position: 'absolute',
            inset: 0,
            backgroundImage:
              'radial-gradient(circle at 15% 50%, rgba(201,168,76,0.07) 0%, transparent 55%), radial-gradient(circle at 85% 20%, rgba(90,138,94,0.09) 0%, transparent 50%)',
            pointerEvents: 'none',
          }}
        />

        <div className="max-w-3xl mx-auto text-center relative">
          {/* Success checkmark */}
          <div
            className="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6"
            style={{
              background: 'rgba(90,138,94,0.15)',
              border: '1px solid rgba(90,138,94,0.3)',
            }}
          >
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#5A8A5E" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </div>

          {/* Plan badge */}
          <div
            className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full mb-5"
            style={{
              background: 'rgba(201,168,76,0.12)',
              border: '1px solid rgba(201,168,76,0.25)',
            }}
          >
            <span
              style={{
                color: '#C9A84C',
                fontFamily: 'JetBrains Mono, monospace',
                fontSize: '11px',
                fontWeight: 500,
                letterSpacing: '0.08em',
                textTransform: 'uppercase',
              }}
            >
              Payment Confirmed · {plan.badge}
            </span>
          </div>

          <h1
            style={{
              fontFamily: 'Cormorant Garamond, serif',
              fontSize: 'clamp(34px, 6vw, 56px)',
              fontWeight: 700,
              color: '#FFFFFF',
              lineHeight: 1.1,
              marginBottom: '16px',
            }}
          >
            Your Protocol Begins Now
          </h1>
          <p
            style={{
              color: 'rgba(255,255,255,0.6)',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontSize: '17px',
              lineHeight: 1.6,
              maxWidth: '460px',
              margin: '0 auto',
            }}
          >
            Welcome to ProtocolHRT. Your personalized optimization journey starts today.
          </p>

          {/* Order number */}
          {mounted && (
            <div
              className="inline-flex items-center gap-2 mt-6 px-5 py-2.5 rounded-xl"
              style={{
                background: 'rgba(255,255,255,0.05)',
                border: '1px solid rgba(255,255,255,0.1)',
              }}
            >
              <span style={{ color: 'rgba(255,255,255,0.4)', fontFamily: 'DM Sans, sans-serif', fontSize: '13px' }}>Order</span>
              <span style={{ color: '#FFFFFF', fontFamily: 'JetBrains Mono, monospace', fontSize: '13px', fontWeight: 500 }}>{orderNumber}</span>
            </div>
          )}
        </div>
      </div>

      {/* ── Auto-Redirect Banner ─────────────────────────────────────────────── */}
      {mounted && !redirectCancelled && (
        <div
          style={{
            background: 'rgba(90,138,94,0.08)',
            borderBottom: '1px solid rgba(90,138,94,0.18)',
            position: 'relative',
            overflow: 'hidden',
          }}
        >
          {/* Progress bar */}
          <div
            style={{
              position: 'absolute',
              bottom: 0,
              left: 0,
              height: '2px',
              width: `${progressPct}%`,
              background: 'linear-gradient(90deg, #5A8A5E, #C9A84C)',
              transition: 'width 1s linear',
            }}
          />
          <div className="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-4 flex-wrap">
            <div className="flex items-center gap-3">
              <div
                className="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0"
                style={{ background: 'rgba(90,138,94,0.15)', border: '1px solid rgba(90,138,94,0.25)' }}
              >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#5A8A5E" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <polyline points="12 6 12 12 16 14"/>
                </svg>
              </div>
              <p style={{ fontFamily: 'DM Sans, sans-serif', fontSize: '13px', color: '#38312C' }}>
                Redirecting to your{' '}
                <span style={{ fontWeight: 600, color: '#5A8A5E' }}>Patient Portal</span>
                {' '}in{' '}
                <span
                  style={{
                    fontFamily: 'JetBrains Mono, monospace',
                    fontWeight: 600,
                    color: '#38312C',
                    display: 'inline-block',
                    minWidth: '18px',
                    textAlign: 'center',
                  }}
                >
                  {countdown}s
                </span>
              </p>
            </div>
            <div className="flex items-center gap-3">
              <Link
                href="/patient-portal"
                className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-semibold transition-all"
                style={{
                  background: '#5A8A5E',
                  color: '#fff',
                  fontFamily: 'DM Sans, sans-serif',
                  textDecoration: 'none',
                }}
              >
                Go Now →
              </Link>
              <button
                onClick={cancelRedirect}
                style={{
                  background: 'none',
                  border: 'none',
                  cursor: 'pointer',
                  color: '#8A7F78',
                  fontFamily: 'DM Sans, sans-serif',
                  fontSize: '12px',
                  padding: '4px 8px',
                  borderRadius: '6px',
                }}
              >
                Stay on page
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── Main Content ─────────────────────────────────────────────────────── */}
      <div className="max-w-5xl mx-auto px-4 py-12 pb-20">
        <div className="grid grid-cols-1 lg:grid-cols-5 gap-8 items-start">

          {/* ── Left Column ─────────────────────────────────────────────────── */}
          <div className="lg:col-span-3 space-y-6">

            {/* Next Steps */}
            <div
              className="rounded-2xl p-6 sm:p-8"
              style={{ background: '#FFFFFF', boxShadow: '0 4px 24px rgba(56,49,44,0.07)', border: '1px solid rgba(56,49,44,0.07)' }}
            >
              <div className="flex items-center gap-3 mb-7">
                <div
                  className="w-8 h-8 rounded-lg flex items-center justify-center"
                  style={{ background: 'rgba(90,138,94,0.1)' }}
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5A8A5E" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M9 11l3 3L22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                  </svg>
                </div>
                <h2
                  style={{
                    fontFamily: 'Cormorant Garamond, serif',
                    fontSize: '22px',
                    fontWeight: 700,
                    color: '#38312C',
                  }}
                >
                  What Happens Next
                </h2>
              </div>

              <div className="space-y-0">
                {nextSteps?.map((item, i) => (
                  <div
                    key={item.step}
                    className="flex gap-5"
                    style={{
                      paddingBottom: i < nextSteps.length - 1 ? '24px' : '0',
                      paddingTop: i > 0 ? '24px' : '0',
                      borderBottom: i < nextSteps.length - 1 ? '1px solid rgba(56,49,44,0.07)' : 'none',
                    }}
                  >
                    {/* Icon + connector */}
                    <div className="flex flex-col items-center flex-shrink-0" style={{ width: '40px' }}>
                      <div
                        className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                        style={{ background: 'rgba(56,49,44,0.05)', color: '#5A8A5E' }}
                      >
                        {item.icon}
                      </div>
                      {i < nextSteps.length - 1 && (
                        <div
                          className="w-px flex-1 mt-3"
                          style={{ background: 'rgba(56,49,44,0.08)', minHeight: '20px' }}
                        />
                      )}
                    </div>

                    {/* Content */}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-3 mb-1.5">
                        <h3
                          style={{
                            fontFamily: 'DM Sans, sans-serif',
                            fontSize: '15px',
                            fontWeight: 600,
                            color: '#38312C',
                          }}
                        >
                          {item.title}
                        </h3>
                        <span
                          className="flex-shrink-0 px-2.5 py-1 rounded-full text-xs"
                          style={{
                            background: 'rgba(90,138,94,0.08)',
                            color: '#5A8A5E',
                            fontFamily: 'DM Sans, sans-serif',
                            fontWeight: 500,
                            whiteSpace: 'nowrap',
                          }}
                        >
                          {item.timing}
                        </span>
                      </div>
                      <p
                        style={{
                          fontFamily: 'DM Sans, sans-serif',
                          fontSize: '14px',
                          color: '#6A6A6A',
                          lineHeight: 1.6,
                        }}
                      >
                        {item.description}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Subscription Details */}
            <div
              className="rounded-2xl p-6 sm:p-8"
              style={{ background: '#FFFFFF', boxShadow: '0 4px 24px rgba(56,49,44,0.07)', border: '1px solid rgba(56,49,44,0.07)' }}
            >
              <div className="flex items-center gap-3 mb-6">
                <div
                  className="w-8 h-8 rounded-lg flex items-center justify-center"
                  style={{ background: 'rgba(201,168,76,0.1)' }}
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                  </svg>
                </div>
                <h2
                  style={{
                    fontFamily: 'Cormorant Garamond, serif',
                    fontSize: '22px',
                    fontWeight: 700,
                    color: '#38312C',
                  }}
                >
                  Subscription Details
                </h2>
              </div>

              <div className="space-y-0">
                {plan.subscriptionDetails.map((row, i) => (
                  <div
                    key={i}
                    className="flex items-start justify-between gap-4 py-3.5"
                    style={{
                      borderBottom: i < plan.subscriptionDetails.length - 1 ? '1px solid rgba(56,49,44,0.07)' : 'none',
                    }}
                  >
                    <span
                      style={{
                        fontFamily: 'DM Sans, sans-serif',
                        fontSize: '13px',
                        color: '#8A7F78',
                        fontWeight: 500,
                        flexShrink: 0,
                        minWidth: '130px',
                      }}
                    >
                      {row.label}
                    </span>
                    <span
                      style={{
                        fontFamily: 'DM Sans, sans-serif',
                        fontSize: '13px',
                        color: '#38312C',
                        fontWeight: 500,
                        textAlign: 'right',
                      }}
                    >
                      {row.value}
                    </span>
                  </div>
                ))}
              </div>

              {/* Price lock notice for TRT */}
              {planKey === 'trt' && (
                <div
                  className="mt-5 rounded-xl px-4 py-3.5 flex items-start gap-3"
                  style={{
                    background: 'rgba(201,168,76,0.07)',
                    border: '1px solid rgba(201,168,76,0.2)',
                  }}
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0, marginTop: '2px' }}>
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                  </svg>
                  <p
                    style={{
                      fontFamily: 'DM Sans, sans-serif',
                      fontSize: '13px',
                      color: '#7A6A3A',
                      lineHeight: 1.55,
                    }}
                  >
                    <strong style={{ color: '#C9A84C', fontWeight: 600 }}>Price locked for life.</strong>{' '}
                    Your $149/mo rate is a launch offer. Patients who enrolled now lock in this price permanently — even as rates increase for new members.
                  </p>
                </div>
              )}

              {/* Credit notice for blueprint */}
              {planKey === 'blueprint' && (
                <div
                  className="mt-5 rounded-xl px-4 py-3.5 flex items-start gap-3"
                  style={{
                    background: 'rgba(90,138,94,0.06)',
                    border: '1px solid rgba(90,138,94,0.18)',
                  }}
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5A8A5E" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0, marginTop: '2px' }}>
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                  </svg>
                  <p
                    style={{
                      fontFamily: 'DM Sans, sans-serif',
                      fontSize: '13px',
                      color: '#3A5A3E',
                      lineHeight: 1.55,
                    }}
                  >
                    <strong style={{ color: '#5A8A5E', fontWeight: 600 }}>$49 assessment credit.</strong>{' '}
                    If you decide to upgrade to TRT or a peptide protocol, your $49 assessment fee is credited toward your first order — you're not paying twice.
                  </p>
                </div>
              )}
            </div>

            {/* Patient Portal CTA */}
            <div
              className="rounded-2xl p-6 sm:p-8"
              style={{
                background: 'linear-gradient(135deg, #0D0D0D 0%, #1C1C1C 100%)',
                border: '1px solid rgba(201,168,76,0.2)',
                position: 'relative',
                overflow: 'hidden',
              }}
            >
              <div
                style={{
                  position: 'absolute',
                  top: 0,
                  right: 0,
                  width: '220px',
                  height: '220px',
                  background: 'radial-gradient(circle, rgba(201,168,76,0.08) 0%, transparent 70%)',
                  pointerEvents: 'none',
                }}
              />
              <div className="relative">
                <div
                  className="inline-flex items-center gap-2 px-3 py-1 rounded-full mb-4"
                  style={{
                    background: 'rgba(201,168,76,0.12)',
                    border: '1px solid rgba(201,168,76,0.2)',
                  }}
                >
                  <span
                    style={{
                      color: '#C9A84C',
                      fontFamily: 'JetBrains Mono, monospace',
                      fontSize: '10px',
                      fontWeight: 500,
                      letterSpacing: '0.08em',
                      textTransform: 'uppercase',
                    }}
                  >
                    Patient Portal
                  </span>
                </div>
                <h3
                  className="mb-2"
                  style={{
                    fontFamily: 'Cormorant Garamond, serif',
                    fontSize: '24px',
                    fontWeight: 700,
                    color: '#FFFFFF',
                  }}
                >
                  Access Your Dashboard
                </h3>
                <p
                  className="mb-6"
                  style={{
                    fontFamily: 'DM Sans, sans-serif',
                    fontSize: '14px',
                    color: 'rgba(255,255,255,0.55)',
                    lineHeight: 1.6,
                  }}
                >
                  Track your protocol progress, view lab results, message your care team, and manage your subscription — all in one place.
                </p>
                <div className="flex flex-wrap gap-3">
                  <Link
                    href="/patient-portal"
                    className="btn-primary"
                    style={{
                      background: '#C9A84C',
                      color: '#0D0D0D',
                      height: '44px',
                      fontSize: '14px',
                      padding: '0 24px',
                      fontWeight: 600,
                    }}
                  >
                    Go to Patient Dashboard →
                  </Link>
                  <Link
                    href="/homepage"
                    className="btn-secondary"
                    style={{
                      height: '44px',
                      fontSize: '14px',
                      padding: '0 20px',
                      borderColor: 'rgba(255,255,255,0.15)',
                      color: 'rgba(255,255,255,0.7)',
                    }}
                  >
                    Return to Home
                  </Link>
                </div>
              </div>
            </div>
          </div>

          {/* ── Right Column ─────────────────────────────────────────────────── */}
          <div className="lg:col-span-2 space-y-5">

            {/* Order Summary */}
            <div
              className="rounded-2xl p-6"
              style={{ background: '#FFFFFF', boxShadow: '0 4px 24px rgba(56,49,44,0.07)', border: '1px solid rgba(56,49,44,0.07)' }}
            >
              <h3
                className="mb-5"
                style={{
                  fontFamily: 'Cormorant Garamond, serif',
                  fontSize: '20px',
                  fontWeight: 700,
                  color: '#38312C',
                }}
              >
                Order Summary
              </h3>

              {/* Product row */}
              <div
                className="rounded-xl p-4 mb-4"
                style={{ background: '#FAFAF8', border: '1px solid rgba(56,49,44,0.07)' }}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1">
                    <p
                      style={{
                        fontFamily: 'DM Sans, sans-serif',
                        fontSize: '14px',
                        fontWeight: 600,
                        color: '#38312C',
                        marginBottom: '3px',
                      }}
                    >
                      {plan.name}
                    </p>
                    <p
                      style={{
                        fontFamily: 'DM Sans, sans-serif',
                        fontSize: '12px',
                        color: '#8A7F78',
                        lineHeight: 1.5,
                      }}
                    >
                      {plan.tagline}
                    </p>
                  </div>
                  <div className="text-right flex-shrink-0">
                    <p
                      style={{
                        fontFamily: 'DM Sans, sans-serif',
                        fontSize: '16px',
                        fontWeight: 700,
                        color: '#38312C',
                      }}
                    >
                      ${plan.price}
                      <span style={{ fontSize: '12px', fontWeight: 400, color: '#8A7F78' }}>
                        /{plan.billingCycle}
                      </span>
                    </p>
                    <p
                      style={{
                        fontFamily: 'DM Sans, sans-serif',
                        fontSize: '11px',
                        color: '#8A7F78',
                        textDecoration: 'line-through',
                      }}
                    >
                      ${plan.originalPrice}/{plan.billingCycle}
                    </p>
                  </div>
                </div>
              </div>

              {/* Savings */}
              <div
                className="flex items-center justify-between rounded-lg px-4 py-2.5 mb-4"
                style={{ background: 'rgba(90,138,94,0.07)', border: '1px solid rgba(90,138,94,0.15)' }}
              >
                <span style={{ fontFamily: 'DM Sans, sans-serif', fontSize: '13px', color: '#5A8A5E', fontWeight: 500 }}>
                  {plan.savingsLabel}
                </span>
                <span style={{ fontFamily: 'DM Sans, sans-serif', fontSize: '13px', color: '#5A8A5E', fontWeight: 700 }}>
                  −${plan.savings}/{plan.billingCycle}
                </span>
              </div>

              <div style={{ borderTop: '1px solid rgba(56,49,44,0.08)', marginBottom: '14px' }} />

              {/* Total */}
              <div className="flex items-center justify-between">
                <span style={{ fontFamily: 'DM Sans, sans-serif', fontSize: '14px', color: '#5C5248', fontWeight: 500 }}>
                  {planKey === 'blueprint' ? 'Total Charged' : 'Monthly Total'}
                </span>
                <span style={{ fontFamily: 'Cormorant Garamond, serif', fontSize: '22px', fontWeight: 700, color: '#38312C' }}>
                  ${plan.price}
                  <span style={{ fontSize: '14px', fontWeight: 400, color: '#8A7F78', fontFamily: 'DM Sans, sans-serif' }}>
                    /{plan.billingCycle}
                  </span>
                </span>
              </div>
            </div>

            {/* Protocol Includes */}
            <div
              className="rounded-2xl p-6"
              style={{ background: '#FFFFFF', boxShadow: '0 4px 24px rgba(56,49,44,0.07)', border: '1px solid rgba(56,49,44,0.07)' }}
            >
              <h3
                className="mb-4"
                style={{
                  fontFamily: 'Cormorant Garamond, serif',
                  fontSize: '18px',
                  fontWeight: 700,
                  color: '#38312C',
                }}
              >
                Your Plan Includes
              </h3>
              <ul className="space-y-3">
                {plan.includes.map((item, i) => (
                  <li key={i} className="flex items-start gap-3">
                    <div
                      className="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                      style={{ background: 'rgba(90,138,94,0.1)' }}
                    >
                      <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="#5A8A5E" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="10 3 5 8.5 2 5.5"/>
                      </svg>
                    </div>
                    <span
                      style={{
                        fontFamily: 'DM Sans, sans-serif',
                        fontSize: '13px',
                        color: '#5C5248',
                        lineHeight: 1.5,
                      }}
                    >
                      {item}
                    </span>
                  </li>
                ))}
              </ul>
            </div>

            {/* Support */}
            <div
              className="rounded-2xl p-5"
              style={{
                background: 'rgba(90,138,94,0.05)',
                border: '1px solid rgba(90,138,94,0.15)',
              }}
            >
              <div className="flex items-start gap-3">
                <div
                  className="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                  style={{ background: 'rgba(90,138,94,0.12)' }}
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5A8A5E" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                  </svg>
                </div>
                <div>
                  <p
                    style={{
                      fontFamily: 'DM Sans, sans-serif',
                      fontSize: '13px',
                      fontWeight: 600,
                      color: '#38312C',
                      marginBottom: '3px',
                    }}
                  >
                    Questions? We're here.
                  </p>
                  <p
                    style={{
                      fontFamily: 'DM Sans, sans-serif',
                      fontSize: '12px',
                      color: '#6A6A6A',
                      lineHeight: 1.5,
                    }}
                  >
                    Your care team responds within 24 hours. Reach us at{' '}
                    <a
                      href="mailto:support@protocolhrt.com"
                      style={{ color: '#5A8A5E', textDecoration: 'underline' }}
                    >
                      support@protocolhrt.com
                    </a>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <Footer />
    </div>
  );
}

export default function OrderConfirmationPage() {
  return (
    <Suspense fallback={
      <div style={{ background: '#F7F4F0', minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <div style={{ fontFamily: 'DM Sans, sans-serif', color: '#8A7F78', fontSize: '15px' }}>Loading your confirmation…</div>
      </div>
    }>
      <OrderConfirmationContent />
    </Suspense>
  );
}
