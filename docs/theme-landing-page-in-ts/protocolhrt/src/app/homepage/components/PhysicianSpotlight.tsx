'use client';
import React, { useEffect, useRef } from 'react';
import AppImage from '@/components/ui/AppImage';

interface Physician {
  name: string;
  credentials: string;
  title: string;
  specialty: string;
  stats: { value: string; label: string }[];
  quote: string;
  image: string;
  imageAlt: string;
  accentColor: string;
}

const physicians: Physician[] = [
  {
    name: 'Dr. Brent Baldasare',
    credentials: 'MD, FACS',
    title: 'Founding Physician & CEO',
    specialty: 'Lifestyle Medicine · Longevity Expert',
    stats: [
      { value: '20+', label: 'Years Practice' },
      { value: '35,000+', label: 'Patients Treated' },
      { value: 'Author', label: 'The Great American Food Fight' },
    ],
    quote:
      '"Conventional medicine treats symptoms. We treat the root cause — your hormones, your biology, your potential."',
    image: '/assets/images/057ea93f-77db-476d-992c-efae2eb7d7bf-1775477827197.png',
    imageAlt: 'Dr. Brent Baldasare, MD — Founding Physician and CEO of ProtocolHRT',
    accentColor: '#5A8A5E',
  },
  {
    name: 'Dr. Joseph Palumbo',
    credentials: 'MD, FACEP',
    title: 'Chief Medical Officer',
    specialty: 'Emergency Medicine · Peptide Science',
    stats: [
      { value: '25+', label: 'Years Practice' },
      { value: 'CMO', label: 'ProtocolHRT' },
      { value: 'Expert', label: 'Peptide & HRT Science' },
    ],
    quote:
      '"In the ER I saw what hormonal decline does to people. Now I get to reverse it — before it becomes a crisis."',
    image: '/assets/images/825105dd-226e-46b3-a174-8632925027c7-1775477717679.png',
    imageAlt: 'Dr. Joseph Palumbo, MD — Chief Medical Officer at ProtocolHRT',
    accentColor: '#C9A84C',
  },
];

export default function PhysicianSpotlight() {
  const sectionRef = useRef<HTMLElement>(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) entry.target.classList.add('is-visible');
        });
      },
      { threshold: 0.1 }
    );
    sectionRef.current
      ?.querySelectorAll('.reveal-fade, .reveal-left, .reveal-right, .stagger-cards')
      ?.forEach((el) => observer.observe(el));
    return () => observer.disconnect();
  }, []);

  return (
    <section
      id="physician-spotlight"
      ref={sectionRef}
      className="py-24 px-4 sm:px-6 lg:px-8"
      style={{ background: '#FFFFFF', borderTop: '1px solid rgba(0,0,0,0.06)' }}
    >
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <div className="text-center mb-14">
          <span
            className="reveal-fade"
            style={{
              fontFamily: 'JetBrains Mono, monospace',
              fontSize: '11px',
              letterSpacing: '0.12em',
              textTransform: 'uppercase',
              color: '#5A8A5E',
              display: 'block',
              marginBottom: '12px',
            }}
          >
            Meet Your Physicians
          </span>
          <h2
            className="reveal-fade"
            style={{
              fontFamily: 'Cormorant Garamond, serif',
              fontSize: 'clamp(30px, 4vw, 48px)',
              fontWeight: 700,
              color: '#1A1A1A',
              lineHeight: 1.06,
              letterSpacing: '-0.02em',
            }}
          >
            Real doctors.{' '}
            <em style={{ color: '#5A8A5E', fontStyle: 'italic' }}>Real credentials.</em>
            <br />
            Real results.
          </h2>
          <p
            className="reveal-fade"
            style={{
              color: '#6A6A6A',
              fontSize: '16px',
              marginTop: '14px',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              lineHeight: 1.65,
              maxWidth: '520px',
              margin: '14px auto 0',
            }}
          >
            Every protocol is designed and reviewed by board-certified physicians with decades of
            clinical experience in hormone and peptide optimization.
          </p>
        </div>

        {/* Physician Cards */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 stagger-cards">
          {physicians.map((doc) => (
            <div
              key={doc.name}
              style={{
                background: '#FAFAF8',
                border: '1px solid rgba(0,0,0,0.07)',
                borderRadius: '24px',
                overflow: 'hidden',
                transition: 'box-shadow 0.3s, transform 0.3s',
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.boxShadow = '0 12px 40px rgba(0,0,0,0.1)';
                e.currentTarget.style.transform = 'translateY(-2px)';
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.boxShadow = 'none';
                e.currentTarget.style.transform = 'translateY(0)';
              }}
            >
              {/* Top accent bar */}
              <div style={{ height: '3px', background: doc.accentColor }} />

              <div className="p-7">
                {/* Physician identity */}
                <div className="flex items-start gap-5 mb-6">
                  <div
                    style={{
                      width: '88px',
                      height: '88px',
                      borderRadius: '16px',
                      overflow: 'hidden',
                      flexShrink: 0,
                      border: `2px solid ${doc.accentColor}30`,
                    }}
                  >
                    <AppImage
                      src={doc.image}
                      alt={doc.imageAlt}
                      width={88}
                      height={88}
                      className="w-full h-full object-cover"
                    />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap mb-1">
                      <h3
                        style={{
                          fontFamily: 'Cormorant Garamond, serif',
                          fontSize: '22px',
                          fontWeight: 700,
                          color: '#1A1A1A',
                          lineHeight: 1.1,
                        }}
                      >
                        {doc.name}
                      </h3>
                      <span
                        style={{
                          fontFamily: 'JetBrains Mono, monospace',
                          fontSize: '11px',
                          color: doc.accentColor,
                          background: `${doc.accentColor}12`,
                          border: `1px solid ${doc.accentColor}30`,
                          borderRadius: '6px',
                          padding: '2px 8px',
                          whiteSpace: 'nowrap',
                        }}
                      >
                        {doc.credentials}
                      </span>
                    </div>
                    <p
                      style={{
                        fontFamily: 'DM Sans, system-ui, sans-serif',
                        fontSize: '13px',
                        fontWeight: 600,
                        color: doc.accentColor,
                        marginBottom: '4px',
                      }}
                    >
                      {doc.title}
                    </p>
                    <p
                      style={{
                        fontFamily: 'DM Sans, system-ui, sans-serif',
                        fontSize: '12px',
                        color: '#8A8A8A',
                      }}
                    >
                      {doc.specialty}
                    </p>
                  </div>
                </div>

                {/* Stats row */}
                <div
                  className="grid grid-cols-3 gap-3 mb-6"
                  style={{
                    background: 'rgba(0,0,0,0.03)',
                    borderRadius: '12px',
                    padding: '14px 12px',
                  }}
                >
                  {doc.stats.map((s) => (
                    <div key={s.label} className="text-center">
                      <div
                        style={{
                          fontFamily: 'Cormorant Garamond, serif',
                          fontSize: '20px',
                          fontWeight: 700,
                          color: '#1A1A1A',
                          lineHeight: 1,
                          marginBottom: '3px',
                        }}
                      >
                        {s.value}
                      </div>
                      <div
                        style={{
                          fontFamily: 'DM Sans, system-ui, sans-serif',
                          fontSize: '10px',
                          color: '#8A8A8A',
                          lineHeight: 1.3,
                        }}
                      >
                        {s.label}
                      </div>
                    </div>
                  ))}
                </div>

                {/* Quote */}
                <blockquote
                  style={{
                    fontFamily: 'Cormorant Garamond, serif',
                    fontSize: '16px',
                    fontStyle: 'italic',
                    color: '#3A3A3A',
                    lineHeight: 1.65,
                    borderLeft: `3px solid ${doc.accentColor}`,
                    paddingLeft: '16px',
                    margin: 0,
                  }}
                >
                  {doc.quote}
                </blockquote>
              </div>
            </div>
          ))}
        </div>

        {/* Trust footer */}
        <div
          className="reveal-fade mt-10 text-center py-7 px-6 rounded-2xl"
          style={{ background: '#F2F5F0', border: '1px solid rgba(90,138,94,0.12)' }}
        >
          <div className="flex flex-wrap items-center justify-center gap-6">
            {[
              { icon: '🏥', text: 'Board-Certified Physicians' },
              { icon: '⚕️', text: 'Licensed in All 50 States' },
              { icon: '🔬', text: 'Evidence-Based Protocols' },
              { icon: '📋', text: 'Physician-Reviewed Every Rx' },
            ].map((item) => (
              <div key={item.text} className="flex items-center gap-2">
                <span style={{ fontSize: '16px' }}>{item.icon}</span>
                <span
                  style={{
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    fontSize: '13px',
                    fontWeight: 500,
                    color: '#3A3A3A',
                  }}
                >
                  {item.text}
                </span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
