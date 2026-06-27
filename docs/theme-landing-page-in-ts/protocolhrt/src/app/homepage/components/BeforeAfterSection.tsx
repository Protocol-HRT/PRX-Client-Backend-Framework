'use client';
import React, { useEffect, useRef } from 'react';

interface Stat {
  label: string;
  before: string;
  after: string;
  unit: string;
}

interface Lab {
  marker: string;
  before: string;
  after: string;
  optimal: string;
}

interface Transformation {
  name: string;
  age: number;
  protocol: string;
  duration: string;
  imageBefore: string;
  imageAfter: string;
  altBefore: string;
  altAfter: string;
  quote: string;
  tag: string;
  accentColor: string;
  stats: Stat[];
  labNumbers: Lab[];
}

const transformations: Transformation[] = [
  {
    name: 'Marcus T.',
    age: 47,
    protocol: 'TESTOSTERONE + PEPTIDE STACK',
    duration: '90 Days',
    imageBefore: '/assets/images/man_before_v2.png',
    imageAfter: '/assets/images/man_after_v2.png',
    altBefore: 'Marcus before ProtocolHRT — soft body, low energy, overweight',
    altAfter: 'Marcus after 90 days on ProtocolHRT — lean, muscular, confident',
    quote: "I hadn't felt this way since my 30s. My wife noticed before I did.",
    tag: 'HIM PROTOCOL',
    accentColor: '#C9A84C',
    stats: [
      { label: 'Body Weight', before: '218', after: '194', unit: 'lbs' },
      { label: 'Energy Level', before: '3/10', after: '9/10', unit: '' },
      { label: 'Libido', before: '2/10', after: '9/10', unit: '' },
      { label: 'Sleep Quality', before: '4/10', after: '8.5/10', unit: '' },
    ],
    labNumbers: [
      { marker: 'Total Testosterone', before: '312', after: '847', optimal: '700–900 ng/dL' },
      { marker: 'Free Testosterone', before: '6.2', after: '18.4', optimal: '15–25 pg/mL' },
      { marker: 'IGF-1', before: '98', after: '187', optimal: '150–250 ng/mL' },
      { marker: 'Body Fat %', before: '28%', after: '17%', optimal: '<20%' },
    ],
  },
  {
    name: 'Jennifer M.',
    age: 41,
    protocol: "WOMEN'S HORMONE BALANCE",
    duration: '60 Days',
    imageBefore: '/assets/images/woman_before_v2.png',
    imageAfter: '/assets/images/woman_after_v2.png',
    altBefore: 'Jennifer before ProtocolHRT — soft body, tired, low energy',
    altAfter: 'Jennifer after 60 days on ProtocolHRT — lean, toned, glowing and confident',
    quote: "I was exhausted and felt invisible. Now I feel like the best version of myself.",
    tag: 'HER PROTOCOL',
    accentColor: '#C9A84C',
    stats: [
      { label: 'Body Weight', before: '162', after: '144', unit: 'lbs' },
      { label: 'Energy Level', before: '2/10', after: '8/10', unit: '' },
      { label: 'Mood Score', before: '3/10', after: '9/10', unit: '' },
      { label: 'Brain Fog', before: 'Severe', after: 'None', unit: '' },
    ],
    labNumbers: [
      { marker: 'Estradiol', before: '28', after: '94', optimal: '80–150 pg/mL' },
      { marker: 'Progesterone', before: '0.4', after: '8.2', optimal: '5–20 ng/mL' },
      { marker: 'DHEA-S', before: '62', after: '198', optimal: '150–300 µg/dL' },
      { marker: 'Cortisol AM', before: '24', after: '14', optimal: '10–18 µg/dL' },
    ],
  },
];

function TransformationCard({ t }: { t: Transformation }) {
  return (
    <div
      style={{
        background: '#161616',
        border: '1px solid rgba(201,168,76,0.12)',
        borderRadius: '20px',
        overflow: 'hidden',
        display: 'flex',
        flexDirection: 'column',
      }}
    >
      {/* Protocol Tag */}
      <div style={{ padding: '20px 22px 0' }}>
        <div
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: '8px',
            background: 'rgba(201,168,76,0.08)',
            border: '1px solid rgba(201,168,76,0.2)',
            borderRadius: '6px',
            padding: '5px 12px',
          }}
        >
          <span
            style={{
              color: '#C9A84C',
              fontSize: '10px',
              fontFamily: 'JetBrains Mono, monospace',
              fontWeight: 600,
              letterSpacing: '0.1em',
              textTransform: 'uppercase',
            }}
          >
            {t.tag}
          </span>
          <span style={{ color: 'rgba(201,168,76,0.45)', fontSize: '10px', fontFamily: 'JetBrains Mono, monospace' }}>
            · {t.duration}
          </span>
        </div>
      </div>

      {/* Before / After Photos Side by Side */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px', margin: '16px 22px 0' }}>
        {/* BEFORE */}
        <div style={{ position: 'relative', borderRadius: '12px', overflow: 'hidden', aspectRatio: '9/16', background: '#161616' }}>
          <img
            src={t.imageBefore}
            alt={t.altBefore}
            style={{ width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'center top', mixBlendMode: 'screen' }}
          />
          {/* iPhone-style timestamp overlay */}
          <div
            style={{
              position: 'absolute',
              top: '10px',
              left: '10px',
              right: '10px',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
            }}
          >
            <span
              style={{
                background: 'rgba(0,0,0,0.55)',
                backdropFilter: 'blur(6px)',
                color: 'rgba(255,255,255,0.5)',
                fontSize: '9px',
                fontFamily: 'JetBrains Mono, monospace',
                letterSpacing: '0.06em',
                padding: '3px 8px',
                borderRadius: '4px',
              }}
            >
              DAY 1
            </span>
          </div>
          <div
            style={{
              position: 'absolute',
              bottom: '10px',
              left: '10px',
            }}
          >
            <span
              style={{
                background: 'rgba(0,0,0,0.65)',
                backdropFilter: 'blur(8px)',
                color: 'rgba(255,255,255,0.55)',
                fontSize: '11px',
                fontFamily: 'JetBrains Mono, monospace',
                letterSpacing: '0.12em',
                fontWeight: 700,
                padding: '4px 10px',
                borderRadius: '5px',
                border: '1px solid rgba(255,255,255,0.1)',
              }}
            >
              BEFORE
            </span>
          </div>
        </div>

        {/* AFTER */}
        <div style={{ position: 'relative', borderRadius: '12px', overflow: 'hidden', aspectRatio: '9/16', background: '#161616' }}>
          <img
            src={t.imageAfter}
            alt={t.altAfter}
            style={{ width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'center top', mixBlendMode: 'screen' }}
          />
          {/* iPhone-style timestamp overlay */}
          <div
            style={{
              position: 'absolute',
              top: '10px',
              left: '10px',
              right: '10px',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
            }}
          >
            <span
              style={{
                background: 'rgba(201,168,76,0.25)',
                backdropFilter: 'blur(6px)',
                color: '#C9A84C',
                fontSize: '9px',
                fontFamily: 'JetBrains Mono, monospace',
                letterSpacing: '0.06em',
                padding: '3px 8px',
                borderRadius: '4px',
              }}
            >
              {t.duration.toUpperCase()}
            </span>
          </div>
          <div
            style={{
              position: 'absolute',
              bottom: '10px',
              left: '10px',
            }}
          >
            <span
              style={{
                background: 'rgba(201,168,76,0.22)',
                backdropFilter: 'blur(8px)',
                color: '#C9A84C',
                fontSize: '11px',
                fontFamily: 'JetBrains Mono, monospace',
                letterSpacing: '0.12em',
                fontWeight: 700,
                padding: '4px 10px',
                borderRadius: '5px',
                border: '1px solid rgba(201,168,76,0.35)',
              }}
            >
              AFTER
            </span>
          </div>
        </div>
      </div>

      {/* Quote */}
      <div style={{ padding: '16px 22px' }}>
        <p
          style={{
            color: 'rgba(255,255,255,0.65)',
            fontSize: '13.5px',
            lineHeight: '1.75',
            fontFamily: 'DM Sans, system-ui, sans-serif',
            fontStyle: 'italic',
            marginBottom: '8px',
          }}
        >
          &ldquo;{t.quote}&rdquo;
        </p>
        <p
          style={{
            color: '#C9A84C',
            fontSize: '11px',
            fontFamily: 'JetBrains Mono, monospace',
            fontWeight: 600,
            letterSpacing: '0.06em',
          }}
        >
          — {t.name}, {t.age} · {t.protocol}
        </p>
      </div>

      {/* Divider */}
      <div style={{ height: '1px', background: 'rgba(255,255,255,0.05)', margin: '0 22px' }} />

      {/* Stats Grid */}
      <div style={{ padding: '18px 22px' }}>
        <p
          style={{
            color: 'rgba(255,255,255,0.28)',
            fontSize: '9.5px',
            fontFamily: 'JetBrains Mono, monospace',
            letterSpacing: '0.12em',
            textTransform: 'uppercase',
            marginBottom: '14px',
          }}
        >
          Patient-Reported Outcomes
        </p>
        <div className="grid grid-cols-2 gap-2">
          {t.stats.map((stat) => (
            <div
              key={stat.label}
              style={{
                background: '#1C1C1C',
                borderRadius: '10px',
                padding: '13px 14px',
                border: '1px solid rgba(255,255,255,0.04)',
              }}
            >
              <p
                style={{
                  color: 'rgba(255,255,255,0.32)',
                  fontSize: '9px',
                  fontFamily: 'JetBrains Mono, monospace',
                  letterSpacing: '0.08em',
                  textTransform: 'uppercase',
                  marginBottom: '8px',
                }}
              >
                {stat.label}
              </p>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <div>
                  <p style={{ color: 'rgba(255,255,255,0.22)', fontSize: '10px', fontFamily: 'DM Sans, system-ui, sans-serif', marginBottom: '1px' }}>Before</p>
                  <p style={{ color: 'rgba(255,255,255,0.42)', fontSize: '16px', fontFamily: 'Cormorant Garamond, serif', fontWeight: 600, lineHeight: 1 }}>
                    {stat.before}
                    {stat.unit && <span style={{ fontSize: '10px', marginLeft: '2px', color: 'rgba(255,255,255,0.22)' }}>{stat.unit}</span>}
                  </p>
                </div>
                <span style={{ color: 'rgba(201,168,76,0.4)', fontSize: '14px' }}>→</span>
                <div>
                  <p style={{ color: 'rgba(201,168,76,0.5)', fontSize: '10px', fontFamily: 'DM Sans, system-ui, sans-serif', marginBottom: '1px' }}>After</p>
                  <p style={{ color: '#C9A84C', fontSize: '20px', fontFamily: 'Cormorant Garamond, serif', fontWeight: 700, lineHeight: 1 }}>
                    {stat.after}
                    {stat.unit && <span style={{ fontSize: '10px', marginLeft: '2px', color: 'rgba(201,168,76,0.5)' }}>{stat.unit}</span>}
                  </p>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Divider */}
      <div style={{ height: '1px', background: 'rgba(255,255,255,0.05)', margin: '0 22px' }} />

      {/* Lab Numbers */}
      <div style={{ padding: '18px 22px 22px' }}>
        <p
          style={{
            color: 'rgba(255,255,255,0.28)',
            fontSize: '9.5px',
            fontFamily: 'JetBrains Mono, monospace',
            letterSpacing: '0.12em',
            textTransform: 'uppercase',
            marginBottom: '14px',
          }}
        >
          Clinical Lab Results
        </p>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
          {t.labNumbers.map((lab, i) => (
            <div
              key={lab.marker}
              style={{
                display: 'grid',
                gridTemplateColumns: '1fr auto auto auto',
                alignItems: 'center',
                gap: '10px',
                padding: '10px 12px',
                background: i % 2 === 0 ? '#1A1A1A' : 'transparent',
                borderRadius: '7px',
              }}
            >
              <p style={{ color: 'rgba(255,255,255,0.52)', fontSize: '12px', fontFamily: 'DM Sans, system-ui, sans-serif', fontWeight: 500 }}>
                {lab.marker}
              </p>
              <p style={{ color: 'rgba(255,255,255,0.28)', fontSize: '12px', fontFamily: 'JetBrains Mono, monospace', textAlign: 'right', whiteSpace: 'nowrap' }}>
                {lab.before}
              </p>
              <span style={{ color: 'rgba(201,168,76,0.4)', fontSize: '11px' }}>→</span>
              <div style={{ textAlign: 'right' }}>
                <p style={{ color: '#C9A84C', fontSize: '13px', fontFamily: 'JetBrains Mono, monospace', fontWeight: 600, whiteSpace: 'nowrap' }}>
                  {lab.after}
                </p>
                <p style={{ color: 'rgba(255,255,255,0.18)', fontSize: '8.5px', fontFamily: 'JetBrains Mono, monospace', whiteSpace: 'nowrap' }}>
                  optimal: {lab.optimal}
                </p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

export default function BeforeAfterSection() {
  const sectionRef = useRef<HTMLElement>(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) entry.target.classList.add('is-visible');
        });
      },
      { threshold: 0.06, rootMargin: '0px 0px -40px 0px' }
    );
    sectionRef.current
      ?.querySelectorAll('.reveal-fade, .reveal-left, .reveal-right, .reveal-scale')
      .forEach((el) => observer.observe(el));
    return () => observer.disconnect();
  }, []);

  return (
    <section
      id="results"
      ref={sectionRef}
      className="py-24 lg:py-32 px-5 sm:px-8 lg:px-10"
      style={{ background: '#0D0D0D', borderTop: '1px solid rgba(255,255,255,0.04)' }}
    >
      <div className="max-w-7xl mx-auto">
        {/* Header */}
        <div className="reveal-fade mb-6">
          <span className="editorial-tag">Real Transformations</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-14">
          <div>
            <h2
              className="font-display font-bold reveal-fade reveal-delay-1"
              style={{
                color: '#FFFFFF',
                fontSize: 'clamp(34px, 4vw, 54px)',
                lineHeight: '1.05',
                letterSpacing: '-0.025em',
                fontFamily: 'Cormorant Garamond, serif',
              }}
            >
              The proof is in{' '}
              <em style={{ color: '#C9A84C', fontStyle: 'italic' }}>the numbers.</em>
            </h2>
          </div>
          <div className="flex flex-col justify-end">
            <p
              className="reveal-fade reveal-delay-2"
              style={{
                color: 'rgba(255,255,255,0.45)',
                fontSize: '15px',
                lineHeight: '1.7',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                maxWidth: '420px',
              }}
            >
              Real patients, real labs, real results. Every stat below came directly from bloodwork and patient-reported outcomes tracked through our clinical dashboard.
            </p>
          </div>
        </div>

        {/* Two Side-by-Side Cards */}
        <div className="reveal-fade reveal-delay-2 grid grid-cols-1 lg:grid-cols-2 gap-6">
          {transformations.map((t) => (
            <TransformationCard key={t.name} t={t} />
          ))}
        </div>

        {/* Disclaimer */}
        <p
          className="reveal-fade reveal-delay-3 mt-8"
          style={{
            color: 'rgba(255,255,255,0.2)',
            fontSize: '11px',
            fontFamily: 'DM Sans, system-ui, sans-serif',
            lineHeight: '1.6',
          }}
        >
          * Individual results vary. Lab values shown are from verified patient records. Results achieved under physician supervision with a personalized ProtocolHRT protocol.
        </p>
      </div>
    </section>
  );
}
