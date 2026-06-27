'use client';
import React, { useEffect, useRef } from 'react';

const testimonials = [
  {
    name: 'Marcus T., 47',
    title: 'Executive & Former Athlete',
    protocol: 'HORMONE OPTIMIZATION',
    quote: "I've tried every supplement stack imaginable. ProtocolHRT's physician-reviewed protocol genuinely changed my life. My testosterone is optimized, my recovery is faster, and I feel 15 years younger.",
    result: 'Testosterone Optimized in 90 Days',
    stars: 5,
    initials: 'MT',
    image: undefined as string | undefined,
  },
  {
    name: 'Sarah K., 38',
    title: 'Competitive CrossFit Athlete',
    protocol: 'BODY RECOMPOSITION',
    quote: "As a woman, I was skeptical this was for me. The AI and medical team understood my goals completely: body recomposition, performance, and hormonal balance. The protocol was unlike anything I'd seen.",
    result: 'Lost 18 lbs, Gained Lean Muscle',
    stars: 5,
    initials: 'SK',
    image: undefined as string | undefined,
  },
  {
    name: 'David R., 52',
    title: 'Biohacker & Entrepreneur',
    protocol: 'LONGEVITY & NOOTROPICS',
    quote: 'The depth of clinical knowledge behind this platform is staggering. Real studies, clear explanations, a protocol I could actually verify. This is the future of personalized medicine.',
    result: 'Cognitive Performance Measurably Improved',
    stars: 5,
    initials: 'DR',
    image: undefined as string | undefined,
  },
  {
    name: 'Dr. Joseph Palumbo',
    title: 'Chief Medical Officer · ER Physician',
    protocol: 'PHYSICIAN ENDORSEMENT',
    quote: 'The clinical foundation of ProtocolHRT is unlike anything else in telemedicine. Decades of frontline medicine and deep mastery of hormone and peptide science are built into every protocol.',
    result: 'World-Class Hormone Optimization Specialist',
    stars: 5,
    initials: 'JP',
    image: '/assets/images/825105dd-226e-46b3-a174-8632925027c7-1775477717679.png',
  },
  {
    name: 'Dr. Brent Baldasare',
    title: 'Founding Physician · Lifestyle Management Expert',
    protocol: 'PHYSICIAN ENDORSEMENT',
    quote: "ProtocolHRT was born from a relentless pursuit of what the body is actually capable of when given the right tools: the right hormones, the right peptides, the right protocol.",
    result: 'Author: The Great American Food Fight',
    stars: 5,
    initials: 'BB',
    image: '/assets/images/057ea93f-77db-476d-992c-efae2eb7d7bf-1775477827197.png',
  },
  {
    name: 'Jennifer M., 44',
    title: 'Physician',
    protocol: "WOMEN\'S HORMONE PROTOCOL",
    quote: 'As a doctor, I was impressed by the clinical rigor. Real studies, explained contraindications, a protocol I could verify. Remarkable technology backed by remarkable physicians.',
    result: 'Energy and Vitality Fully Restored',
    stars: 5,
    initials: 'JM',
    image: undefined as string | undefined,
  },
  {
    name: 'Ashley R., NP',
    title: 'Nurse Practitioner · Women\'s Health',
    protocol: "WOMEN\'S HORMONE OPTIMIZATION",
    quote: "What fills my heart every single day is hearing my patients say their lives have completely changed. When a woman finally feels like herself again, her energy, her confidence, her joy, because we optimized her hormones, there is nothing more rewarding. I love being part of that transformation.",
    result: 'Specializes in Female Hormone Optimization',
    stars: 5,
    initials: 'AR',
    image: '/assets/images/nurse_practitioner_blonde.png',
  },
];

const ratingStats = [
  { number: '10,000+', label: 'Patients' },
  { number: '4.9/5', label: 'Average Rating' },
  { number: '94%', label: 'Report Measurable Results' },
];

export default function TestimonialsSection() {
  const sectionRef = useRef<HTMLElement>(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) entry.target.classList.add('is-visible');
        });
      },
      { threshold: 0.08, rootMargin: '0px 0px -60px 0px' }
    );
    sectionRef.current?.querySelectorAll('.reveal-fade, .reveal-left, .reveal-right, .reveal-scale, .stagger-grid, .stagger-cards').forEach((el) => observer.observe(el));
    return () => observer.disconnect();
  }, []);

  return (
    <section
      id="testimonials"
      ref={sectionRef}
      className="py-24 lg:py-32 px-5 sm:px-8 lg:px-10"
      style={{ background: '#141414', borderTop: '1px solid rgba(255,255,255,0.05)' }}
    >
      <div className="max-w-7xl mx-auto">
        <div className="reveal-fade mb-6">
          <span className="editorial-tag">Real Results</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-14">
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
              Thousands have{' '}
              <em style={{ color: '#C9A84C', fontStyle: 'italic' }}>transformed.</em>
            </h2>
          </div>
          <div className="flex flex-col justify-end">
            <div className="reveal-fade reveal-delay-2 flex flex-wrap gap-8">
              {ratingStats.map((s) => (
                <div key={s.label} className="flex items-baseline gap-2">
                  <span
                    className="font-display font-bold"
                    style={{ color: '#C9A84C', fontSize: '30px', lineHeight: 1, fontFamily: 'Cormorant Garamond, serif' }}
                  >
                    {s.number}
                  </span>
                  <span
                    style={{ color: 'rgba(255,255,255,0.4)', fontSize: '13px', fontFamily: 'DM Sans, system-ui, sans-serif' }}
                  >
                    {s.label}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Mobile: snap scroll */}
        <div className="md:hidden testimonial-scroll-container pb-4 -mx-4 px-4">
          {testimonials.map((t) => (
            <div key={t.name} className="testimonial-card-snap" style={{ background: '#1C1C1C', border: '1px solid rgba(201,168,76,0.1)', borderRadius: '20px', padding: '24px', minWidth: '280px' }}>
              <TestimonialCardContent testimonial={t} />
            </div>
          ))}
        </div>

        {/* Desktop grid */}
        <div className="hidden md:grid md:grid-cols-2 gap-4 stagger-cards">
          {testimonials.map((t) => (
            <div
              key={t.name}
              className="card-shine"
              style={{
                background: '#1C1C1C',
                border: '1px solid rgba(201,168,76,0.1)',
                borderRadius: '20px',
                padding: '28px',
                transition: 'border-color 0.3s ease, transform 0.3s cubic-bezier(0.34,1.56,0.64,1)',
              }}
              onMouseEnter={(e) => {
                (e.currentTarget as HTMLElement).style.borderColor = 'rgba(201,168,76,0.3)';
                (e.currentTarget as HTMLElement).style.transform = 'translateY(-4px)';
              }}
              onMouseLeave={(e) => {
                (e.currentTarget as HTMLElement).style.borderColor = 'rgba(201,168,76,0.1)';
                (e.currentTarget as HTMLElement).style.transform = 'translateY(0)';
              }}
            >
              <TestimonialCardContent testimonial={t} />
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function TestimonialCardContent({ testimonial }: { testimonial: typeof testimonials[0] }) {
  return (
    <>
      <div className="flex gap-0.5 mb-3">
        {Array.from({ length: testimonial.stars }).map((_, i) => (
          <span key={i} style={{ color: '#C9A84C', fontSize: '13px' }}>★</span>
        ))}
      </div>

      <span
        style={{
          color: '#C9A84C',
          fontSize: '10px',
          fontFamily: 'JetBrains Mono, monospace',
          fontWeight: 500,
          letterSpacing: '0.08em',
          textTransform: 'uppercase' as const,
          display: 'block',
          marginBottom: '14px',
        }}
      >
        {testimonial.protocol}
      </span>

      <p
        style={{
          color: 'rgba(255,255,255,0.65)',
          fontSize: '14px',
          lineHeight: '1.7',
          fontFamily: 'DM Sans, system-ui, sans-serif',
          fontStyle: 'italic',
          fontWeight: 400,
          marginBottom: '20px',
        }}
      >
        &ldquo;{testimonial.quote}&rdquo;
      </p>

      <div className="flex items-center gap-3">
        <div
          className="w-9 h-9 rounded-full flex-shrink-0 overflow-hidden"
          style={
            !testimonial.image
              ? { background: 'rgba(201,168,76,0.1)', border: '1.5px solid rgba(201,168,76,0.25)' }
              : { border: '1.5px solid rgba(201,168,76,0.25)' }
          }
        >
          {testimonial.image ? (
            <img
              src={testimonial.image}
              alt={testimonial.name}
              className="w-full h-full object-cover object-top"
            />
          ) : (
            <span
              className="w-full h-full flex items-center justify-center"
              style={{ color: '#C9A84C', fontSize: '11px', fontFamily: 'DM Sans, system-ui, sans-serif', fontWeight: 600 }}
            >
              {testimonial.initials}
            </span>
          )}
        </div>
        <div>
          <span
            style={{
              color: '#FFFFFF',
              fontSize: '14px',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontWeight: 500,
              display: 'block',
            }}
          >
            {testimonial.name}
          </span>
          <span
            style={{
              color: 'rgba(255,255,255,0.35)',
              fontSize: '12px',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontWeight: 400,
              display: 'block',
            }}
          >
            {testimonial.title}
          </span>
        </div>
        <div
          className="ml-auto w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0"
          style={{ background: 'rgba(201,168,76,0.15)', border: '1px solid rgba(201,168,76,0.3)' }}
        >
          <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="3">
            <path d="M20 6L9 17l-5-5" />
          </svg>
        </div>
      </div>
    </>
  );
}