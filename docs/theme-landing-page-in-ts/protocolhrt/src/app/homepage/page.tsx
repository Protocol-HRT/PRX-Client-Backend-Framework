import React from 'react';
import type { Metadata } from 'next';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import HeroSection from './components/HeroSection';
import TrustBar from './components/TrustBar';
import FoundersSection from './components/FoundersSection';
import HimSection from './components/HimSection';
import HerSection from './components/HerSection';
import ProcessSection from './components/ProcessSection';
import SpecialOfferSection from './components/SpecialOfferSection';
import AmbassadorsSection from './components/AmbassadorsSection';
import TestimonialsSection from './components/TestimonialsSection';
import FaqSection from './components/FaqSection';
import FinalCtaSection from './components/FinalCtaSection';
import StickyCtaBar from './components/StickyCtaBar';
import FloatingContactButton from './components/FloatingContactButton';
import AnimatedStats from './components/AnimatedStats';
import PhysicianSpotlight from './components/PhysicianSpotlight';
import AiIntakeModal from './components/AiIntakeModal';

export const metadata: Metadata = {
  title: 'ProtocolHRT | Premier Hormone & Peptide Telemedicine — Licensed in All 50 States',
  description:
    "Access ProtocolHRT's elite physician team and AI-powered technology to build your personalized hormone and peptide protocol. Physician-reviewed. Science-backed. Available nationwide.",
  alternates: {
    canonical: '/homepage',
  },
  openGraph: {
    title: 'ProtocolHRT | Hormone & Peptide Telemedicine',
    description:
      'Physician-reviewed hormone and peptide protocols. AI-powered personalization. Licensed in all 50 states.',
    images: [{ url: '/assets/images/app_logo.png', width: 1200, height: 630 }],
  },
  other: {
    'script:ld+json': JSON.stringify([
      {
        '@context': 'https://schema.org',
        '@type': 'MedicalOrganization',
        name: 'ProtocolHRT',
        description:
          'Premier telemedicine platform for hormone and peptide optimization, licensed in all 50 states.',
        url: 'https://protocolhrt.com',
        areaServed: 'US',
        medicalSpecialty: ['HormoneTherapy', 'PeptideTherapy', 'AntiAging'],
      },
      {
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        name: 'ProtocolHRT',
        url: 'https://protocolhrt.com',
      },
      {
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        name: 'ProtocolHRT AI Concierge',
        applicationCategory: 'HealthApplication',
        description: 'AI-powered hormone and peptide protocol recommendation system.',
      },
      {
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: [
          {
            '@type': 'Question',
            name: 'Is ProtocolHRT legal?',
            acceptedAnswer: {
              '@type': 'Answer',
              text: 'Yes. ProtocolHRT is a fully licensed telemedicine platform operating in all 50 states. Every protocol is physician-reviewed and prescribed through a legitimate medical process.',
            },
          },
          {
            '@type': 'Question',
            name: 'Is ProtocolHRT available in my state?',
            acceptedAnswer: {
              '@type': 'Answer',
              text: 'Yes. ProtocolHRT is fully licensed and operational in all 50 states.',
            },
          },
        ],
      },
    ]),
  },
};

export default function Homepage() {
  return (
    <main
      style={{ background: '#FFFFFF', minHeight: '100vh' }}
      className="overflow-x-hidden"
    >
      {/* Navigation + Announcement Bar */}
      <Header />

      {/* Spacer for fixed header + announcement bar */}
      <div style={{ height: '80px' }} />

      {/* Hero — AI Concierge integrated above the fold */}
      <HeroSection />

      {/* Trust Bar — key stats ticker */}
      <TrustBar />

      {/* Animated Stats — patients, protocols, states */}
      <AnimatedStats />

      {/* Offer Cards — $49 Blueprint, TRT $149/mo, Peptide-Only */}
      <SpecialOfferSection />

      {/* Physician Spotlight — real faces and credentials */}
      <PhysicianSpotlight />

      {/* Founders Story */}
      <FoundersSection />

      {/* HIM Protocols */}
      <HimSection />

      {/* HER Protocols */}
      <HerSection />

      {/* How It Works */}
      <ProcessSection />

      {/* Ambassadors */}
      <AmbassadorsSection />

      {/* Testimonials */}
      <TestimonialsSection />

      {/* FAQ */}
      <FaqSection />

      {/* Final CTA */}
      <FinalCtaSection />

      {/* Footer */}
      <Footer />

      {/* Mobile: Sticky bottom CTA bar */}
      <StickyCtaBar />

      {/* Mobile: Floating contact button */}
      <FloatingContactButton />

      {/* Mobile padding for sticky bar */}
      <div className="md:hidden" style={{ height: '60px' }} />

      {/* Global AI Intake Modal — opened by all CTAs via openIntakeModal() */}
      <AiIntakeModal />
    </main>
  );
}