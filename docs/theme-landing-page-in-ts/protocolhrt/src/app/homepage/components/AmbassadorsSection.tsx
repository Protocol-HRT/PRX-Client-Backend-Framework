'use client';
import React, { useEffect, useRef } from 'react';
import AppImage from '@/components/ui/AppImage';

const ambassadors = [
  {
    name: 'Dan Bilzerian',
    title: 'Entrepreneur · Lifestyle Icon · Ambassador',
    quote: '"ProtocolHRT delivers exactly what it promises: precision, results, and physician-backed science that actually works at the highest level."',
    protocol: 'Performance & Hormone Optimization',
    image: '/assets/images/Untitled_design_1_-1775477889485.png',
    imageAlt: 'Dan Bilzerian, ProtocolHRT Ambassador',
  },
  {
    name: 'Dr. Joseph Palumbo',
    title: 'Chief Medical Officer · ER Physician',
    quote: '"I built the clinical foundation of ProtocolHRT because I believe every person deserves access to the science that elite performers have always had."',
    protocol: 'Clinical Formulation Lead',
    image: '/assets/images/825105dd-226e-46b3-a174-8632925027c7-1775478625645.png',
    imageAlt: 'Dr. Joseph Palumbo, Chief Medical Officer',
  },
  {
    name: 'Dr. Brent Baldasare',
    title: 'Founding Physician · Author · Survivor',
    quote: '"My recovery from injury showed me what the body can truly do with the right protocol. That experience is the DNA of everything we built here."',
    protocol: 'Lifestyle & Hormone Integration',
    image: '/assets/images/057ea93f-77db-476d-992c-efae2eb7d7bf-1775499747700.png',
    imageAlt: 'Dr. Brent Baldasare, Founding Physician',
  },
  {
    name: 'Ashley R., NP',
    title: 'Nurse Practitioner · Women\'s Health',
    quote: '"What fills my heart every single day is hearing my patients say their lives have completely changed. When a woman finally feels like herself again, her energy, her confidence, her joy, because we optimized her hormones, there is nothing more rewarding."',
    protocol: "Women\'s Hormone Optimization",
    image: '/assets/images/ashley_np_realistic.png',
    imageAlt: 'Ashley R., NP, Blonde female nurse practitioner specializing in women\'s hormone optimization',
  },
];

export default function AmbassadorsSection() {
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

  return (
    <section
      id="ambassadors"
      ref={sectionRef}
      className="py-24 lg:py-32 px-5 sm:px-8 lg:px-10"
      style={{ background: '#0D0D0D', borderTop: '1px solid rgba(255,255,255,0.05)' }}
    >
      <div className="max-w-7xl mx-auto">
        <div className="reveal-fade mb-6">
          <span className="editorial-tag">Trusted by the World&apos;s Best</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-16">
          <div>
            <h2
              className="font-display font-bold reveal-fade reveal-delay-1"
              style={{
                color: '#FFFFFF',
                fontSize: 'clamp(36px, 4.5vw, 58px)',
                lineHeight: '1.04',
                letterSpacing: '-0.025em',
                fontFamily: 'Cormorant Garamond, serif',
              }}
            >
              There&apos;s a reason people are{' '}
              <em style={{ color: '#C9A84C', fontStyle: 'italic' }}>raving about us.</em>
            </h2>
          </div>
          <div className="flex flex-col justify-end">
            <p
              className="reveal-fade reveal-delay-2"
              style={{
                color: 'rgba(255,255,255,0.5)',
                fontSize: '17px',
                lineHeight: '1.7',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontWeight: 400,
              }}
            >
              ProtocolHRT&apos;s protocols have been used and validated by some of the world&apos;s most
              influential athletes, executives, and public figures.
            </p>
          </div>
        </div>

        {/* Ambassador Cards — Large editorial layout */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-5 mb-14 stagger-cards">
          {ambassadors.map((amb) => (
            <div
              key={amb.name}
              style={{
                background: '#141414',
                border: '1px solid rgba(201,168,76,0.12)',
                borderRadius: '24px',
                overflow: 'hidden',
                transition: 'border-color 0.3s ease, transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s ease',
              }}
              onMouseEnter={(e) => {
                (e.currentTarget as HTMLElement).style.borderColor = 'rgba(201,168,76,0.35)';
                (e.currentTarget as HTMLElement).style.transform = 'translateY(-6px)';
                (e.currentTarget as HTMLElement).style.boxShadow = '0 24px 60px rgba(0,0,0,0.5)';
              }}
              onMouseLeave={(e) => {
                (e.currentTarget as HTMLElement).style.borderColor = 'rgba(201,168,76,0.12)';
                (e.currentTarget as HTMLElement).style.transform = 'translateY(0)';
                (e.currentTarget as HTMLElement).style.boxShadow = 'none';
              }}
            >
              {/* Circle photo */}
              <div
                style={{ padding: '28px 28px 0', display: 'flex', justifyContent: 'center' }}
              >
                <div
                  style={{
                    width: '120px',
                    height: '120px',
                    borderRadius: '50%',
                    overflow: 'hidden',
                    border: '3px solid rgba(201,168,76,0.4)',
                    boxShadow: '0 0 0 6px rgba(201,168,76,0.08)',
                    flexShrink: 0,
                  }}
                >
                  <AppImage
                    src={amb.image}
                    alt={amb.imageAlt}
                    width={120}
                    height={120}
                    className="w-full h-full object-cover object-top"
                  />
                </div>
              </div>

              {/* Content */}
              <div style={{ padding: '20px 28px 28px' }}>
                <div className="flex gap-0.5 mb-4">
                  {[1, 2, 3, 4, 5].map((s) => (
                    <span key={s} style={{ color: '#C9A84C', fontSize: '12px' }}>★</span>
                  ))}
                </div>

                <p
                  style={{
                    color: 'rgba(255,255,255,0.7)',
                    fontSize: '14px',
                    lineHeight: '1.75',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    fontStyle: 'italic',
                    fontWeight: 400,
                    marginBottom: '20px',
                  }}
                >
                  {amb.quote}
                </p>

                <div
                  style={{
                    borderTop: '1px solid rgba(255,255,255,0.07)',
                    paddingTop: '16px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                  }}
                >
                  <div>
                    <h3
                      className="font-display font-bold"
                      style={{ color: '#FFFFFF', fontSize: '18px', fontFamily: 'Cormorant Garamond, serif' }}
                    >
                      {amb.name}
                    </h3>
                    <p
                      style={{
                        color: 'rgba(255,255,255,0.4)',
                        fontSize: '12px',
                        fontFamily: 'DM Sans, system-ui, sans-serif',
                        fontWeight: 400,
                        marginTop: '2px',
                      }}
                    >
                      {amb.title}
                    </p>
                  </div>
                  <span
                    style={{
                      background: 'rgba(201,168,76,0.1)',
                      border: '1px solid rgba(201,168,76,0.2)',
                      color: '#C9A84C',
                      fontSize: '10px',
                      fontFamily: 'JetBrains Mono, monospace',
                      fontWeight: 500,
                      letterSpacing: '0.06em',
                      textTransform: 'uppercase' as const,
                      padding: '4px 10px',
                      borderRadius: '20px',
                      whiteSpace: 'nowrap' as const,
                    }}
                  >
                    {amb.protocol}
                  </span>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
