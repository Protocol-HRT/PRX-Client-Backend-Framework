import React from 'react';
import type { Metadata, Viewport } from 'next';
import '../styles/index.css';
import '../styles/tailwind.css';
import { Toaster } from 'react-hot-toast';
import { AuthProvider } from '@/contexts/AuthContext';

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
};

export const metadata: Metadata = {
  metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3000'),
  title: 'ProtocolHRT | Premier Hormone & Peptide Telemedicine — Licensed in All 50 States',
  description: 'Access ProtocolHRT\'s elite physician team and AI-powered technology to build your personalized hormone and peptide protocol. Physician-reviewed. Science-backed. Available nationwide.',
  icons: {
    icon: [
      { url: '/favicon.ico', type: 'image/x-icon' }
    ],
  },
  openGraph: {
    title: 'ProtocolHRT | Hormone & Peptide Telemedicine',
    description: 'Physician-reviewed hormone and peptide protocols. AI-powered. Licensed in all 50 states.',
    images: [{ url: '/assets/images/app_logo.png', width: 1200, height: 630 }],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'ProtocolHRT | Hormone & Peptide Telemedicine',
    description: 'Physician-reviewed hormone and peptide protocols. AI-powered. Licensed in all 50 states.',
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body style={{ background: '#FFFFFF' }}>
        <AuthProvider>
          {children}
        </AuthProvider>
        <Toaster position="top-center" toastOptions={{ duration: 4000 }} />
</body>
    </html>
  );
}