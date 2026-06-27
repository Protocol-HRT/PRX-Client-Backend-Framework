'use client';
import React, { useEffect, useRef } from 'react';
import Image from 'next/image';
import { openIntakeModal } from '@/lib/openIntakeModal';

const statsRow = [
{ number: '18%', label: 'Average improvement in key biomarkers' },
{ number: '9/10', label: 'Patients call it the most effective treatment' },
{ number: '94%', label: 'Report measurable results within 90 days' },
{ number: '50', label: 'States fully licensed and operational' }];

export default function FinalCtaSection() {
  const sectionRef = useRef<HTMLElement>(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) entry.target.classList.add('is-visible');
        });
      },
      { threshold: 0.15 }
    );
    sectionRef?.current?.querySelectorAll('.reveal-fade, .reveal-left, .reveal-right, .reveal-scale, .stagger-grid, .stagger-cards')?.forEach((el) => observer?.observe(el));
    return () => observer?.disconnect();
  }, []);

  const vials = [
    { drug: 'Testosterone', concentration: 'Cypionate · 200mg/mL · 10mL', image: '/assets/images/vial_testosterone_v2.png', alt: 'ProtocolHRT Testosterone Cypionate pharmaceutical glass vial with gold crimp cap and white label' },
    { drug: 'GLP-1', concentration: 'Semaglutide · 2.4mg/mL · 3mL', image: '/assets/images/vial_glp1_v2.png', alt: 'ProtocolHRT GLP-1 Semaglutide pharmaceutical glass vial with gold crimp cap and white label' },
    { drug: 'NAD+ / Sermorelin', concentration: 'Peptide Blend · 500mg · 5mL', image: '/assets/images/vial_nad_sermorelin_v2.png', alt: 'ProtocolHRT NAD+ Sermorelin peptide blend pharmaceutical glass vial with gold crimp cap and white label' },
  ];

  return (
    <section
      id="final-cta"
      ref={sectionRef}
      className="py-24 lg:py-32 px-5 sm:px-8 lg:px-10"
      style={{ background: '#0D0D0D', borderTop: '1px solid rgba(255,255,255,0.05)' }}>
      
      <div className="max-w-7xl mx-auto">
        {/* Stats Row */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-14 stagger-grid">
          {statsRow?.map((s) =>
          <div
            key={s?.label}
            className="text-center p-6 rounded-2xl"
            style={{
              background: 'rgba(255,255,255,0.03)',
              border: '1px solid rgba(201,168,76,0.12)'
            }}>
            
              <div
              className="font-display font-bold mb-2"
              style={{ color: '#C9A84C', fontSize: '40px', lineHeight: 1, fontFamily: 'Cormorant Garamond, serif' }}>
              
                {s?.number}
              </div>
              <div
              style={{
                color: 'rgba(255,255,255,0.45)',
                fontSize: '13px',
                lineHeight: '1.5',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontWeight: 400
              }}>
              
                {s?.label}
              </div>
            </div>
          )}
        </div>

        {/* Guarantee Box */}
        <div
          className="reveal-fade rounded-3xl overflow-hidden"
          style={{
            background: '#141414',
            border: '1px solid rgba(201,168,76,0.15)'
          }}>
          
          {/* Vial Banner */}
          <div
            className="grid grid-cols-3 gap-0"
            style={{
              background: 'linear-gradient(180deg, #060606 0%, #0e0e0e 55%, #080808 100%)',
              padding: '56px 0 40px'
            }}
          >
            {vials.map((vial, i) => (
              <div
                key={vial.drug}
                className="flex flex-col items-center justify-end gap-5"
                style={{
                  borderLeft: i > 0 ? '1px solid rgba(201,168,76,0.08)' : 'none',
                  paddingBottom: '8px',
                }}
              >
                <div style={{ position: 'relative', width: '140px', height: '340px' }}>
                  <Image
                    src={vial.image}
                    alt={vial.alt}
                    fill
                    style={{
                      objectFit: 'contain',
                      filter: 'drop-shadow(0 16px 48px rgba(0,0,0,0.85)) drop-shadow(0 4px 12px rgba(201,168,76,0.22))',
                    }}
                  />
                </div>
                <div className="text-center">
                  <div style={{
                    color: 'rgba(255,255,255,0.85)',
                    fontSize: '12px',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    fontWeight: 600,
                    letterSpacing: '0.08em',
                    textTransform: 'uppercase',
                    marginBottom: '2px',
                  }}>{vial.drug}</div>
                  <div style={{
                    color: 'rgba(201,168,76,0.6)',
                    fontSize: '10px',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    letterSpacing: '0.04em',
                  }}>{vial.concentration}</div>
                </div>
              </div>
            ))}
          </div>

          <div className="py-16 px-8 text-center">
            {/* Badge */}
            <div className="reveal-fade mb-6 flex justify-center">
              <div
                className="inline-flex items-center gap-2 px-4 py-2 rounded-full"
                style={{ background: 'rgba(201,168,76,0.08)', border: '1px solid rgba(201,168,76,0.25)' }}>
                
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.5">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
                <span
                  style={{
                    color: '#C9A84C',
                    fontSize: '11px',
                    fontFamily: 'JetBrains Mono, monospace',
                    fontWeight: 500,
                    letterSpacing: '0.08em',
                    textTransform: 'uppercase' as const
                  }}>
                  
                  ProtocolHRT Guarantee
                </span>
              </div>
            </div>

            {/* Headline */}
            <h2
              className="font-display font-bold reveal-fade reveal-delay-1 mb-4"
              style={{
                color: '#FFFFFF',
                fontSize: 'clamp(36px, 4.5vw, 62px)',
                lineHeight: '1.04',
                letterSpacing: '-0.025em',
                fontFamily: 'Cormorant Garamond, serif'
              }}>
              
              The only thing you&apos;ll lose is{' '}
              <em style={{ color: '#C9A84C', fontStyle: 'italic' }}>what&apos;s holding you back.</em>
            </h2>

            {/* Subheadline */}
            <p
              className="reveal-fade reveal-delay-2 mx-auto mb-10"
              style={{
                color: 'rgba(255,255,255,0.5)',
                fontSize: 'clamp(15px, 1.5vw, 17px)',
                lineHeight: '1.7',
                maxWidth: '520px',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontWeight: 400
              }}>
              
              With over 10,000+ patients, we&apos;re confident you will reach your goal with our
              personalized physician-reviewed program. Your AI-powered protocol is waiting.
            </p>

            {/* CTA Buttons */}
            <div className="reveal-fade reveal-delay-3 flex flex-col sm:flex-row gap-3 justify-center mb-8">
              <button
                className="btn-gold"
                onClick={() => openIntakeModal()}
                style={{ height: '54px', minWidth: '240px' }}>
                Find My Protocol →
              </button>
              <button
                className="btn-ghost-white"
                onClick={() => openIntakeModal()}
                style={{ height: '54px', minWidth: '160px' }}>
                Talk to Our Team
              </button>
            </div>

            {/* Trust Row */}
            <div className="reveal-fade reveal-delay-4 flex flex-wrap gap-x-6 gap-y-2 justify-center">
              {['Licensed in All 50 States', 'Physician-Reviewed']?.map((item) =>
              <span
                key={item}
                className="flex items-center gap-1.5"
                style={{
                  color: 'rgba(255,255,255,0.35)',
                  fontSize: '13px',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontWeight: 400
                }}>
                
                  <span style={{ color: '#C9A84C', fontWeight: 600 }}>✓</span>
                  {item}
                </span>
              )}
            </div>
          </div>
        </div>
      </div>
    </section>);
}
