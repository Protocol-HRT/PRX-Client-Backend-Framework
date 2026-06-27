'use client';
import React, { useEffect, useRef } from 'react';
import { openIntakeModal } from '@/lib/openIntakeModal';

const steps = [
  {
    number: '01',
    title: 'Consult',
    body: 'Complete a quick online evaluation. Our AI concierge, trained on thousands of peer-reviewed clinical studies, listens, learns, and begins building your personalized profile.',
    subLabel: 'Takes about 5 minutes',
  },
  {
    number: '02',
    title: 'Your Protocol',
    body: 'Our AI cross-references your profile against our clinical database to build your protocol. A licensed ProtocolHRT physician reviews and approves every recommendation before it reaches you.',
    subLabel: 'Physician-reviewed · All 50 states',
  },
  {
    number: '03',
    title: 'Delivered',
    body: 'Your medication ships directly to your door. Check in with our AI concierge anytime. Your protocol evolves as your body responds, with ongoing support 24/7.',
    subLabel: 'Ongoing AI support 24/7',
  },
];

export default function ProcessSection() {
  const sectionRef = useRef<HTMLElement>(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) entry.target.classList.add('is-visible');
        });
      },
      { threshold: 0.1, rootMargin: '0px 0px -60px 0px' }
    );
    sectionRef.current?.querySelectorAll('.reveal-fade, .reveal-left, .reveal-right, .reveal-scale, .stagger-grid, .stagger-cards').forEach((el) => observer.observe(el));
    return () => observer.disconnect();
  }, []);

  const scrollToSection = (id: string) => {
    document.querySelector(id)?.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <section
      id="process"
      ref={sectionRef}
      className="py-24 lg:py-32 px-5 sm:px-8 lg:px-10"
      style={{ background: '#F8F7F5', borderTop: '1px solid rgba(0,0,0,0.05)' }}
    >
      <div className="max-w-7xl mx-auto">
        <div className="reveal-fade mb-4">
          <span className="section-label">The Process</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-16">
          <div>
            <h2
              className="font-display font-bold reveal-fade reveal-delay-1"
              style={{
                color: '#1A1A1A',
                fontSize: 'clamp(36px, 4.5vw, 54px)',
                lineHeight: '1.04',
                letterSpacing: '-0.02em',
                fontFamily: 'Cormorant Garamond, serif',
              }}
            >
              Begin your hormone optimization{' '}
              <span style={{ color: '#5A8A5E' }}>journey.</span>
            </h2>
          </div>
          <div className="flex flex-col justify-end">
            <p
              className="reveal-fade reveal-delay-2"
              style={{
                color: '#5A5A5A',
                fontSize: '17px',
                lineHeight: '1.7',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontWeight: 400,
              }}
            >
              Three simple steps between you and the protocol that changes everything.
            </p>
            <div className="mt-6 reveal-fade reveal-delay-3">
              <button
                className="btn-primary"
                onClick={() => openIntakeModal()}
                style={{ height: '50px', minWidth: '180px' }}
              >
                Get Started
              </button>
            </div>
          </div>
        </div>

        {/* Steps */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 stagger-cards">
          {steps.map((step, i) => (
            <div key={step.number} className="relative">
              {i < steps.length - 1 && (
                <div
                  className="hidden lg:block absolute top-5 h-px"
                  style={{
                    background: 'linear-gradient(to right, rgba(90,138,94,0.3), rgba(90,138,94,0.05))',
                    left: 'calc(44px + 16px)',
                    right: '-16px',
                    zIndex: 1,
                  }}
                />
              )}

              <div className="step-number mb-5">{step.number}</div>

              <p
                style={{
                  color: '#5A8A5E',
                  fontSize: '11px',
                  fontFamily: 'JetBrains Mono, monospace',
                  fontWeight: 500,
                  letterSpacing: '0.06em',
                  textTransform: 'uppercase',
                  marginBottom: '10px',
                }}
              >
                {step.subLabel}
              </p>

              <h3
                className="font-display font-bold mb-3"
                style={{ color: '#1A1A1A', fontSize: '22px', fontFamily: 'Cormorant Garamond, serif' }}
              >
                {step.title}
              </h3>

              <p
                style={{
                  color: '#5A5A5A',
                  fontSize: '14px',
                  lineHeight: '1.7',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontWeight: 400,
                }}
              >
                {step.body}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}