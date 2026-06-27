'use client';
import React, { useEffect, useRef } from 'react';
import AppImage from '@/components/ui/AppImage';

const physicians = [
  {
    name: 'Dr. Brent Baldasare',
    title: 'Founding Physician · Lifestyle Management Expert',
    badge: 'Author: The Great American Food Fight',
    bio: "Dr. Brent Baldasare survived a paralyzing college football injury, and his recovery didn't come from the standard playbook. It came from a relentless pursuit of what the body is capable of when given the right hormones, the right peptides, and the right protocol. That pursuit became ProtocolHRT.",
    image: '/assets/images/057ea93f-77db-476d-992c-efae2eb7d7bf-1775477827197.png',
    imageAlt: 'Dr. Brent Baldasare, Founding Physician at ProtocolHRT',
  },
  {
    name: 'Dr. Joseph Palumbo',
    title: 'Chief Medical Officer · ER Physician · Peptide Expert',
    badge: 'World-Class Hormone Optimization Specialist',
    bio: 'Dr. Joseph Palumbo brings decades of frontline ER medicine and deep mastery of hormone and peptide science that most physicians never study. One of the most respected voices in hormone optimization, Dr. Palumbo built the clinical foundation that makes ProtocolHRT unlike anything else in telemedicine.',
    image: '/assets/images/825105dd-226e-46b3-a174-8632925027c7-1775477717679.png',
    imageAlt: 'Dr. Joseph Palumbo, Chief Medical Officer at ProtocolHRT',
  },
];

export default function FoundersSection() {
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
    const els = sectionRef?.current?.querySelectorAll('.reveal-fade, .reveal-left, .reveal-right, .reveal-scale, .stagger-grid, .stagger-cards');
    els?.forEach((el) => observer?.observe(el));
    return () => observer?.disconnect();
  }, []);

  return (
    <section
      id="founders"
      ref={sectionRef}
      className="py-24 lg:py-32 px-5 sm:px-8 lg:px-10"
      style={{ background: '#FFFFFF' }}
    >
      <div className="max-w-7xl mx-auto">
        <div className="reveal-fade mb-4">
          <span className="section-label">The Story Behind ProtocolHRT</span>
        </div>

        <h2
          className="font-display font-bold reveal-fade reveal-delay-1 mb-5"
          style={{
            color: '#1A1A1A',
            fontSize: 'clamp(36px, 4.5vw, 54px)',
            lineHeight: '1.04',
            letterSpacing: '-0.02em',
            fontFamily: 'Cormorant Garamond, serif',
          }}
        >
          Trusted by experts,{' '}
          <span style={{ color: '#5A8A5E' }}>built for you.</span>
        </h2>

        <div className="max-w-2xl reveal-fade reveal-delay-2 mb-14">
          <p
            style={{
              color: '#5A5A5A',
              fontSize: '17px',
              lineHeight: '1.7',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontWeight: 400,
            }}
          >
            ProtocolHRT wasn&apos;t built in a boardroom. It was built by physicians who lived it,
            treated it, and refused to accept the limits of conventional medicine.
          </p>
        </div>

        {/* Physician Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-5 mb-14 stagger-cards">
          {physicians?.map((doc) => (
            <div key={doc?.name} className="doctor-card card-shine">
              <div className="flex gap-5 mb-5">
                <div
                  className="flex-shrink-0 w-16 h-16 rounded-full overflow-hidden"
                  style={{ border: '2px solid rgba(90,138,94,0.25)' }}
                >
                  <AppImage
                    src={doc?.image}
                    alt={doc?.imageAlt}
                    width={64}
                    height={64}
                    className="w-full h-full object-cover"
                  />
                </div>
                <div>
                  <h3
                    className="font-display font-bold mb-1"
                    style={{ color: '#1A1A1A', fontSize: '22px', fontFamily: 'Cormorant Garamond, serif' }}
                  >
                    {doc?.name}
                  </h3>
                  <p
                    style={{
                      color: '#5A8A5E',
                      fontSize: '12px',
                      fontFamily: 'DM Sans, system-ui, sans-serif',
                      fontWeight: 400,
                      marginBottom: '8px',
                    }}
                  >
                    {doc?.title}
                  </p>
                  <span className="gold-badge">{doc?.badge}</span>
                </div>
              </div>
              <p
                style={{
                  color: '#5A5A5A',
                  fontSize: '15px',
                  lineHeight: '1.7',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontWeight: 400,
                }}
              >
                {doc?.bio}
              </p>
            </div>
          ))}
        </div>

        {/* Manifesto */}
        <div
          className="reveal-fade text-center py-10 px-8 rounded-3xl"
          style={{ background: '#F2F5F0', border: '1px solid rgba(90,138,94,0.12)' }}
        >
          <p
            className="manifesto mb-3"
            style={{ fontSize: 'clamp(18px, 2.2vw, 24px)', color: '#1A1A1A' }}
          >
            &ldquo;Built by physicians. Proven by the world&apos;s best. Made for everyone.&rdquo;
          </p>
          <p
            style={{
              color: '#5A8A5E',
              fontSize: '13px',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontWeight: 400,
            }}
          >
            — Dr. Brent Baldasare &amp; Dr. Joseph Palumbo, Founding Physicians
          </p>
        </div>
      </div>
    </section>
  );
}
