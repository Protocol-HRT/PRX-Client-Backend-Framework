'use client';
import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/contexts/AuthContext';
import { createClient } from '@/lib/supabase/client';

type Mode = 'signin' | 'signup' | 'forgot';

export default function LoginPage() {
  const router = useRouter();
  const { user, loading, signIn, signUp } = useAuth();

  const [mode, setMode] = useState<Mode>('signin');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [fullName, setFullName] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [emailConfirmationSent, setEmailConfirmationSent] = useState(false);
  const [forgotPasswordSent, setForgotPasswordSent] = useState(false);

  // If already authenticated, redirect to patient portal
  useEffect(() => {
    if (!loading && user) {
      router.replace('/patient-portal');
    }
  }, [user, loading, router]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (mode === 'forgot') {
      if (!email.trim()) {
        setError('Please enter your email address.');
        return;
      }
      setSubmitting(true);
      try {
        const supabase = createClient();
        const { error: resetError } = await supabase.auth.resetPasswordForEmail(email, {
          redirectTo: `${process.env.NEXT_PUBLIC_SITE_URL}/auth/callback?type=recovery`,
        });
        if (resetError) throw resetError;
        setForgotPasswordSent(true);
      } catch (err: any) {
        setError(err?.message || 'Failed to send reset email. Please try again.');
      } finally {
        setSubmitting(false);
      }
      return;
    }

    if (mode === 'signup' && password !== confirmPassword) {
      setError('Passwords do not match.');
      return;
    }
    if (password.length < 6) {
      setError('Password must be at least 6 characters.');
      return;
    }

    setSubmitting(true);
    try {
      if (mode === 'signin') {
        await signIn(email, password);
        router.replace('/patient-portal');
      } else {
        await signUp(email, password, { fullName });
        // Show email confirmation message instead of redirecting
        setEmailConfirmationSent(true);
      }
    } catch (err: any) {
      setError(err?.message || 'An error occurred. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  const switchMode = (newMode: Mode) => {
    setMode(newMode);
    setError('');
    setEmailConfirmationSent(false);
    setForgotPasswordSent(false);
  };

  if (loading) {
    return (
      <div
        style={{
          minHeight: '100vh',
          background: '#FAFAF8',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        <div
          style={{
            width: 36,
            height: 36,
            border: '2px solid rgba(90,138,94,0.2)',
            borderTopColor: '#5A8A5E',
            borderRadius: '50%',
            animation: 'spin 0.8s linear infinite',
          }}
        />
        <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
      </div>
    );
  }

  return (
    <div
      style={{
        minHeight: '100vh',
        background: '#FAFAF8',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '24px 16px',
        fontFamily: 'DM Sans, system-ui, sans-serif',
      }}
    >
      {/* Logo */}
      <Link
        href="/homepage"
        style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 40, textDecoration: 'none' }}
      >
        <svg width="36" height="36" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M16 2L28 8.5V23.5L16 30L4 23.5V8.5L16 2Z" fill="#1A1A1A" />
          <path
            d="M16 2L28 8.5V23.5L16 30L4 23.5V8.5L16 2Z"
            fill="none"
            stroke="#C9A84C"
            strokeWidth="0.75"
            opacity="0.8"
          />
          <text x="16" y="22" textAnchor="middle" fill="white" fontSize="16" fontWeight="700" fontFamily="Georgia, serif" letterSpacing="-0.5">P</text>
        </svg>
        <span style={{ color: '#1A1A1A', fontSize: 18, fontWeight: 600, letterSpacing: '-0.02em' }}>
          Protocol<span style={{ fontWeight: 800, fontStyle: 'italic', color: '#C9A84C' }}>HRT</span>
        </span>
      </Link>

      {/* Card */}
      <div
        style={{
          width: '100%',
          maxWidth: 440,
          background: '#FFFFFF',
          borderRadius: 16,
          border: '1px solid rgba(0,0,0,0.07)',
          boxShadow: '0 4px 32px rgba(0,0,0,0.06)',
          padding: '40px 36px',
        }}
      >
        {/* Email Confirmation Success */}
        {emailConfirmationSent ? (
          <div style={{ textAlign: 'center' }}>
            <div
              style={{
                width: 56,
                height: 56,
                borderRadius: '50%',
                background: 'rgba(90,138,94,0.1)',
                border: '1px solid rgba(90,138,94,0.2)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                margin: '0 auto 20px',
              }}
            >
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#5A8A5E" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
              </svg>
            </div>
            <h2 style={{ fontSize: 20, fontWeight: 600, color: '#1A1A1A', marginBottom: 10, letterSpacing: '-0.02em' }}>
              Check your email
            </h2>
            <p style={{ fontSize: 14, color: '#6A6A6A', lineHeight: 1.6, marginBottom: 24 }}>
              We sent a confirmation link to <strong style={{ color: '#1A1A1A' }}>{email}</strong>. Click the link in the email to activate your account.
            </p>
            <p style={{ fontSize: 13, color: '#8A8A8A', marginBottom: 20 }}>
              Didn't receive it? Check your spam folder or{' '}
              <button
                onClick={() => switchMode('signup')}
                style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#5A8A5E', fontWeight: 600, fontSize: 13, padding: 0 }}
              >
                try again
              </button>
            </p>
            <button
              onClick={() => switchMode('signin')}
              style={{
                width: '100%',
                padding: '11px 0',
                borderRadius: 10,
                border: '1px solid rgba(0,0,0,0.12)',
                background: 'transparent',
                fontSize: 14,
                fontWeight: 500,
                color: '#4A4A4A',
                cursor: 'pointer',
                fontFamily: 'DM Sans, system-ui, sans-serif',
              }}
            >
              Back to Sign In
            </button>
          </div>
        ) : forgotPasswordSent ? (
          <div style={{ textAlign: 'center' }}>
            <div
              style={{
                width: 56,
                height: 56,
                borderRadius: '50%',
                background: 'rgba(90,138,94,0.1)',
                border: '1px solid rgba(90,138,94,0.2)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                margin: '0 auto 20px',
              }}
            >
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#5A8A5E" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
              </svg>
            </div>
            <h2 style={{ fontSize: 20, fontWeight: 600, color: '#1A1A1A', marginBottom: 10, letterSpacing: '-0.02em' }}>
              Reset link sent
            </h2>
            <p style={{ fontSize: 14, color: '#6A6A6A', lineHeight: 1.6, marginBottom: 24 }}>
              We sent a password reset link to <strong style={{ color: '#1A1A1A' }}>{email}</strong>. Check your inbox and follow the instructions.
            </p>
            <button
              onClick={() => switchMode('signin')}
              style={{
                width: '100%',
                padding: '11px 0',
                borderRadius: 10,
                border: '1px solid rgba(0,0,0,0.12)',
                background: 'transparent',
                fontSize: 14,
                fontWeight: 500,
                color: '#4A4A4A',
                cursor: 'pointer',
                fontFamily: 'DM Sans, system-ui, sans-serif',
              }}
            >
              Back to Sign In
            </button>
          </div>
        ) : mode === 'forgot' ? (
          <>
            <button
              onClick={() => switchMode('signin')}
              style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#5A8A5E', fontSize: 13, fontFamily: 'DM Sans, system-ui, sans-serif', padding: 0, marginBottom: 24, display: 'flex', alignItems: 'center', gap: 4 }}
            >
              ← Back to Sign In
            </button>
            <h1 style={{ fontSize: 22, fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.02em', marginBottom: 6 }}>
              Reset your password
            </h1>
            <p style={{ fontSize: 14, color: '#6A6A6A', marginBottom: 28 }}>
              Enter your email and we'll send you a reset link.
            </p>
            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
              <div>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 500, color: '#4A4A4A', marginBottom: 6 }}>
                  Email Address
                </label>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="you@example.com"
                  required
                  style={{
                    width: '100%',
                    padding: '11px 14px',
                    borderRadius: 10,
                    border: '1px solid rgba(0,0,0,0.12)',
                    fontSize: 14,
                    color: '#1A1A1A',
                    background: '#FAFAF8',
                    outline: 'none',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    boxSizing: 'border-box',
                  }}
                  onFocus={(e) => (e.currentTarget.style.borderColor = '#5A8A5E')}
                  onBlur={(e) => (e.currentTarget.style.borderColor = 'rgba(0,0,0,0.12)')}
                />
              </div>
              {error && (
                <div style={{ padding: '10px 14px', borderRadius: 8, background: 'rgba(180,60,60,0.08)', border: '1px solid rgba(180,60,60,0.2)', fontSize: 13, color: '#B43C3C' }}>
                  {error}
                </div>
              )}
              <button
                type="submit"
                disabled={submitting}
                style={{
                  marginTop: 4,
                  width: '100%',
                  padding: '13px 0',
                  borderRadius: 10,
                  border: 'none',
                  background: submitting ? 'rgba(90,138,94,0.5)' : '#5A8A5E',
                  color: '#FFFFFF',
                  fontSize: 15,
                  fontWeight: 600,
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  cursor: submitting ? 'not-allowed' : 'pointer',
                  letterSpacing: '-0.01em',
                  transition: 'background 0.2s',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  gap: 8,
                }}
              >
                {submitting ? 'Sending…' : 'Send Reset Link'}
              </button>
            </form>
          </>
        ) : (
          <>
            {/* Tab Toggle */}
            <div
              style={{
                display: 'flex',
                background: '#F2F5F0',
                borderRadius: 10,
                padding: 4,
                marginBottom: 32,
              }}
            >
              {(['signin', 'signup'] as Mode[]).map((m) => (
                <button
                  key={m}
                  onClick={() => switchMode(m)}
                  style={{
                    flex: 1,
                    padding: '9px 0',
                    borderRadius: 8,
                    border: 'none',
                    cursor: 'pointer',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    fontSize: 14,
                    fontWeight: mode === m ? 600 : 400,
                    color: mode === m ? '#1A1A1A' : '#6A6A6A',
                    background: mode === m ? '#FFFFFF' : 'transparent',
                    boxShadow: mode === m ? '0 1px 4px rgba(0,0,0,0.08)' : 'none',
                    transition: 'all 0.2s',
                  }}
                >
                  {m === 'signin' ? 'Sign In' : 'Create Account'}
                </button>
              ))}
            </div>

            {/* Heading */}
            <h1
              style={{
                fontSize: 22,
                fontWeight: 600,
                color: '#1A1A1A',
                letterSpacing: '-0.02em',
                marginBottom: 6,
              }}
            >
              {mode === 'signin' ? 'Welcome back' : 'Join ProtocolHRT'}
            </h1>
            <p style={{ fontSize: 14, color: '#6A6A6A', marginBottom: 28 }}>
              {mode === 'signin' ? 'Sign in to access your patient portal.' : 'Create your account to get started.'}
            </p>

            {/* Form */}
            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
              {mode === 'signup' && (
                <div>
                  <label style={{ display: 'block', fontSize: 13, fontWeight: 500, color: '#4A4A4A', marginBottom: 6 }}>
                    Full Name
                  </label>
                  <input
                    type="text"
                    value={fullName}
                    onChange={(e) => setFullName(e.target.value)}
                    placeholder="Jane Smith"
                    required
                    style={{
                      width: '100%',
                      padding: '11px 14px',
                      borderRadius: 10,
                      border: '1px solid rgba(0,0,0,0.12)',
                      fontSize: 14,
                      color: '#1A1A1A',
                      background: '#FAFAF8',
                      outline: 'none',
                      fontFamily: 'DM Sans, system-ui, sans-serif',
                      boxSizing: 'border-box',
                    }}
                    onFocus={(e) => (e.currentTarget.style.borderColor = '#5A8A5E')}
                    onBlur={(e) => (e.currentTarget.style.borderColor = 'rgba(0,0,0,0.12)')}
                  />
                </div>
              )}

              <div>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 500, color: '#4A4A4A', marginBottom: 6 }}>
                  Email Address
                </label>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="you@example.com"
                  required
                  style={{
                    width: '100%',
                    padding: '11px 14px',
                    borderRadius: 10,
                    border: '1px solid rgba(0,0,0,0.12)',
                    fontSize: 14,
                    color: '#1A1A1A',
                    background: '#FAFAF8',
                    outline: 'none',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    boxSizing: 'border-box',
                  }}
                  onFocus={(e) => (e.currentTarget.style.borderColor = '#5A8A5E')}
                  onBlur={(e) => (e.currentTarget.style.borderColor = 'rgba(0,0,0,0.12)')}
                />
              </div>

              <div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 }}>
                  <label style={{ display: 'block', fontSize: 13, fontWeight: 500, color: '#4A4A4A' }}>
                    Password
                  </label>
                  {mode === 'signin' && (
                    <button
                      type="button"
                      onClick={() => switchMode('forgot')}
                      style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#5A8A5E', fontSize: 12, fontFamily: 'DM Sans, system-ui, sans-serif', padding: 0 }}
                    >
                      Forgot password?
                    </button>
                  )}
                </div>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  required
                  style={{
                    width: '100%',
                    padding: '11px 14px',
                    borderRadius: 10,
                    border: '1px solid rgba(0,0,0,0.12)',
                    fontSize: 14,
                    color: '#1A1A1A',
                    background: '#FAFAF8',
                    outline: 'none',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    boxSizing: 'border-box',
                  }}
                  onFocus={(e) => (e.currentTarget.style.borderColor = '#5A8A5E')}
                  onBlur={(e) => (e.currentTarget.style.borderColor = 'rgba(0,0,0,0.12)')}
                />
              </div>

              {mode === 'signup' && (
                <div>
                  <label style={{ display: 'block', fontSize: 13, fontWeight: 500, color: '#4A4A4A', marginBottom: 6 }}>
                    Confirm Password
                  </label>
                  <input
                    type="password"
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    placeholder="••••••••"
                    required
                    style={{
                      width: '100%',
                      padding: '11px 14px',
                      borderRadius: 10,
                      border: '1px solid rgba(0,0,0,0.12)',
                      fontSize: 14,
                      color: '#1A1A1A',
                      background: '#FAFAF8',
                      outline: 'none',
                      fontFamily: 'DM Sans, system-ui, sans-serif',
                      boxSizing: 'border-box',
                    }}
                    onFocus={(e) => (e.currentTarget.style.borderColor = '#5A8A5E')}
                    onBlur={(e) => (e.currentTarget.style.borderColor = 'rgba(0,0,0,0.12)')}
                  />
                </div>
              )}

              {/* Error */}
              {error && (
                <div
                  style={{
                    padding: '10px 14px',
                    borderRadius: 8,
                    background: 'rgba(180,60,60,0.08)',
                    border: '1px solid rgba(180,60,60,0.2)',
                    fontSize: 13,
                    color: '#B43C3C',
                  }}
                >
                  {error}
                </div>
              )}

              {/* Submit */}
              <button
                type="submit"
                disabled={submitting}
                style={{
                  marginTop: 4,
                  width: '100%',
                  padding: '13px 0',
                  borderRadius: 10,
                  border: 'none',
                  background: submitting ? 'rgba(90,138,94,0.5)' : '#5A8A5E',
                  color: '#FFFFFF',
                  fontSize: 15,
                  fontWeight: 600,
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  cursor: submitting ? 'not-allowed' : 'pointer',
                  letterSpacing: '-0.01em',
                  transition: 'background 0.2s',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  gap: 8,
                }}
                onMouseEnter={(e) => { if (!submitting) e.currentTarget.style.background = '#4A7A4E'; }}
                onMouseLeave={(e) => { if (!submitting) e.currentTarget.style.background = '#5A8A5E'; }}
              >
                {submitting ? (
                  <>
                    <span
                      style={{
                        width: 16,
                        height: 16,
                        border: '2px solid rgba(255,255,255,0.3)',
                        borderTopColor: '#FFFFFF',
                        borderRadius: '50%',
                        animation: 'spin 0.8s linear infinite',
                        display: 'inline-block',
                      }}
                    />
                    {mode === 'signin' ? 'Signing in…' : 'Creating account…'}
                  </>
                ) : (
                  mode === 'signin' ? 'Sign In to Patient Portal' : 'Create Account'
                )}
              </button>
            </form>

            {/* Footer link */}
            <p style={{ textAlign: 'center', fontSize: 13, color: '#8A8A8A', marginTop: 24 }}>
              {mode === 'signin' ? "Don't have an account? " : 'Already have an account? '}
              <button
                onClick={() => switchMode(mode === 'signin' ? 'signup' : 'signin')}
                style={{
                  background: 'none',
                  border: 'none',
                  cursor: 'pointer',
                  color: '#5A8A5E',
                  fontWeight: 600,
                  fontSize: 13,
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  padding: 0,
                }}
              >
                {mode === 'signin' ? 'Create one' : 'Sign in'}
              </button>
            </p>
          </>
        )}
      </div>

      {/* Back link */}
      <Link
        href="/homepage"
        style={{ marginTop: 24, fontSize: 13, color: '#8A8A8A', textDecoration: 'none' }}
      >
        ← Back to homepage
      </Link>

      <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
    </div>
  );
}
