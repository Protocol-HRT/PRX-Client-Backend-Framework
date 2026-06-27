'use client';
import React, { useState, useEffect, useRef } from 'react';

const faqs = [
  {
    q: 'Is this legal?',
    a: 'Yes. ProtocolHRT is a fully licensed telemedicine platform operating in all 50 states. Every protocol is physician-reviewed and prescribed through a legitimate medical process. We operate under the same regulatory framework as any licensed medical practice.',
  },
  {
    q: 'Is it safe?',
    a: 'Every protocol is reviewed and approved by a licensed ProtocolHRT physician before it reaches you. We only recommend compounds with strong clinical evidence profiles. Our AI concierge is trained on thousands of peer-reviewed studies, and your safety is built into every step of the process.',
  },
  {
    q: 'How fast does it ship?',
    a: "Once your protocol is physician-approved, your medication ships directly to your door, typically within 3-5 business days. You'll receive tracking information and can reach our AI concierge 24/7 with any questions.",
  },
  {
    q: "What if it doesn't work?",
    a: "We stand behind our protocols with the ProtocolHRT Guarantee. If you follow your prescribed protocol and don't see measurable results, we will work with you to adjust your protocol at no additional cost. Over 94% of our patients report measurable results within 90 days.",
  },
  {
    q: 'How do I cancel?',
    a: 'You can cancel anytime. No contracts, no hidden fees, no runaround. Simply contact our team through the AI concierge or by email and your subscription will be cancelled immediately. We believe in earning your loyalty, not locking you in.',
  },
];

export default function FaqSection() {
  const [openIndex, setOpenIndex] = useState<number | null>(null);
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
    sectionRef.current?.querySelectorAll('.reveal-fade, .stagger-grid').forEach((el) => observer.observe(el));
    return () => observer.disconnect();
  }, []);

  const toggle = (i: number) => setOpenIndex(openIndex === i ? null : i);

  return (
    <section
      id="faq"
      ref={sectionRef}
      className="py-24 lg:py-32 px-5 sm:px-8 lg:px-10"
      style={{ background: '#FFFFFF', borderTop: '1px solid rgba(0,0,0,0.05)' }}
    >
      <div className="max-w-3xl mx-auto">
        <div className="reveal-fade mb-4">
          <span className="section-label">Common Questions</span>
        </div>

        <h2
          className="font-display font-bold reveal-fade reveal-delay-1 mb-12"
          style={{
            color: '#1A1A1A',
            fontSize: 'clamp(36px, 4.5vw, 54px)',
            lineHeight: '1.04',
            letterSpacing: '-0.02em',
            fontFamily: 'Cormorant Garamond, serif',
          }}
        >
          Frequently asked{' '}
          <span style={{ color: '#5A8A5E' }}>questions</span>
        </h2>

        <div className="stagger-grid space-y-0">
          {faqs.map((faq, i) => (
            <div key={i} style={{ borderBottom: '1px solid rgba(0,0,0,0.07)' }}>
              <button
                className="w-full flex items-center justify-between gap-4 text-left"
                onClick={() => toggle(i)}
                aria-expanded={openIndex === i}
                style={{ padding: '24px 0', minHeight: '68px' }}
              >
                <span
                  className="font-display font-bold"
                  style={{
                    color: '#1A1A1A',
                    fontSize: '20px',
                    lineHeight: '1.3',
                    fontFamily: 'Cormorant Garamond, serif',
                  }}
                >
                  {faq.q}
                </span>
                <span
                  className="flex-shrink-0 transition-transform duration-300"
                  style={{
                    color: '#5A8A5E',
                    transform: openIndex === i ? 'rotate(180deg)' : 'rotate(0deg)',
                    display: 'block',
                  }}
                >
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                    <path d="M6 9l6 6 6-6" />
                  </svg>
                </span>
              </button>

              <div className={`faq-answer ${openIndex === i ? 'open' : ''}`}>
                <p
                  style={{
                    color: '#5A5A5A',
                    fontSize: '15px',
                    lineHeight: '1.75',
                    padding: '0 0 22px',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    fontWeight: 400,
                  }}
                >
                  {faq.a}
                </p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}