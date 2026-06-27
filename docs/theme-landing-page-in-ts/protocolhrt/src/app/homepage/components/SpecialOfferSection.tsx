'use client';
import React, { useEffect, useRef, useState } from 'react';
import { openIntakeModal } from '@/lib/openIntakeModal';

// ─── Debug helpers ────────────────────────────────────────────────────────────
const DEBUG = false;

function dbLog(label: string, value?: unknown) {
  if (!DEBUG) return;
  const style = 'background:#1a1a1a;color:#C9A84C;padding:2px 6px;border-radius:3px;font-weight:700;';
  if (value !== undefined) {
    console.log(`%c[SpecialOfferSection] ${label}`, style, value);
  } else {
    console.log(`%c[SpecialOfferSection] ${label}`, style);
  }
}
// ─────────────────────────────────────────────────────────────────────────────

export default function SpecialOfferSection() {
  const sectionRef = useRef<HTMLElement>(null);
  const [debugVisible, setDebugVisible] = useState(false);
  const [renderChecks, setRenderChecks] = useState<Record<string, boolean>>({});

  // ── Peptide waitlist state ─────────────────────────────────────────────────
  const [waitlistEmail, setWaitlistEmail] = useState('');
  const [waitlistSubmitted, setWaitlistSubmitted] = useState(false);
  const [waitlistLoading, setWaitlistLoading] = useState(false);

  const handleWaitlistSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!waitlistEmail.trim()) return;
    setWaitlistLoading(true);
    // Simulate submission — replace with actual API call when ready
    await new Promise((resolve) => setTimeout(resolve, 800));
    setWaitlistLoading(false);
    setWaitlistSubmitted(true);
    dbLog('Peptide waitlist submitted', { email: waitlistEmail });
  };

  useEffect(() => {
    dbLog('Component mounted — starting render verification');

    const checks: Array<{ key: string; selector: string; expectedText: string }> = [
      { key: '$49 price',        selector: '[data-debug="price-49"]',       expectedText: '$49' },
      { key: '$149 price',       selector: '[data-debug="price-149"]',      expectedText: '$149' },
      { key: 'Peptide price',    selector: '[data-debug="price-peptide"]',  expectedText: '[Price on formulary upload]' },
      { key: '$49 CTA button',   selector: '[data-debug="cta-49"]',         expectedText: '$49' },
      { key: '$149 CTA button',  selector: '[data-debug="cta-149"]',        expectedText: '$149' },
      { key: 'Peptide CTA btn',  selector: '[data-debug="cta-peptide"]',    expectedText: '$49' },
    ];

    const results: Record<string, boolean> = {};

    checks.forEach(({ key, selector, expectedText }) => {
      const el = document.querySelector(selector);
      const found = !!el;
      const textMatch = found && (el?.textContent ?? '').includes(expectedText);
      const visible = found && el instanceof HTMLElement
        ? el.offsetParent !== null && getComputedStyle(el).visibility !== 'hidden' && getComputedStyle(el).opacity !== '0'
        : false;

      results[key] = found && textMatch && visible;

      dbLog(`${key}`, {
        elementFound: found,
        textMatch,
        visible,
        actualText: el?.textContent?.trim().slice(0, 60) ?? '—',
        pass: results[key],
      });
    });

    setRenderChecks(results);

    const allPass = Object.values(results).every(Boolean);
    if (allPass) {
      dbLog('✅ ALL price/CTA checks PASSED — cards are rendered and visible');
    } else {
      const failed = Object.entries(results).filter(([, v]) => !v).map(([k]) => k);
      dbLog('❌ SOME checks FAILED', { failed });
    }
  }, []);

  useEffect(() => {
    const elements = sectionRef?.current?.querySelectorAll('.reveal-fade, .reveal-scale');
    if (!elements?.length) return;

    dbLog(`IntersectionObserver watching ${elements.length} reveal elements`);

    const fallbackTimer = setTimeout(() => {
      elements?.forEach((el) => el?.classList?.add('is-visible'));
      dbLog('Fallback timer fired — forced all reveal elements to is-visible');

      setTimeout(() => {
        const visibilityReport: Record<string, string> = {};
        document.querySelectorAll('[data-debug]').forEach((el) => {
          const key = el.getAttribute('data-debug') ?? 'unknown';
          const isVisible = el instanceof HTMLElement
            ? el.offsetParent !== null && getComputedStyle(el).opacity !== '0'
            : false;
          visibilityReport[key] = isVisible ? '✅ visible' : '❌ hidden';
        });
        dbLog('Post-reveal visibility snapshot', visibilityReport);
        setDebugVisible(true);
      }, 100);
    }, 300);

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            dbLog('IntersectionObserver triggered is-visible', entry.target.className.split(' ')[0]);
          }
        });
      },
      { threshold: 0, rootMargin: '0px 0px -50px 0px' }
    );
    elements?.forEach((el) => observer?.observe(el));
    return () => {
      clearTimeout(fallbackTimer);
      observer?.disconnect();
    };
  }, []);

  const DebugOverlay = () => {
    if (!DEBUG) return null;
    const entries = Object.entries(renderChecks);
    if (!entries.length) return null;
    return (
      <div
        style={{
          position: 'fixed',
          bottom: '16px',
          right: '16px',
          zIndex: 9999,
          background: 'rgba(13,13,13,0.95)',
          border: '1px solid rgba(201,168,76,0.4)',
          borderRadius: '12px',
          padding: '12px 16px',
          minWidth: '260px',
          boxShadow: '0 8px 32px rgba(0,0,0,0.6)',
          fontFamily: 'JetBrains Mono, monospace',
          fontSize: '11px',
        }}>
        <p style={{ color: '#C9A84C', fontWeight: 700, marginBottom: '8px', letterSpacing: '0.1em' }}>
          🔍 SPECIAL OFFER DEBUG
        </p>
        {entries.map(([label, pass]) => (
          <div key={label} style={{ display: 'flex', justifyContent: 'space-between', gap: '12px', marginBottom: '4px' }}>
            <span style={{ color: 'rgba(255,255,255,0.55)' }}>{label}</span>
            <span style={{ color: pass ? '#A9FFCB' : '#FF6B6B', fontWeight: 700 }}>{pass ? '✅' : '❌'}</span>
          </div>
        ))}
        <button
          onClick={() => setDebugVisible(false)}
          style={{
            marginTop: '10px', width: '100%', background: 'rgba(255,255,255,0.05)',
            border: '1px solid rgba(255,255,255,0.1)', borderRadius: '6px',
            color: 'rgba(255,255,255,0.4)', cursor: 'pointer', padding: '4px 0', fontSize: '10px',
          }}>
          dismiss
        </button>
      </div>
    );
  };

  return (
    <>
      <section
        ref={sectionRef}
        id="special-offer"
        className="py-20 px-4 sm:px-6 lg:px-8"
        style={{ background: '#0D0D0D', borderTop: '1px solid rgba(255,255,255,0.05)' }}>
      <div className="max-w-6xl mx-auto">

        {/* Section label */}
        <div className="reveal-fade text-center mb-12">
          <span
            className="inline-flex items-center gap-2 px-4 py-2 rounded-full"
            style={{
              background: 'rgba(201,168,76,0.08)',
              border: '1px solid rgba(201,168,76,0.2)',
              color: '#C9A84C',
              fontSize: '11px',
              fontFamily: 'JetBrains Mono, monospace',
              letterSpacing: '0.15em',
              textTransform: 'uppercase',
            }}>
            Choose Your Starting Point
          </span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

          {/* ── Card 1: $49 Protocol Blueprint Assessment ── */}
          <div
            className="reveal-fade relative overflow-hidden rounded-3xl flex flex-col"
            style={{
              background: 'linear-gradient(135deg, #141414 0%, #1A1A1A 100%)',
              border: '1px solid rgba(119,157,124,0.25)',
              padding: 'clamp(28px, 4vw, 44px)',
            }}>

            <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: '2px', background: 'linear-gradient(90deg, transparent 0%, #779D7C 40%, #779D7C 60%, transparent 100%)' }} />

            <div className="flex items-center gap-2 mb-5">
              <span
                style={{
                  background: 'rgba(119,157,124,0.12)',
                  border: '1px solid rgba(119,157,124,0.3)',
                  color: '#A9FFCB',
                  fontSize: '10px',
                  fontFamily: 'JetBrains Mono, monospace',
                  letterSpacing: '0.12em',
                  textTransform: 'uppercase',
                  padding: '4px 10px',
                  borderRadius: '20px',
                }}>
                FRONT DOOR · ALL PATIENTS
              </span>
            </div>

            <h2
              style={{
                color: '#FFFFFF',
                fontFamily: 'Cormorant Garamond, serif',
                fontSize: 'clamp(26px, 3vw, 36px)',
                fontWeight: 700,
                lineHeight: 1.1,
                letterSpacing: '-0.01em',
                marginBottom: '8px',
              }}>
              Protocol Blueprint Assessment
            </h2>

            <p style={{ color: 'rgba(255,255,255,0.45)', fontSize: '13px', fontFamily: 'JetBrains Mono, monospace', letterSpacing: '0.06em', marginBottom: '20px' }}>
              AI concierge intake · evidence-based protocol · physician review
            </p>

            <div className="flex items-baseline gap-3 mb-6">
              <span
                data-debug="price-49"
                style={{ color: '#FFFFFF', fontFamily: 'Cormorant Garamond, serif', fontSize: '48px', fontWeight: 700, lineHeight: 1 }}>
                $49
              </span>
              <span style={{ color: 'rgba(255,255,255,0.4)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px' }}>one-time</span>
            </div>

            <div
              style={{
                background: 'rgba(119,157,124,0.08)',
                border: '1px solid rgba(119,157,124,0.25)',
                borderRadius: '14px',
                padding: '14px 16px',
                marginBottom: '20px',
              }}>
              <p style={{ color: '#A9FFCB', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', fontWeight: 600, marginBottom: '4px' }}>
                $49 credited in full at checkout
              </p>
              <p style={{ color: 'rgba(255,255,255,0.45)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', lineHeight: '1.5' }}>
                Apply your $49 toward any peptide or peptide combination. Your protocol is effectively free the moment you add a peptide to your order.
              </p>
            </div>

            <ul className="space-y-2.5 mb-6 flex-1">
              {[
                'Full AI concierge intake and personalized protocol build',
                'Licensed physician review — async (peptides) or live video call (hormones)',
                'Diet, sleep, exercise, and supplement stack delivered',
                'Blood work recommendation if clinically indicated',
                'No compounds included — protocol and physician review only',
              ]?.map((item) => (
                <li key={item} className="flex items-start gap-2.5">
                  <svg className="flex-shrink-0 mt-0.5" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#779D7C" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                  <span style={{ color: 'rgba(255,255,255,0.6)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', lineHeight: '1.5' }}>{item}</span>
                </li>
              ))}
            </ul>

            <p style={{ color: 'rgba(255,255,255,0.25)', fontSize: '11px', fontFamily: 'JetBrains Mono, monospace', letterSpacing: '0.05em', marginBottom: '20px', lineHeight: '1.6' }}>
              Peptide-only protocols → async physician review, no live call required<br />
              Hormone protocols → live physician video call required
            </p>

            <button
              data-debug="cta-49"
              className="w-full btn-green"
              onClick={() => openIntakeModal()}
              style={{ height: '52px', fontSize: '14px', letterSpacing: '0.04em' }}>
              Get My Protocol — $49
            </button>

            <button
              onClick={() => document.querySelector('#process')?.scrollIntoView({ behavior: 'smooth' })}
              style={{
                background: 'none', border: 'none', cursor: 'pointer',
                color: 'rgba(255,255,255,0.35)', fontFamily: 'DM Sans, system-ui, sans-serif',
                fontSize: '13px', marginTop: '12px', textAlign: 'center', width: '100%',
                textDecoration: 'underline', textUnderlineOffset: '3px',
              }}>
              See what's included →
            </button>
          </div>

          {/* ── Card 2: TRT $149/mo Hero Offer ── */}
          <div
            className="reveal-fade relative overflow-hidden rounded-3xl flex flex-col"
            style={{
              background: 'linear-gradient(135deg, #1C1810 0%, #201A0E 100%)',
              border: '2px solid rgba(201,168,76,0.45)',
              padding: 'clamp(28px, 4vw, 44px)',
            }}>

            {/* Top accent line */}
            <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: '2px', background: 'linear-gradient(90deg, transparent 0%, #C9A84C 40%, #C9A84C 60%, transparent 100%)' }} />

            {/* Background glow */}
            <div style={{ position: 'absolute', top: '-40px', right: '-40px', width: '220px', height: '220px', borderRadius: '50%', background: 'radial-gradient(circle, rgba(201,168,76,0.07) 0%, transparent 70%)', pointerEvents: 'none' }} />

            {/* ── MOST POPULAR badge (absolute top-right) ── */}
            <div
              style={{
                position: 'absolute',
                top: '18px',
                right: '18px',
                background: 'linear-gradient(135deg, #C9A84C 0%, #B8943A 100%)',
                color: '#0D0D0D',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontWeight: 800,
                fontSize: '10px',
                letterSpacing: '0.12em',
                textTransform: 'uppercase',
                padding: '5px 12px',
                borderRadius: '20px',
                boxShadow: '0 2px 12px rgba(201,168,76,0.5)',
              }}>
              ⭐ Most Popular
            </div>

            {/* LTO urgency banner */}
            <div
              style={{
                background: 'linear-gradient(90deg, rgba(201,168,76,0.15) 0%, rgba(201,168,76,0.05) 100%)',
                border: '1px solid rgba(201,168,76,0.4)',
                borderRadius: '10px',
                padding: '10px 14px',
                marginBottom: '16px',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
              }}>
              <span style={{ fontSize: '16px' }}>⏳</span>
              <p style={{ color: '#C9A84C', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', fontWeight: 700, lineHeight: 1.4, margin: 0 }}>
                Launch pricing closes without notice — patients who enroll now are grandfathered at $149/mo for life.
              </p>
            </div>

            {/* Headline */}
            <h2
              style={{
                color: '#FFFFFF',
                fontFamily: 'Cormorant Garamond, serif',
                fontSize: 'clamp(26px, 3vw, 36px)',
                fontWeight: 700,
                lineHeight: 1.1,
                letterSpacing: '-0.01em',
                marginBottom: '8px',
              }}>
              Testosterone Replacement Therapy
            </h2>

            <p style={{ color: 'rgba(255,255,255,0.45)', fontSize: '13px', fontFamily: 'JetBrains Mono, monospace', letterSpacing: '0.06em', marginBottom: '20px' }}>
              All-in monthly program · medication included · live physician video call
            </p>

            {/* Price */}
            <div className="flex items-baseline gap-3 mb-5">
              <span
                data-debug="price-149"
                style={{ color: '#C9A84C', fontFamily: 'Cormorant Garamond, serif', fontSize: '52px', fontWeight: 700, lineHeight: 1 }}>
                $149
              </span>
              <div>
                <span style={{ color: 'rgba(255,255,255,0.5)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '16px' }}>/month</span>
                <p style={{ color: 'rgba(255,255,255,0.3)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', marginTop: '2px' }}>All-in · medication included · limited time pricing</p>
              </div>
            </div>

            {/* Urgency block */}
            <div
              style={{
                background: 'rgba(201,168,76,0.08)',
                border: '1px solid rgba(201,168,76,0.3)',
                borderRadius: '14px',
                padding: '14px 16px',
                marginBottom: '20px',
              }}>
              <p style={{ color: '#C9A84C', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', fontWeight: 700, marginBottom: '5px' }}>
                Lock in $149/mo now
              </p>
              <p style={{ color: 'rgba(255,255,255,0.5)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', lineHeight: '1.6' }}>
                This is a special launch offer. Patients who enroll now are grandfathered at $149/mo for the life of their subscription. Price will increase — lock in before it does.
              </p>
            </div>

            {/* Feature list */}
            <ul className="space-y-2.5 mb-6 flex-1">
              {[
                'Testosterone medication included — all-in monthly pricing',
                'Live physician video call required before prescribing',
                'Full AI protocol build delivered before your physician visit',
                'Blood work kit included if clinically indicated',
                'Monthly refill — delivered to your door',
                'Stack up to 3 peptides at checkout — async physician approvals, no additional call needed',
              ]?.map((item) => (
                <li key={item} className="flex items-start gap-2.5">
                  <svg className="flex-shrink-0 mt-0.5" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                  <span style={{ color: 'rgba(255,255,255,0.6)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', lineHeight: '1.5' }}>{item}</span>
                </li>
              ))}
            </ul>

            <p style={{ color: 'rgba(255,255,255,0.25)', fontSize: '11px', fontFamily: 'JetBrains Mono, monospace', letterSpacing: '0.05em', marginBottom: '20px' }}>
              Route: AI intake → Checkout → Live physician video call → Lab kit if indicated → Rx + ship
            </p>

            <button
              data-debug="cta-149"
              className="w-full"
              onClick={() => openIntakeModal()}
              style={{
                height: '52px',
                background: 'linear-gradient(135deg, #C9A84C 0%, #B8943A 100%)',
                color: '#0D0D0D',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontWeight: 700,
                fontSize: '14px',
                letterSpacing: '0.04em',
                border: 'none',
                borderRadius: '12px',
                cursor: 'pointer',
                boxShadow: '0 4px 20px rgba(201,168,76,0.35)',
                transition: 'all 0.2s ease',
              }}
              onMouseEnter={(e) => { e.currentTarget.style.boxShadow = '0 6px 28px rgba(201,168,76,0.5)'; e.currentTarget.style.transform = 'translateY(-1px)'; }}
              onMouseLeave={(e) => { e.currentTarget.style.boxShadow = '0 4px 20px rgba(201,168,76,0.35)'; e.currentTarget.style.transform = 'translateY(0)'; }}>
              Start TRT — $149/mo (Lock In Now)
            </button>

            <p style={{ color: 'rgba(201,168,76,0.5)', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', letterSpacing: '0.1em', textAlign: 'center', marginTop: '8px' }}>
              Limited time · price increases at launch close
            </p>

            <div className="flex flex-wrap items-center justify-center gap-4 mt-5 pt-5" style={{ borderTop: '1px solid rgba(255,255,255,0.06)' }}>
              {[
                'Licensed physicians in all 50 states',
                'Medication shipped to your door',
                'Grandfathered rate — cancel anytime',
              ]?.map((badge) => (
                <div key={badge} className="flex items-center gap-1.5">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                  <span style={{ color: 'rgba(255,255,255,0.35)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px' }}>{badge}</span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* ── Peptide-Only Entry Offer ── */}
        <div
          className="reveal-fade mt-6 relative overflow-hidden rounded-3xl"
          style={{
            background: 'linear-gradient(135deg, #0F1510 0%, #111A12 100%)',
            border: '1px solid rgba(119,157,124,0.2)',
            padding: 'clamp(24px, 3vw, 36px)',
          }}>

          <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: '2px', background: 'linear-gradient(90deg, transparent 0%, #779D7C 30%, #779D7C 70%, transparent 100%)' }} />

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div className="lg:col-span-2">
              <div className="flex items-center gap-2 mb-4 flex-wrap">
                <span
                  style={{
                    background: 'rgba(119,157,124,0.1)',
                    border: '1px solid rgba(119,157,124,0.25)',
                    color: '#A9FFCB',
                    fontSize: '10px',
                    fontFamily: 'JetBrains Mono, monospace',
                    letterSpacing: '0.12em',
                    textTransform: 'uppercase',
                    padding: '4px 10px',
                    borderRadius: '20px',
                  }}>
                  PEPTIDE PERFORMANCE · ASYNC APPROVAL · NO LIVE CALL
                </span>
                <span
                  style={{
                    background: 'rgba(255,200,100,0.1)',
                    border: '1px solid rgba(255,200,100,0.25)',
                    color: 'rgba(255,200,100,0.8)',
                    fontSize: '10px',
                    fontFamily: 'JetBrains Mono, monospace',
                    letterSpacing: '0.12em',
                    textTransform: 'uppercase',
                    padding: '4px 10px',
                    borderRadius: '20px',
                  }}>
                  🧪 FORMULARY PENDING — JOIN WAITLIST
                </span>
              </div>

              <h3
                style={{
                  color: '#FFFFFF',
                  fontFamily: 'Cormorant Garamond, serif',
                  fontSize: 'clamp(22px, 2.5vw, 30px)',
                  fontWeight: 700,
                  lineHeight: 1.15,
                  marginBottom: '6px',
                }}>
                Peptide-Only Protocol Stack
              </h3>
              <p style={{ color: 'rgba(255,255,255,0.4)', fontSize: '13px', fontFamily: 'JetBrains Mono, monospace', letterSpacing: '0.05em', marginBottom: '16px' }}>
                Select 1–3 peptides · async physician approval · no live visit required
              </p>

              <div className="flex gap-3 mb-5 flex-wrap">
                {['1 Peptide', '2 Peptides', '3 Peptides']?.map((label) => (
                  <div
                    key={label}
                    style={{
                      background: 'rgba(119,157,124,0.06)',
                      border: '1px solid rgba(119,157,124,0.15)',
                      borderRadius: '10px',
                      padding: '10px 14px',
                      textAlign: 'center',
                      minWidth: '90px',
                    }}>
                    <p style={{ color: 'rgba(255,255,255,0.25)', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', letterSpacing: '0.08em', marginBottom: '4px' }}>{label}</p>
                    <p
                      data-debug="price-peptide"
                      style={{ color: 'rgba(119,157,124,0.5)', fontFamily: 'Cormorant Garamond, serif', fontSize: '18px', fontWeight: 700 }}>
                      [Price on formulary upload] /mo
                    </p>
                    <p style={{ color: 'rgba(255,255,255,0.2)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', marginTop: '2px' }}>Async physician review included</p>
                  </div>
                ))}
              </div>

              <ul className="space-y-2 flex-wrap">
                {[
                  'AI intake builds your personalized peptide protocol',
                  'Async physician chart review and approval — no video call needed',
                  '$49 assessment credit applied to your first order',
                  'Monthly refill, delivered to your door',
                  'Stack up to 3 peptide protocols',
                ]?.map((item) => (
                  <li key={item} className="flex items-start gap-2">
                    <svg className="flex-shrink-0 mt-0.5" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#779D7C" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span style={{ color: 'rgba(255,255,255,0.5)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', lineHeight: '1.5' }}>{item}</span>
                  </li>
                ))}
              </ul>
            </div>

            {/* ── Peptide Waitlist Email Capture ── */}
            <div className="flex flex-col gap-3">
              {waitlistSubmitted ? (
                <div
                  style={{
                    background: 'rgba(119,157,124,0.1)',
                    border: '1px solid rgba(119,157,124,0.35)',
                    borderRadius: '14px',
                    padding: '20px 16px',
                    textAlign: 'center',
                  }}>
                  <p style={{ fontSize: '24px', marginBottom: '8px' }}>✅</p>
                  <p style={{ color: '#A9FFCB', fontFamily: 'DM Sans, system-ui, sans-serif', fontWeight: 700, fontSize: '14px', marginBottom: '6px' }}>
                    You're on the list
                  </p>
                  <p style={{ color: 'rgba(255,255,255,0.4)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', lineHeight: '1.5' }}>
                    We'll notify you the moment peptide pricing goes live. You'll be first.
                  </p>
                </div>
              ) : (
                <div
                  style={{
                    background: 'rgba(119,157,124,0.06)',
                    border: '1px solid rgba(119,157,124,0.2)',
                    borderRadius: '14px',
                    padding: '18px 16px',
                  }}>
                  <p style={{ color: '#A9FFCB', fontFamily: 'DM Sans, system-ui, sans-serif', fontWeight: 700, fontSize: '13px', marginBottom: '4px' }}>
                    Get notified when pricing drops
                  </p>
                  <p style={{ color: 'rgba(255,255,255,0.35)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', lineHeight: '1.5', marginBottom: '14px' }}>
                    Peptide formulary is being finalized. Join the waitlist — we'll email you the moment it's live.
                  </p>
                  <form onSubmit={handleWaitlistSubmit} className="flex flex-col gap-2">
                    <input
                      type="email"
                      value={waitlistEmail}
                      onChange={(e) => setWaitlistEmail(e.target.value)}
                      placeholder="your@email.com"
                      required
                      style={{
                        background: 'rgba(255,255,255,0.05)',
                        border: '1px solid rgba(119,157,124,0.3)',
                        borderRadius: '10px',
                        padding: '10px 14px',
                        color: '#FFFFFF',
                        fontFamily: 'DM Sans, system-ui, sans-serif',
                        fontSize: '13px',
                        outline: 'none',
                        width: '100%',
                      }}
                      onFocus={(e) => { e.currentTarget.style.borderColor = 'rgba(119,157,124,0.6)'; }}
                      onBlur={(e) => { e.currentTarget.style.borderColor = 'rgba(119,157,124,0.3)'; }}
                    />
                    <button
                      type="submit"
                      disabled={waitlistLoading}
                      style={{
                        height: '44px',
                        background: waitlistLoading ? 'rgba(119,157,124,0.2)' : 'rgba(119,157,124,0.15)',
                        color: '#A9FFCB',
                        fontFamily: 'DM Sans, system-ui, sans-serif',
                        fontWeight: 700,
                        fontSize: '13px',
                        letterSpacing: '0.04em',
                        border: '1px solid rgba(119,157,124,0.4)',
                        borderRadius: '10px',
                        cursor: waitlistLoading ? 'not-allowed' : 'pointer',
                        transition: 'all 0.2s ease',
                        opacity: waitlistLoading ? 0.7 : 1,
                      }}
                      onMouseEnter={(e) => { if (!waitlistLoading) e.currentTarget.style.background = 'rgba(119,157,124,0.25)'; }}
                      onMouseLeave={(e) => { if (!waitlistLoading) e.currentTarget.style.background = 'rgba(119,157,124,0.15)'; }}>
                      {waitlistLoading ? 'Joining...' : 'Notify Me When Live →'}
                    </button>
                  </form>
                </div>
              )}

              <button
                data-debug="cta-peptide"
                className="w-full"
                onClick={() => openIntakeModal()}
                style={{
                  height: '48px',
                  background: 'transparent',
                  color: 'rgba(255,255,255,0.4)',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontWeight: 600,
                  fontSize: '13px',
                  letterSpacing: '0.04em',
                  border: '1px solid rgba(255,255,255,0.1)',
                  borderRadius: '12px',
                  cursor: 'pointer',
                  transition: 'all 0.2s ease',
                }}
                onMouseEnter={(e) => { e.currentTarget.style.borderColor = 'rgba(119,157,124,0.4)'; e.currentTarget.style.color = '#A9FFCB'; }}
                onMouseLeave={(e) => { e.currentTarget.style.borderColor = 'rgba(255,255,255,0.1)'; e.currentTarget.style.color = 'rgba(255,255,255,0.4)'; }}>
                Start $49 Assessment Now →
              </button>
              <p style={{ color: 'rgba(255,255,255,0.2)', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', letterSpacing: '0.08em', textAlign: 'center' }}>
                PEPTIDE PRICING AVAILABLE ON FORMULARY UPLOAD
              </p>
            </div>
          </div>
        </div>

      </div>
    </section>
    </>
  );
}
