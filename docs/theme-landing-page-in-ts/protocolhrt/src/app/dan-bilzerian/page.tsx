'use client';
import React, { useEffect, useRef, useState } from 'react';
import Link from 'next/link';

export default function DanBilzerianPage() {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  return (
    <div style={{ background: '#08080a', color: '#f0e9d6', fontFamily: "'DM Sans', sans-serif", fontWeight: 300, lineHeight: 1.55, overflowX: 'hidden', minHeight: '100vh' }}>
      {/* Grain overlay */}
      <div
        style={{
          position: 'fixed',
          inset: 0,
          backgroundImage: `url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 0.93 0 0 0 0 0.85 0 0 0 0 0.65 0 0 0 0.05 0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>")`,
          pointerEvents: 'none',
          opacity: 0.5,
          zIndex: 100,
          mixBlendMode: 'overlay',
        }}
      />

      {/* NAV */}
      <nav style={{ position: 'absolute', top: 0, left: 0, right: 0, zIndex: 10, padding: '28px 56px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Link href="/homepage" style={{ fontFamily: "'Syne', sans-serif", fontWeight: 700, fontSize: 16, letterSpacing: '0.18em', textTransform: 'uppercase', color: '#f0e9d6', textDecoration: 'none' }}>
          PROTOCOL<span style={{ color: '#c9a565' }}>HRT</span>
        </Link>
        <div style={{ fontFamily: "'Space Mono', monospace", fontSize: 11, letterSpacing: '0.15em', textTransform: 'uppercase', color: '#a89e85', display: 'flex', alignItems: 'center', gap: 10 }}>
          <PulseDot />
          Founding Member Enrollment — Open
        </div>
      </nav>

      {/* HERO */}
      <section style={{ position: 'relative', minHeight: '100vh', display: 'grid', gridTemplateColumns: '1.1fr 1fr', alignItems: 'stretch', overflow: 'hidden' }} className="hero-grid">
        {/* Left: Image frame */}
        <div style={{ position: 'relative', background: 'linear-gradient(180deg, #1a1610 0%, #08070a 100%)', overflow: 'hidden', borderRight: '1px solid rgba(201,165,101,0.18)' }}>
          <div style={{
            position: 'absolute', inset: 0,
            background: 'radial-gradient(ellipse at 50% 35%, rgba(201,165,101,0.32) 0%, transparent 55%), radial-gradient(ellipse at 30% 80%, rgba(201,165,101,0.06) 0%, transparent 50%), linear-gradient(135deg, #1f1a10 0%, #08070a 100%)',
          }} />
          {/* Placeholder label */}
          <div style={{
            position: 'absolute', top: '50%', left: '50%', transform: 'translate(-50%, -50%)',
            fontFamily: "'Space Mono', monospace", fontSize: 10, letterSpacing: '0.2em', textTransform: 'uppercase',
            color: '#5a5347', textAlign: 'center', lineHeight: 1.8,
            border: '1px dashed #5a5347', padding: '18px 24px', borderRadius: 2, zIndex: 2,
          }}>
            [ HERO PHOTO ]<br />
            DAN BILZERIAN — APPROVED ASSET<br />
            DROP-IN: 1600×2000 / DARK CINEMATIC
          </div>
          {/* Credit bar */}
          <div style={{
            position: 'absolute', bottom: 32, left: 32, right: 32, display: 'flex',
            justifyContent: 'space-between', alignItems: 'center', zIndex: 3,
            borderTop: '1px solid rgba(201,165,101,0.18)', paddingTop: 16,
          }}>
            <div style={{ fontFamily: "'Cormorant Garamond', serif", fontStyle: 'italic', fontSize: 22, color: '#f0e9d6' }}>Dan Bilzerian</div>
            <div style={{ fontFamily: "'Space Mono', monospace", fontSize: 10, letterSpacing: '0.2em', textTransform: 'uppercase', color: '#c9a565' }}>America's Most-Followed Lifestyle Icon</div>
          </div>
        </div>

        {/* Right: Copy */}
        <div style={{
          padding: '140px 72px 80px',
          display: 'flex', flexDirection: 'column', justifyContent: 'center',
          background: 'linear-gradient(180deg, #08080a 0%, #0f0f10 100%)',
          position: 'relative',
        }}>
          <div style={{ fontFamily: "'Space Mono', monospace", fontSize: 11, letterSpacing: '0.25em', textTransform: 'uppercase', color: '#c9a565', marginBottom: 36, display: 'flex', alignItems: 'center', gap: 12 }}>
            <span style={{ width: 32, height: 1, background: '#c9a565', display: 'inline-block' }} />
            Founding Member · Limited Enrollment
          </div>

          <h1 style={{ fontFamily: "'Cormorant Garamond', serif", fontWeight: 400, fontSize: 'clamp(48px, 5.2vw, 84px)', lineHeight: 0.96, letterSpacing: '-0.02em', marginBottom: 32 }}>
            The TRT program{' '}
            <em style={{ fontStyle: 'italic', color: '#c9a565', fontWeight: 300 }}>Dan put his name on.</em>
          </h1>

          <p style={{ fontFamily: "'DM Sans', sans-serif", fontSize: 17, color: '#a89e85', maxWidth: 460, marginBottom: 48, lineHeight: 1.65 }}>
            A complete men's testosterone replacement program — physician-led, AI-built around your biology, and delivered to your door. No clinic visits. No upsell games. No cookie-cutter protocols.
          </p>

          {/* Offer card */}
          <div style={{
            border: '1px solid rgba(201,165,101,0.18)',
            background: 'linear-gradient(180deg, rgba(201,165,101,0.06) 0%, rgba(201,165,101,0.01) 100%)',
            padding: '28px 32px', marginBottom: 32, position: 'relative', maxWidth: 480,
          }}>
            <div style={{ position: 'absolute', top: 0, left: 0, width: 3, height: '100%', background: '#c9a565' }} />
            <div style={{ fontFamily: "'Space Mono', monospace", fontSize: 10, letterSpacing: '0.25em', textTransform: 'uppercase', color: '#c9a565', marginBottom: 14 }}>
              Founding Member Rate · Locked For Life
            </div>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 12, marginBottom: 8 }}>
              <span style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 24, color: '#a89e85', fontWeight: 300 }}>$</span>
              <span style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 72, fontWeight: 400, color: '#f0e9d6', lineHeight: 1, letterSpacing: '-0.03em' }}>149</span>
              <span style={{ fontFamily: "'DM Sans', sans-serif", fontSize: 14, color: '#a89e85', marginLeft: 4 }}>/ month, all-in</span>
            </div>
            <div style={{ fontSize: 13, color: '#a89e85', lineHeight: 1.6 }}>
              Medication included. Live physician video call. Lab kit if indicated. Up to 3 peptide add-ons available at checkout.
            </div>
          </div>

          {/* CTA */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 24, flexWrap: 'wrap' }}>
            <CtaButton href="#start" label="Start My Protocol" />
            <div style={{ fontFamily: "'Space Mono', monospace", fontSize: 11, letterSpacing: '0.15em', textTransform: 'uppercase', color: '#5a5347' }}>~ 6 minute intake</div>
          </div>
        </div>
      </section>

      {/* ENDORSEMENT */}
      <section style={{ background: '#0f0f10', padding: '140px 56px', borderTop: '1px solid rgba(201,165,101,0.18)', borderBottom: '1px solid rgba(201,165,101,0.18)', position: 'relative' }}>
        {/* Big quote mark */}
        <div style={{
          position: 'absolute', top: 40, left: 56,
          fontFamily: "'Cormorant Garamond', serif", fontSize: 280, lineHeight: 1,
          color: '#c9a565', opacity: 0.14, fontStyle: 'italic', pointerEvents: 'none', userSelect: 'none',
        }}>"</div>
        <div style={{ maxWidth: 1100, margin: '0 auto', position: 'relative', zIndex: 2 }}>
          <div style={{ fontFamily: "'Space Mono', monospace", fontSize: 11, letterSpacing: '0.25em', textTransform: 'uppercase', color: '#c9a565', marginBottom: 40, display: 'flex', alignItems: 'center', gap: 12 }}>
            <span style={{ width: 40, height: 1, background: '#c9a565', display: 'inline-block' }} />
            A Word From Dan
          </div>

          <blockquote style={{ fontFamily: "'Cormorant Garamond', serif", fontStyle: 'italic', fontWeight: 300, fontSize: 'clamp(28px, 3.6vw, 52px)', lineHeight: 1.25, letterSpacing: '-0.01em', color: '#f0e9d6', marginBottom: 56 }}>
            I get pitched every supplement, clinic, and miracle protocol on the planet. Most of them are{' '}
            <span style={{ color: '#c9a565', fontStyle: 'italic' }}>a joke</span>{' '}
            — cookie-cutter scripts, no real medicine, just a credit card swipe and a vial in the mail. ProtocolHRT is the first one that{' '}
            <span style={{ color: '#c9a565', fontStyle: 'italic' }}>does it right.</span>{' '}
            Real physicians. Protocols built around your bloodwork, not a template. This is the only program I'll put my name on.
          </blockquote>

          <div style={{ display: 'flex', alignItems: 'center', gap: 24, paddingTop: 32, borderTop: '1px solid rgba(201,165,101,0.18)' }}>
            <div style={{
              width: 64, height: 64, borderRadius: '50%', flexShrink: 0,
              background: 'radial-gradient(ellipse at 50% 30%, rgba(201,165,101,0.45) 0%, transparent 70%), linear-gradient(135deg, #2a2218 0%, #08070a 100%)',
              border: '1px solid rgba(201,165,101,0.18)',
            }} />
            <div>
              <div style={{ fontFamily: "'Syne', sans-serif", fontWeight: 700, fontSize: 18, letterSpacing: '0.05em', textTransform: 'uppercase', color: '#f0e9d6', marginBottom: 4 }}>Dan Bilzerian</div>
              <div style={{ fontFamily: "'Space Mono', monospace", fontSize: 11, letterSpacing: '0.2em', textTransform: 'uppercase', color: '#c9a565' }}>America's Most-Followed Lifestyle Icon</div>
            </div>
          </div>
        </div>
      </section>

      {/* PRODUCT */}
      <section style={{
        padding: '140px 56px',
        background: 'radial-gradient(ellipse at 50% 0%, rgba(201,165,101,0.08) 0%, transparent 60%), linear-gradient(180deg, #08080a 0%, #0f0f10 100%)',
        borderBottom: '1px solid rgba(201,165,101,0.18)',
        overflow: 'hidden',
      }}>
        <div style={{ maxWidth: 1200, margin: '0 auto', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 80, alignItems: 'center' }} className="product-grid">
          {/* Vial visual */}
          <div style={{ position: 'relative', display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: 540 }}>
            <div style={{ position: 'absolute', width: 460, height: 460, borderRadius: '50%', background: 'radial-gradient(circle, rgba(201,165,101,0.18) 0%, transparent 65%)', pointerEvents: 'none' }} />
            <VialSVG />
          </div>

          {/* Copy */}
          <div>
            <div style={{ fontFamily: "'Space Mono', monospace", fontSize: 11, letterSpacing: '0.25em', textTransform: 'uppercase', color: '#c9a565', marginBottom: 24 }}>/ The Compound</div>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 'clamp(40px, 4.4vw, 64px)', fontWeight: 400, lineHeight: 1.02, letterSpacing: '-0.02em', marginBottom: 28 }}>
              Pharmaceutical-grade.{' '}
              <em style={{ fontStyle: 'italic', color: '#c9a565' }}>Compounded for you.</em>
            </h2>
            <p style={{ fontSize: 17, color: '#a89e85', lineHeight: 1.7, marginBottom: 20, maxWidth: 480 }}>
              Every vial is compounded by our licensed pharmacy partner, prescribed by a physician who reviewed your bloodwork, and shipped directly to your door. No clinic markup. No middleman.
            </p>
            <p style={{ fontSize: 17, color: '#a89e85', lineHeight: 1.7, marginBottom: 20, maxWidth: 480 }}>
              The medication is included in your $149/mo program. The price you see is the price you pay — for life.
            </p>

            <div style={{ marginTop: 40, borderTop: '1px solid rgba(201,165,101,0.18)', paddingTop: 32, display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 24 }}>
              {[
                { label: 'Compound', value: 'Testosterone', accent: 'Cypionate' },
                { label: 'Concentration', value: '200 mg', accent: '/ ml' },
                { label: 'Format', value: '10 ml', accent: 'multi-dose vial' },
                { label: 'Source', value: '503A', accent: 'Compounded' },
              ].map((spec) => (
                <div key={spec.label}>
                  <div style={{ fontFamily: "'Space Mono', monospace", fontSize: 10, letterSpacing: '0.2em', textTransform: 'uppercase', color: '#5a5347', marginBottom: 8 }}>{spec.label}</div>
                  <div style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#f0e9d6', fontWeight: 400 }}>
                    {spec.value} <em style={{ color: '#c9a565', fontStyle: 'italic' }}>{spec.accent}</em>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* INCLUDED */}
      <section style={{ padding: '120px 56px', background: '#08080a' }}>
        <div style={{ maxWidth: 1200, margin: '0 auto' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 80, marginBottom: 80, alignItems: 'end' }} className="section-head-grid">
            <div>
              <div style={{ fontFamily: "'Space Mono', monospace", fontSize: 11, letterSpacing: '0.25em', textTransform: 'uppercase', color: '#c9a565', marginBottom: 24 }}>/ 02 — What's Included</div>
              <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 'clamp(40px, 4.2vw, 64px)', fontWeight: 400, lineHeight: 1, letterSpacing: '-0.02em' }}>
                All-in.<br /><em style={{ fontStyle: 'italic', color: '#c9a565' }}>No surprises.</em>
              </h2>
            </div>
            <p style={{ fontSize: 16, color: '#a89e85', lineHeight: 1.7, maxWidth: 540 }}>
              $149 covers everything — the medication, the physician, the labs, the AI-built protocol, and the monthly delivery. No pharmacy markup. No add-on fees. The price you see is the price you pay, and it's locked in for the life of your subscription.
            </p>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 1, background: 'rgba(240,233,214,0.08)', border: '1px solid rgba(240,233,214,0.08)' }} className="included-grid">
            {[
              { num: '/ 01', title: 'Testosterone, included', body: 'Pharmaceutical-grade T delivered monthly. No separate pharmacy bill. No surprise charges.' },
              { num: '/ 02', title: 'Live physician call', body: 'A licensed doctor — not a chatbot — reviews your case and signs off before anything ships.' },
              { num: '/ 03', title: 'AI-built protocol', body: 'Your bloodwork, your goals, your biology. Diet, sleep, training, supplementation — built around you.' },
              { num: '/ 04', title: 'Bloodwork kit', body: 'At-home lab kit included if clinically indicated. Mail-in. Results back in days.' },
            ].map((cell) => (
              <IncludedCell key={cell.num} num={cell.num} title={cell.title} body={cell.body} />
            ))}
          </div>
        </div>
      </section>

      {/* LTO */}
      <section id="start" style={{
        background: 'linear-gradient(180deg, #08080a 0%, #16161a 100%)',
        padding: '100px 56px',
        textAlign: 'center',
        borderTop: '1px solid rgba(201,165,101,0.18)',
        position: 'relative',
        overflow: 'hidden',
      }}>
        <div style={{ position: 'absolute', inset: 0, background: 'radial-gradient(ellipse at 50% 0%, rgba(201,165,101,0.12) 0%, transparent 60%)', pointerEvents: 'none' }} />
        <div style={{ maxWidth: 820, margin: '0 auto', position: 'relative' }}>
          <div style={{
            display: 'inline-flex', alignItems: 'center', gap: 10,
            fontFamily: "'Space Mono', monospace", fontSize: 11, letterSpacing: '0.25em', textTransform: 'uppercase',
            color: '#c9a565', border: '1px solid #c9a565', padding: '8px 16px', marginBottom: 32,
          }}>
            <PulseDot />
            Limited Time · Founding Member Rate
          </div>
          <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 'clamp(36px, 4.5vw, 64px)', fontWeight: 400, lineHeight: 1.1, letterSpacing: '-0.02em', marginBottom: 24 }}>
            $149/mo. <em style={{ fontStyle: 'italic', color: '#c9a565' }}>Locked for life.</em>
          </h2>
          <p style={{ fontSize: 17, color: '#a89e85', lineHeight: 1.65, maxWidth: 620, margin: '0 auto 48px' }}>
            The $149 rate is a launch offer. Patients who enroll now lock in this price for the life of their subscription. We haven't announced when this window closes — but it will. If TRT is on your radar, now is when the math makes the most sense.
          </p>
          <CtaButton href="#" label="Click Here To Start" large />
        </div>
      </section>

      {/* FOOTER */}
      <footer style={{
        padding: '56px',
        background: '#0f0f10',
        borderTop: '1px solid rgba(201,165,101,0.18)',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        fontFamily: "'Space Mono', monospace",
        fontSize: 11,
        letterSpacing: '0.15em',
        textTransform: 'uppercase',
        color: '#5a5347',
        flexWrap: 'wrap',
        gap: 16,
      }}>
        <div>© 2026 PROTOCOLHRT</div>
        <div style={{ maxWidth: 520, lineHeight: 1.7, textTransform: 'none', letterSpacing: '0.05em', fontSize: 10 }}>
          Telemedicine services provided through licensed physicians. Treatment available only after physician evaluation. Individual results vary. Not all patients qualify. Read full clinical and pricing terms at protocolhrt.com.
        </div>
      </footer>

      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Syne:wght@500;600;700;800&family=DM+Sans:wght@300;400;500&family=Space+Mono:wght@400;700&display=swap');
        @media (max-width: 960px) {
          .hero-grid { grid-template-columns: 1fr !important; }
          .product-grid { grid-template-columns: 1fr !important; gap: 40px !important; }
          .section-head-grid { grid-template-columns: 1fr !important; gap: 32px !important; }
          .included-grid { grid-template-columns: repeat(2, 1fr) !important; }
          nav { padding: 20px 24px !important; }
        }
        @media (max-width: 600px) {
          .included-grid { grid-template-columns: 1fr !important; }
        }
      `}</style>
    </div>
  );
}

function PulseDot() {
  return (
    <span style={{
      display: 'inline-block',
      width: 6, height: 6, borderRadius: '50%',
      background: '#c9a565',
      boxShadow: '0 0 8px #c9a565',
      animation: 'phrt-pulse 2s infinite',
      flexShrink: 0,
    }}>
      <style>{`@keyframes phrt-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }`}</style>
    </span>
  );
}

function CtaButton({ href, label, large }: { href: string; label: string; large?: boolean }) {
  const [hovered, setHovered] = React.useState(false);
  return (
    <a
      href={href}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      style={{
        display: 'inline-flex', alignItems: 'center', gap: 14,
        background: hovered ? '#e6c787' : '#c9a565',
        color: '#08080a',
        padding: large ? '22px 44px' : '20px 36px',
        fontFamily: "'Syne', sans-serif", fontWeight: 700,
        fontSize: large ? 14 : 13,
        letterSpacing: '0.15em', textTransform: 'uppercase',
        textDecoration: 'none', border: 'none', cursor: 'pointer',
        transform: hovered ? 'translateY(-2px)' : 'none',
        boxShadow: hovered ? '0 8px 32px rgba(201,165,101,0.18)' : '0 0 0 0 rgba(201,165,101,0.18)',
        transition: 'all 0.3s ease',
      }}
    >
      {label}
      <span style={{ position: 'relative', display: 'inline-block', width: hovered ? 32 : 24, height: 1, background: '#08080a', transition: 'width 0.3s ease' }}>
        <span style={{ position: 'absolute', right: 0, top: -3, width: 7, height: 7, borderTop: '1px solid #08080a', borderRight: '1px solid #08080a', transform: 'rotate(45deg)', display: 'block' }} />
      </span>
    </a>
  );
}

function IncludedCell({ num, title, body }: { num: string; title: string; body: string }) {
  const [hovered, setHovered] = React.useState(false);
  return (
    <div
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      style={{ background: hovered ? '#0f0f10' : '#08080a', padding: '40px 32px', transition: 'background 0.4s ease' }}
    >
      <div style={{ fontFamily: "'Space Mono', monospace", fontSize: 11, color: '#c9a565', marginBottom: 24, letterSpacing: '0.15em' }}>{num}</div>
      <div style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 24, lineHeight: 1.15, marginBottom: 14, color: '#f0e9d6' }}>{title}</div>
      <div style={{ fontSize: 14, color: '#a89e85', lineHeight: 1.6 }}>{body}</div>
    </div>
  );
}

function VialSVG() {
  return (
    <svg className="vial-svg" width="280" height="520" viewBox="0 0 280 520" xmlns="http://www.w3.org/2000/svg" style={{ position: 'relative', zIndex: 2, filter: 'drop-shadow(0 30px 60px rgba(0,0,0,0.6))' }}>
      <defs>
        <linearGradient id="db-goldCap" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#7a5e2e"/>
          <stop offset="15%" stopColor="#c9a565"/>
          <stop offset="35%" stopColor="#f0d896"/>
          <stop offset="50%" stopColor="#e6c787"/>
          <stop offset="65%" stopColor="#c9a565"/>
          <stop offset="85%" stopColor="#a07f42"/>
          <stop offset="100%" stopColor="#5a4422"/>
        </linearGradient>
        <linearGradient id="db-goldCapTop" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#a07f42"/>
          <stop offset="50%" stopColor="#f0d896"/>
          <stop offset="100%" stopColor="#7a5e2e"/>
        </linearGradient>
        <linearGradient id="db-crimp" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#5a4422"/>
          <stop offset="50%" stopColor="#c9a565"/>
          <stop offset="100%" stopColor="#3a2d18"/>
        </linearGradient>
        <linearGradient id="db-glass" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#0a0907"/>
          <stop offset="20%" stopColor="#1a140c"/>
          <stop offset="50%" stopColor="#2a1f10"/>
          <stop offset="80%" stopColor="#1a140c"/>
          <stop offset="100%" stopColor="#080604"/>
        </linearGradient>
        <linearGradient id="db-liquid" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#1a0e04"/>
          <stop offset="50%" stopColor="#3d2510"/>
          <stop offset="100%" stopColor="#0e0703"/>
        </linearGradient>
        <linearGradient id="db-label" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#0a0a08"/>
          <stop offset="50%" stopColor="#16140e"/>
          <stop offset="100%" stopColor="#08070a"/>
        </linearGradient>
        <linearGradient id="db-glassHighlight" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stopColor="#ffffff" stopOpacity="0.0"/>
          <stop offset="20%" stopColor="#ffffff" stopOpacity="0.18"/>
          <stop offset="80%" stopColor="#ffffff" stopOpacity="0.04"/>
          <stop offset="100%" stopColor="#ffffff" stopOpacity="0"/>
        </linearGradient>
      </defs>

      <ellipse cx="140" cy="118" rx="62" ry="4" fill="#000" opacity="0.4"/>
      <ellipse cx="140" cy="42" rx="56" ry="8" fill="url(#db-goldCapTop)"/>
      <rect x="84" y="42" width="112" height="68" fill="url(#db-goldCap)"/>
      <ellipse cx="140" cy="110" rx="56" ry="8" fill="#7a5e2e"/>
      <rect x="84" y="50" width="112" height="1" fill="#5a4422" opacity="0.6"/>
      <rect x="84" y="56" width="112" height="1" fill="#5a4422" opacity="0.4"/>
      <rect x="84" y="98" width="112" height="1" fill="#5a4422" opacity="0.6"/>
      <rect x="84" y="104" width="112" height="1" fill="#5a4422" opacity="0.4"/>
      <rect x="92" y="44" width="6" height="62" fill="#f0d896" opacity="0.6"/>
      <rect x="180" y="44" width="10" height="62" fill="#3a2d18" opacity="0.5"/>
      <text x="140" y="46" textAnchor="middle" fontFamily="Syne, sans-serif" fontWeight="700" fontSize="9" fill="#5a4422" letterSpacing="2">PHRT</text>

      <ellipse cx="140" cy="118" rx="52" ry="6" fill="url(#db-crimp)"/>
      <rect x="88" y="118" width="104" height="14" fill="url(#db-crimp)"/>
      <ellipse cx="140" cy="132" rx="52" ry="6" fill="#3a2d18"/>

      <path d="M 92 132 Q 88 142 90 156 L 100 156 Q 100 144 102 134 Z" fill="url(#db-glass)"/>
      <path d="M 188 132 Q 192 142 190 156 L 180 156 Q 180 144 178 134 Z" fill="url(#db-glass)"/>
      <ellipse cx="140" cy="156" rx="50" ry="5" fill="#0a0907"/>

      <rect x="60" y="156" width="160" height="296" rx="4" fill="url(#db-glass)"/>
      <ellipse cx="140" cy="452" rx="80" ry="10" fill="#080604"/>
      <rect x="60" y="442" width="160" height="14" fill="url(#db-glass)"/>
      <ellipse cx="140" cy="456" rx="80" ry="8" fill="#040302"/>

      <rect x="68" y="180" width="144" height="266" fill="url(#db-liquid)" opacity="0.9"/>
      <ellipse cx="140" cy="180" rx="72" ry="3" fill="#3d2510" opacity="0.6"/>

      <rect x="56" y="220" width="168" height="180" fill="url(#db-label)" stroke="#c9a565" strokeWidth="0.5" opacity="0.96"/>
      <rect x="56" y="220" width="168" height="2" fill="#c9a565"/>
      <rect x="56" y="398" width="168" height="2" fill="#c9a565"/>

      <text x="140" y="246" textAnchor="middle" fontFamily="Space Mono, monospace" fontSize="7" fill="#c9a565" letterSpacing="2">PROTOCOL · HRT</text>
      <line x1="90" y1="254" x2="190" y2="254" stroke="#c9a565" strokeWidth="0.4" opacity="0.5"/>
      <text x="140" y="284" textAnchor="middle" fontFamily="Cormorant Garamond, serif" fontStyle="italic" fontSize="22" fill="#f0e9d6">Testosterone</text>
      <text x="140" y="304" textAnchor="middle" fontFamily="Cormorant Garamond, serif" fontStyle="italic" fontSize="22" fill="#f0e9d6">Cypionate</text>
      <line x1="100" y1="320" x2="180" y2="320" stroke="#c9a565" strokeWidth="0.4" opacity="0.4"/>
      <text x="140" y="340" textAnchor="middle" fontFamily="Space Mono, monospace" fontSize="7" fill="#a89e85" letterSpacing="1.5">200 MG / ML</text>
      <text x="140" y="354" textAnchor="middle" fontFamily="Space Mono, monospace" fontSize="7" fill="#a89e85" letterSpacing="1.5">10 ML MULTI-DOSE</text>
      <line x1="100" y1="370" x2="180" y2="370" stroke="#c9a565" strokeWidth="0.4" opacity="0.4"/>
      <text x="140" y="386" textAnchor="middle" fontFamily="Space Mono, monospace" fontSize="6" fill="#5a5347" letterSpacing="1.5">RX ONLY · COMPOUNDED</text>

      <rect x="64" y="160" width="6" height="288" fill="url(#db-glassHighlight)" opacity="0.8" rx="3"/>
      <rect x="208" y="170" width="3" height="270" fill="#ffffff" opacity="0.06" rx="2"/>
      <ellipse cx="140" cy="475" rx="100" ry="6" fill="#000" opacity="0.5"/>
    </svg>
  );
}
