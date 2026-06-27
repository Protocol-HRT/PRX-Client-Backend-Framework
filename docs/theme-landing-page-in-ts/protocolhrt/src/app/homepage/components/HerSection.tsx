'use client';
import React, { useEffect, useRef } from 'react';
import AppImage from '@/components/ui/AppImage';
import { openIntakeModal } from '@/lib/openIntakeModal';

const herProtocols = [
{
  tag: 'HORMONES',
  badge: 'Foundation',
  title: 'Hormone Balance',
  body: 'Estrogen, progesterone, and testosterone work together in women too. Our bioidentical hormone protocols are precision-designed around your labs, your symptoms, and your goals.'
},
{
  tag: 'METABOLIC',
  badge: 'Transform',
  title: 'Weight Loss & Metabolism',
  body: 'GLP-1 support, metabolic optimization, and body recomposition protocols built for female physiology. Clinically proven, physician-reviewed, and personalized to your biology.'
},
{
  tag: 'LONGEVITY',
  badge: 'Rejuvenate',
  title: 'Energy & Anti-Aging',
  body: 'Combat fatigue, brain fog, and accelerated aging with peptide and longevity protocols that target the root cause, declining hormones, not the symptoms.'
},
{
  tag: 'VITALITY',
  badge: 'Restore',
  title: "Women\'s Sexual Health",
  body: 'Restore drive, sensitivity, and balance with targeted protocols addressing female hormonal health at every life stage: perimenopause, menopause, and beyond.'
}];


export default function HerSection() {
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
      id="her"
      ref={sectionRef}
      className="py-24 lg:py-32 px-5 sm:px-8 lg:px-10"
      style={{ background: '#0D0D0D' }}>
      
      <div className="max-w-7xl mx-auto">
        <div className="reveal-fade mb-6">
          <span className="editorial-tag">For Her</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-10 items-start">
          {/* Left: Protocol Cards */}
          <div className="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 stagger-cards order-2 lg:order-1">
            {herProtocols.map((p) =>
            <div
              key={p.title}
              className="dark-card card-shine"
              style={{ padding: '28px' }}>
              
                <div className="flex items-center justify-between mb-4">
                  <span
                  style={{
                    fontFamily: 'JetBrains Mono, monospace',
                    fontSize: '10px',
                    fontWeight: 500,
                    letterSpacing: '0.1em',
                    textTransform: 'uppercase' as const,
                    color: '#C9A84C'
                  }}>
                  
                    {p.tag}
                  </span>
                  <span
                  style={{
                    background: 'rgba(201,168,76,0.1)',
                    border: '1px solid rgba(201,168,76,0.25)',
                    color: '#C9A84C',
                    fontSize: '11px',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    fontWeight: 500,
                    padding: '3px 10px',
                    borderRadius: '20px'
                  }}>
                  
                    {p.badge}
                  </span>
                </div>
                <h3
                className="font-display font-bold mb-3"
                style={{ color: '#FFFFFF', fontSize: '21px', fontFamily: 'Cormorant Garamond, serif' }}>
                
                  {p.title}
                </h3>
                <p
                style={{
                  color: 'rgba(255,255,255,0.55)',
                  fontSize: '14px',
                  lineHeight: '1.7',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontWeight: 400
                }}>
                
                  {p.body}
                </p>
              </div>
            )}
          </div>

          {/* Right: Headline + Photo + CTA */}
          <div className="lg:col-span-1 order-1 lg:order-2">
            <h2
              className="font-display font-bold reveal-fade reveal-delay-1 mb-5"
              style={{
                color: '#FFFFFF',
                fontSize: 'clamp(38px, 4.5vw, 58px)',
                lineHeight: '1.04',
                letterSpacing: '-0.025em',
                fontFamily: 'Cormorant Garamond, serif'
              }}>
              
              Optimized for{' '}
              <em style={{ color: '#C9A84C', fontStyle: 'italic' }}>her biology.</em>
            </h2>

            <p
              className="reveal-fade reveal-delay-2 mb-8"
              style={{
                color: 'rgba(255,255,255,0.55)',
                fontSize: 'clamp(15px, 1.5vw, 17px)',
                lineHeight: '1.7',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontWeight: 400
              }}>
              
              Women&apos;s hormones are complex, dynamic, and deeply personal. Our medical team
              builds protocols that honor the distinct physiology of the female body.
              No guesswork. No one-size-fits-all.
            </p>

            <div
              className="reveal-fade reveal-delay-2 mb-8 overflow-hidden ambassador-photo-frame"
              style={{ borderRadius: '20px', height: '340px', border: '1px solid rgba(201,168,76,0.15)' }}>
              
              {/* ORIGINAL IMAGE (revert): src="https://img.rocket.new/generatedImages/rocket_gen_img_1f5fbdbbc-1775517007402.png" */}
              <AppImage
                src="/assets/images/her_section_woman_elegant.png"
                alt="Strikingly beautiful feminine woman with glowing skin in elegant silk dress, soft golden light, luxury setting, representing the radiant results of female hormone optimization"
                width={500}
                height={400}
                className="w-full h-full object-cover" />
              
            </div>

            <div className="reveal-fade reveal-delay-3">
              <button
                className="btn-gold"
                onClick={() => openIntakeModal()}
                style={{ height: '52px', minWidth: '220px' }}>
                
                Build My HER Protocol
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>);

}