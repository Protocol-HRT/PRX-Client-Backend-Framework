'use client';
import React, { useState, Suspense } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import Header from '@/components/Header';
import Footer from '@/components/Footer';

interface FormData {
  firstName: string;
  lastName: string;
  email: string;
  dateOfBirth: string;
  address: string;
  city: string;
  state: string;
  zip: string;
  gender: string;
  goals: string;
}

// ─── Plan Configs ─────────────────────────────────────────────────────────────
const PLANS = {
  trt: {
    name: 'TRT Protocol — $149/mo',
    description: 'All-in monthly program · medication included · live physician video call',
    price: 149,
    originalPrice: 599,
    billingLabel: '/mo',
    mobileLabel: 'TRT Protocol',
    mobileSub: '$149/mo · Limited time',
    confirmLabel: 'Confirm & Start My Protocol — $149/mo',
    showCredit: false,
    includes: [
      'Live physician video call — required before prescribing',
      'Testosterone medication included — all-in monthly pricing',
      'Full AI protocol build delivered before your physician visit',
      'Blood work kit included if clinically indicated',
      'Monthly refill — delivered to your door',
      'Stack up to 3 peptides at checkout (async approval)',
    ],
  },
  blueprint: {
    name: 'Protocol Blueprint Assessment — $49',
    description: 'One-time assessment · $49 credited toward TRT if you upgrade',
    price: 49,
    originalPrice: 149,
    billingLabel: ' one-time',
    mobileLabel: 'Blueprint Assessment',
    mobileSub: '$49 one-time · Credited toward TRT',
    confirmLabel: 'Confirm & Start My Blueprint — $49',
    showCredit: true,
    includes: [
      'Full AI-generated protocol blueprint based on your intake',
      'Physician async review of your protocol',
      'Personalized hormone optimization roadmap',
      'Lab panel recommendations included',
      '$49 credited toward TRT or peptide order if you upgrade',
      'Access to patient portal for 90 days',
    ],
  },
};

const US_STATES = [
  'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA',
  'KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
  'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT',
  'VA','WA','WV','WI','WY',
];

function CheckoutContent() {
  const searchParams = useSearchParams();
  const planKey = (searchParams?.get('plan') === 'blueprint' ? 'blueprint' : 'trt') as keyof typeof PLANS;
  const plan = PLANS[planKey];

  const [form, setForm] = useState<FormData>({
    firstName: '',
    lastName: '',
    email: '',
    dateOfBirth: '',
    address: '',
    city: '',
    state: '',
    zip: '',
    gender: '',
    goals: '',
  });

  const [step, setStep] = useState<1 | 2>(1);
  const [formErrors, setFormErrors] = useState<Partial<Record<keyof FormData, string>>>({});

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
    if (formErrors[e.target.name as keyof FormData]) {
      setFormErrors((prev) => ({ ...prev, [e.target.name]: '' }));
    }
  };

  const validateForm = (): boolean => {
    const errors: Partial<Record<keyof FormData, string>> = {};
    if (!form.firstName.trim()) errors.firstName = 'First name is required';
    if (!form.lastName.trim()) errors.lastName = 'Last name is required';
    if (!form.email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errors.email = 'Valid email is required';
    if (!form.dateOfBirth) errors.dateOfBirth = 'Date of birth is required';
    if (!form.gender) errors.gender = 'Please select your biological sex';
    if (!form.address.trim()) errors.address = 'Shipping address is required';
    if (!form.city.trim()) errors.city = 'City is required';
    if (!form.state) errors.state = 'State is required';
    if (!form.zip.trim() || !/^\d{5}$/.test(form.zip)) errors.zip = 'Valid 5-digit ZIP is required';
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleContinue = () => {
    if (validateForm()) setStep(2);
  };

  const inputClass = `w-full px-4 py-3 rounded-xl border text-sm transition-all duration-200 outline-none focus:ring-2`;
  const inputStyle = {
    background: '#FAFAF8',
    borderColor: 'rgba(56,49,44,0.15)',
    color: '#38312C',
    fontFamily: 'Red Hat Text, sans-serif',
  };
  const focusRingColor = '#779D7C';

  return (
    <div style={{ background: '#F7F4F0', minHeight: '100vh' }}>
      <Header />

      {/* Page Header */}
      <div
        className="pt-32 pb-10 px-4"
        style={{ background: 'linear-gradient(135deg, #F1F5E9 0%, #F7F4F0 60%, #EEF5EE 100%)' }}
      >
        <div className="max-w-5xl mx-auto">
          {/* Breadcrumb */}
          <div className="flex items-center gap-2 mb-6 text-sm" style={{ color: '#8A7F78', fontFamily: 'Red Hat Text, sans-serif' }}>
            <Link href="/homepage" className="hover:underline transition-colors" style={{ color: '#779D7C' }}>
              Home
            </Link>
            <span>/</span>
            <span style={{ color: '#38312C', fontWeight: 500 }}>Checkout</span>
          </div>

          <h1
            className="font-display mb-2"
            style={{
              fontFamily: 'Cormorant Garamond, serif',
              fontSize: 'clamp(32px, 5vw, 48px)',
              fontWeight: 700,
              color: '#38312C',
              lineHeight: 1.15,
            }}
          >
            Complete Your Order
          </h1>
          <p style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif', fontSize: '16px' }}>
            You're one step away from your personalized protocol.
          </p>

          {/* Step Indicator */}
          <div className="flex items-center gap-3 mt-6">
            {[
              { num: 1, label: 'Personal Info' },
              { num: 2, label: 'Payment' },
            ].map((s, i) => (
              <React.Fragment key={s.num}>
                <div className="flex items-center gap-2">
                  <div
                    className="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300"
                    style={{
                      background: step >= s.num ? '#779D7C' : 'rgba(56,49,44,0.1)',
                      color: step >= s.num ? '#fff' : '#8A7F78',
                      fontFamily: 'Red Hat Text, sans-serif',
                    }}
                  >
                    {s.num}
                  </div>
                  <span
                    className="text-sm font-medium hidden sm:block"
                    style={{
                      color: step >= s.num ? '#38312C' : '#8A7F78',
                      fontFamily: 'Red Hat Text, sans-serif',
                    }}
                  >
                    {s.label}
                  </span>
                </div>
                {i < 1 && (
                  <div
                    className="flex-1 h-px max-w-[60px]"
                    style={{ background: step > s.num ? '#779D7C' : 'rgba(56,49,44,0.15)' }}
                  />
                )}
              </React.Fragment>
            ))}
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-5xl mx-auto px-4 py-10 pb-20">
        <div className="grid grid-cols-1 lg:grid-cols-5 gap-8 items-start">

          {/* Left: Form */}
          <div className="lg:col-span-3">

            {/* Step 1: Personal Info */}
            {step === 1 && (
              <div
                className="rounded-2xl p-6 sm:p-8"
                style={{ background: '#FFFFFF', boxShadow: '0 4px 24px rgba(56,49,44,0.07)', border: '1px solid rgba(56,49,44,0.07)' }}
              >
                <h2
                  className="mb-6 font-display"
                  style={{ fontFamily: 'Cormorant Garamond, serif', fontSize: '26px', fontWeight: 700, color: '#38312C' }}
                >
                  Personal Information
                </h2>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  {/* First Name */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                      First Name <span style={{ color: '#779D7C' }}>*</span>
                    </label>
                    <input
                      type="text"
                      name="firstName"
                      value={form.firstName}
                      onChange={handleChange}
                      placeholder="Jane"
                      className={inputClass}
                      style={{ ...inputStyle, borderColor: formErrors.firstName ? '#B43C3C' : 'rgba(56,49,44,0.15)' }}
                      onFocus={(e) => { e.currentTarget.style.borderColor = focusRingColor; e.currentTarget.style.boxShadow = `0 0 0 3px rgba(119,157,124,0.15)`; }}
                      onBlur={(e) => { e.currentTarget.style.borderColor = formErrors.firstName ? '#B43C3C' : 'rgba(56,49,44,0.15)'; e.currentTarget.style.boxShadow = 'none'; }}
                    />
                    {formErrors.firstName && <p className="text-xs mt-1" style={{ color: '#B43C3C', fontFamily: 'Red Hat Text, sans-serif' }}>{formErrors.firstName}</p>}
                  </div>

                  {/* Last Name */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                      Last Name <span style={{ color: '#779D7C' }}>*</span>
                    </label>
                    <input
                      type="text"
                      name="lastName"
                      value={form.lastName}
                      onChange={handleChange}
                      placeholder="Smith"
                      className={inputClass}
                      style={{ ...inputStyle, borderColor: formErrors.lastName ? '#B43C3C' : 'rgba(56,49,44,0.15)' }}
                      onFocus={(e) => { e.currentTarget.style.borderColor = focusRingColor; e.currentTarget.style.boxShadow = `0 0 0 3px rgba(119,157,124,0.15)`; }}
                      onBlur={(e) => { e.currentTarget.style.borderColor = formErrors.lastName ? '#B43C3C' : 'rgba(56,49,44,0.15)'; e.currentTarget.style.boxShadow = 'none'; }}
                    />
                    {formErrors.lastName && <p className="text-xs mt-1" style={{ color: '#B43C3C', fontFamily: 'Red Hat Text, sans-serif' }}>{formErrors.lastName}</p>}
                  </div>

                  {/* Email */}
                  <div className="sm:col-span-2">
                    <label className="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                      Email Address <span style={{ color: '#779D7C' }}>*</span>
                    </label>
                    <input
                      type="email"
                      name="email"
                      value={form.email}
                      onChange={handleChange}
                      placeholder="jane@example.com"
                      className={inputClass}
                      style={{ ...inputStyle, borderColor: formErrors.email ? '#B43C3C' : 'rgba(56,49,44,0.15)' }}
                      onFocus={(e) => { e.currentTarget.style.borderColor = focusRingColor; e.currentTarget.style.boxShadow = `0 0 0 3px rgba(119,157,124,0.15)`; }}
                      onBlur={(e) => { e.currentTarget.style.borderColor = formErrors.email ? '#B43C3C' : 'rgba(56,49,44,0.15)'; e.currentTarget.style.boxShadow = 'none'; }}
                    />
                    {formErrors.email && <p className="text-xs mt-1" style={{ color: '#B43C3C', fontFamily: 'Red Hat Text, sans-serif' }}>{formErrors.email}</p>}
                  </div>

                  {/* Date of Birth */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                      Date of Birth <span style={{ color: '#779D7C' }}>*</span>
                    </label>
                    <input
                      type="date"
                      name="dateOfBirth"
                      value={form.dateOfBirth}
                      onChange={handleChange}
                      className={inputClass}
                      style={{ ...inputStyle, borderColor: formErrors.dateOfBirth ? '#B43C3C' : 'rgba(56,49,44,0.15)' }}
                      onFocus={(e) => { e.currentTarget.style.borderColor = focusRingColor; e.currentTarget.style.boxShadow = `0 0 0 3px rgba(119,157,124,0.15)`; }}
                      onBlur={(e) => { e.currentTarget.style.borderColor = formErrors.dateOfBirth ? '#B43C3C' : 'rgba(56,49,44,0.15)'; e.currentTarget.style.boxShadow = 'none'; }}
                    />
                    {formErrors.dateOfBirth && <p className="text-xs mt-1" style={{ color: '#B43C3C', fontFamily: 'Red Hat Text, sans-serif' }}>{formErrors.dateOfBirth}</p>}
                  </div>

                  {/* Gender */}
                  <div className="sm:col-span-2">
                    <label className="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                      Biological Sex <span style={{ color: '#779D7C' }}>*</span>
                    </label>
                    <div className="flex gap-3">
                      {['Male', 'Female'].map((g) => (
                        <button
                          key={g}
                          type="button"
                          onClick={() => { setForm((prev) => ({ ...prev, gender: g })); setFormErrors((prev) => ({ ...prev, gender: '' })); }}
                          className="flex-1 py-3 rounded-xl text-sm font-semibold border transition-all duration-200"
                          style={{
                            background: form.gender === g ? '#779D7C' : '#FAFAF8',
                            color: form.gender === g ? '#fff' : '#5C5248',
                            borderColor: formErrors.gender ? '#B43C3C' : (form.gender === g ? '#779D7C' : 'rgba(56,49,44,0.15)'),
                            fontFamily: 'Red Hat Text, sans-serif',
                          }}
                        >
                          {g}
                        </button>
                      ))}
                    </div>
                    {formErrors.gender && <p className="text-xs mt-1" style={{ color: '#B43C3C', fontFamily: 'Red Hat Text, sans-serif' }}>{formErrors.gender}</p>}
                  </div>

                  {/* Address */}
                  <div className="sm:col-span-2">
                    <label className="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                      Shipping Address <span style={{ color: '#779D7C' }}>*</span>
                    </label>
                    <input
                      type="text"
                      name="address"
                      value={form.address}
                      onChange={handleChange}
                      placeholder="123 Main Street, Apt 4B"
                      className={inputClass}
                      style={{ ...inputStyle, borderColor: formErrors.address ? '#B43C3C' : 'rgba(56,49,44,0.15)' }}
                      onFocus={(e) => { e.currentTarget.style.borderColor = focusRingColor; e.currentTarget.style.boxShadow = `0 0 0 3px rgba(119,157,124,0.15)`; }}
                      onBlur={(e) => { e.currentTarget.style.borderColor = formErrors.address ? '#B43C3C' : 'rgba(56,49,44,0.15)'; e.currentTarget.style.boxShadow = 'none'; }}
                    />
                    {formErrors.address && <p className="text-xs mt-1" style={{ color: '#B43C3C', fontFamily: 'Red Hat Text, sans-serif' }}>{formErrors.address}</p>}
                  </div>

                  {/* City */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                      City <span style={{ color: '#779D7C' }}>*</span>
                    </label>
                    <input
                      type="text"
                      name="city"
                      value={form.city}
                      onChange={handleChange}
                      placeholder="New York"
                      className={inputClass}
                      style={{ ...inputStyle, borderColor: formErrors.city ? '#B43C3C' : 'rgba(56,49,44,0.15)' }}
                      onFocus={(e) => { e.currentTarget.style.borderColor = focusRingColor; e.currentTarget.style.boxShadow = `0 0 0 3px rgba(119,157,124,0.15)`; }}
                      onBlur={(e) => { e.currentTarget.style.borderColor = formErrors.city ? '#B43C3C' : 'rgba(56,49,44,0.15)'; e.currentTarget.style.boxShadow = 'none'; }}
                    />
                    {formErrors.city && <p className="text-xs mt-1" style={{ color: '#B43C3C', fontFamily: 'Red Hat Text, sans-serif' }}>{formErrors.city}</p>}
                  </div>

                  {/* State */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                      State <span style={{ color: '#779D7C' }}>*</span>
                    </label>
                    <select
                      name="state"
                      value={form.state}
                      onChange={handleChange}
                      className={inputClass}
                      style={{ ...inputStyle, appearance: 'none', borderColor: formErrors.state ? '#B43C3C' : 'rgba(56,49,44,0.15)' }}
                      onFocus={(e) => { e.currentTarget.style.borderColor = focusRingColor; e.currentTarget.style.boxShadow = `0 0 0 3px rgba(119,157,124,0.15)`; }}
                      onBlur={(e) => { e.currentTarget.style.borderColor = formErrors.state ? '#B43C3C' : 'rgba(56,49,44,0.15)'; e.currentTarget.style.boxShadow = 'none'; }}
                    >
                      <option value="">Select state</option>
                      {US_STATES.map((s) => (
                        <option key={s} value={s}>{s}</option>
                      ))}
                    </select>
                    {formErrors.state && <p className="text-xs mt-1" style={{ color: '#B43C3C', fontFamily: 'Red Hat Text, sans-serif' }}>{formErrors.state}</p>}
                  </div>

                  {/* ZIP */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                      ZIP Code <span style={{ color: '#779D7C' }}>*</span>
                    </label>
                    <input
                      type="text"
                      name="zip"
                      value={form.zip}
                      onChange={handleChange}
                      placeholder="10001"
                      maxLength={5}
                      className={inputClass}
                      style={{ ...inputStyle, borderColor: formErrors.zip ? '#B43C3C' : 'rgba(56,49,44,0.15)' }}
                      onFocus={(e) => { e.currentTarget.style.borderColor = focusRingColor; e.currentTarget.style.boxShadow = `0 0 0 3px rgba(119,157,124,0.15)`; }}
                      onBlur={(e) => { e.currentTarget.style.borderColor = formErrors.zip ? '#B43C3C' : 'rgba(56,49,44,0.15)'; e.currentTarget.style.boxShadow = 'none'; }}
                    />
                    {formErrors.zip && <p className="text-xs mt-1" style={{ color: '#B43C3C', fontFamily: 'Red Hat Text, sans-serif' }}>{formErrors.zip}</p>}
                  </div>

                  {/* Health Goals */}
                  <div className="sm:col-span-2">
                    <label className="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                      Primary Health Goals
                    </label>
                    <textarea
                      name="goals"
                      value={form.goals}
                      onChange={handleChange}
                      rows={3}
                      placeholder="e.g. Increase energy, improve body composition, enhance libido..."
                      className={`${inputClass} resize-none`}
                      style={{ ...inputStyle, lineHeight: '1.6' }}
                      onFocus={(e) => { e.currentTarget.style.borderColor = focusRingColor; e.currentTarget.style.boxShadow = `0 0 0 3px rgba(119,157,124,0.15)`; }}
                      onBlur={(e) => { e.currentTarget.style.borderColor = 'rgba(56,49,44,0.15)'; e.currentTarget.style.boxShadow = 'none'; }}
                    />
                  </div>
                </div>

                <button
                  onClick={handleContinue}
                  className="w-full mt-8 py-4 rounded-xl font-semibold text-sm tracking-wide transition-all duration-300"
                  style={{
                    background: '#779D7C',
                    color: '#fff',
                    fontFamily: 'Red Hat Text, sans-serif',
                    fontSize: '14px',
                    letterSpacing: '0.05em',
                    boxShadow: '0 4px 16px rgba(119,157,124,0.35)',
                    border: 'none',
                    cursor: 'pointer',
                  }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.background = '#5E8A63';
                    e.currentTarget.style.transform = 'translateY(-2px)';
                    e.currentTarget.style.boxShadow = '0 8px 24px rgba(119,157,124,0.45)';
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.background = '#779D7C';
                    e.currentTarget.style.transform = 'translateY(0)';
                    e.currentTarget.style.boxShadow = '0 4px 16px rgba(119,157,124,0.35)';
                  }}
                >
                  Continue to Payment →
                </button>
              </div>
            )}

            {/* Step 2: Payment Placeholder */}
            {step === 2 && (
              <div
                className="rounded-2xl p-6 sm:p-8"
                style={{ background: '#FFFFFF', boxShadow: '0 4px 24px rgba(56,49,44,0.07)', border: '1px solid rgba(56,49,44,0.07)' }}
              >
                <button
                  onClick={() => setStep(1)}
                  className="flex items-center gap-2 text-sm mb-6 transition-colors duration-200"
                  style={{ color: '#779D7C', fontFamily: 'Red Hat Text, sans-serif', background: 'none', border: 'none', cursor: 'pointer' }}
                >
                  ← Back to Personal Info
                </button>

                <h2
                  className="mb-2 font-display"
                  style={{ fontFamily: 'Cormorant Garamond, serif', fontSize: '26px', fontWeight: 700, color: '#38312C' }}
                >
                  Payment Details
                </h2>
                <p className="text-sm mb-8" style={{ color: '#8A7F78', fontFamily: 'Red Hat Text, sans-serif' }}>
                  Your payment information is encrypted and secure.
                </p>

                {/* Payment Placeholder */}
                <div
                  className="rounded-2xl p-8 flex flex-col items-center justify-center text-center"
                  style={{
                    background: 'linear-gradient(135deg, #F1F5E9 0%, #EEF5EE 100%)',
                    border: '2px dashed rgba(119,157,124,0.35)',
                    minHeight: '220px',
                  }}
                >
                  {/* Lock Icon */}
                  <div
                    className="w-14 h-14 rounded-full flex items-center justify-center mb-4"
                    style={{ background: 'rgba(119,157,124,0.15)' }}
                  >
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#779D7C" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                  </div>
                  <p
                    className="font-semibold mb-1"
                    style={{ color: '#38312C', fontFamily: 'Red Hat Text, sans-serif', fontSize: '16px' }}
                  >
                    Secure Payment Processing
                  </p>
                  <p className="text-sm" style={{ color: '#8A7F78', fontFamily: 'Red Hat Text, sans-serif', maxWidth: '280px' }}>
                    Payment gateway integration coming soon. Your card details will be encrypted with 256-bit SSL.
                  </p>

                  {/* Accepted Cards */}
                  <div className="flex items-center gap-3 mt-6">
                    {['VISA', 'MC', 'AMEX', 'HSA'].map((card) => (
                      <div
                        key={card}
                        className="px-3 py-1.5 rounded-lg text-xs font-bold"
                        style={{
                          background: '#fff',
                          color: '#5C5248',
                          border: '1px solid rgba(56,49,44,0.12)',
                          fontFamily: 'Red Hat Text, sans-serif',
                          letterSpacing: '0.03em',
                        }}
                      >
                        {card}
                      </div>
                    ))}
                  </div>
                </div>

                {/* Trust badges */}
                <div className="flex flex-wrap items-center justify-center gap-4 mt-6">
                  {[
                    { icon: '🔒', text: '256-bit SSL' },
                    { icon: '✓', text: 'HIPAA Compliant' },
                    { icon: '↩', text: '30-Day Guarantee' },
                  ].map((badge) => (
                    <div key={badge.text} className="flex items-center gap-1.5 text-xs" style={{ color: '#8A7F78', fontFamily: 'Red Hat Text, sans-serif' }}>
                      <span style={{ color: '#779D7C' }}>{badge.icon}</span>
                      {badge.text}
                    </div>
                  ))}
                </div>

                <button
                  className="w-full mt-8 py-4 rounded-xl font-semibold text-sm tracking-wide transition-all duration-300"
                  style={{
                    background: '#779D7C',
                    color: '#fff',
                    fontFamily: 'Red Hat Text, sans-serif',
                    fontSize: '14px',
                    letterSpacing: '0.05em',
                    boxShadow: '0 4px 16px rgba(119,157,124,0.35)',
                    opacity: 0.6,
                    cursor: 'not-allowed',
                  }}
                  disabled
                >
                  {plan.confirmLabel}
                </button>
                <p className="text-center text-xs mt-2" style={{ color: '#8A7F78', fontFamily: 'Red Hat Text, sans-serif' }}>
                  {planKey === 'trt' ? 'Limited time · price increases at launch close' : 'One-time charge · no subscription'}
                </p>
                {plan.showCredit && (
                  <p className="text-center text-xs mt-1" style={{ color: '#779D7C', fontFamily: 'Red Hat Text, sans-serif', fontWeight: 600 }}>
                    $49 credited toward your first protocol upgrade ✓
                  </p>
                )}
                <p className="text-center text-xs mt-2" style={{ color: '#8A7F78', fontFamily: 'Red Hat Text, sans-serif' }}>
                  Payment processing not yet active
                </p>
              </div>
            )}
          </div>

          {/* Right: Order Summary */}
          <div className="lg:col-span-2 lg:sticky lg:top-28">
            <div
              className="rounded-2xl p-6"
              style={{ background: '#FFFFFF', boxShadow: '0 4px 24px rgba(56,49,44,0.07)', border: '1px solid rgba(56,49,44,0.07)' }}
            >
              {/* Special Offer Badge */}
              <div
                className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold mb-4"
                style={{
                  background: 'rgba(119,157,124,0.12)',
                  color: '#5E8A63',
                  fontFamily: 'Red Hat Text, sans-serif',
                  letterSpacing: '0.05em',
                }}
              >
                <span className="w-1.5 h-1.5 rounded-full bg-current animate-pulse" />
                {planKey === 'blueprint' ? 'ONE-TIME ASSESSMENT' : 'LIMITED TIME OFFER'}
              </div>

              <h3
                className="font-display mb-4"
                style={{ fontFamily: 'Cormorant Garamond, serif', fontSize: '22px', fontWeight: 700, color: '#38312C' }}
              >
                Order Summary
              </h3>

              {/* Order Item */}
              <div
                className="flex items-start justify-between gap-3 pb-4 mb-4"
                style={{ borderBottom: '1px solid rgba(56,49,44,0.08)' }}
              >
                <div className="flex-1">
                  <p className="font-semibold text-sm" style={{ color: '#38312C', fontFamily: 'Red Hat Text, sans-serif' }}>
                    {plan.name}
                  </p>
                  <p className="text-xs mt-0.5" style={{ color: '#8A7F78', fontFamily: 'Red Hat Text, sans-serif' }}>
                    {plan.description}
                  </p>
                </div>
                <div className="text-right flex-shrink-0">
                  <p className="font-bold text-sm" style={{ color: '#38312C', fontFamily: 'Red Hat Text, sans-serif' }}>
                    ${plan.price}
                  </p>
                  <p className="text-xs line-through" style={{ color: '#B0A89F', fontFamily: 'Red Hat Text, sans-serif' }}>
                    ${plan.originalPrice}
                  </p>
                </div>
              </div>

              {/* What's Included */}
              <div className="mb-4">
                <p className="text-xs font-semibold uppercase tracking-wide mb-3" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                  What's Included
                </p>
                <ul className="space-y-2">
                  {plan.includes.map((item) => (
                    <li key={item} className="flex items-start gap-2 text-xs" style={{ color: '#5C5248', fontFamily: 'Red Hat Text, sans-serif' }}>
                      <svg className="flex-shrink-0 mt-0.5" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#779D7C" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                      </svg>
                      {item}
                    </li>
                  ))}
                </ul>
              </div>

              {/* Totals */}
              <div
                className="pt-4 space-y-2"
                style={{ borderTop: '1px solid rgba(56,49,44,0.08)' }}
              >
                <div className="flex justify-between text-sm" style={{ fontFamily: 'Red Hat Text, sans-serif' }}>
                  <span style={{ color: '#8A7F78' }}>Subtotal</span>
                  <span style={{ color: '#38312C' }}>${plan.price}{plan.billingLabel}</span>
                </div>
                <div className="flex justify-between text-sm" style={{ fontFamily: 'Red Hat Text, sans-serif' }}>
                  <span style={{ color: '#8A7F78' }}>Shipping</span>
                  <span style={{ color: '#779D7C', fontWeight: 600 }}>FREE</span>
                </div>
                <div
                  className="flex justify-between pt-3 mt-1"
                  style={{ borderTop: '1px solid rgba(56,49,44,0.08)', fontFamily: 'Red Hat Text, sans-serif' }}
                >
                  <span className="font-bold text-base" style={{ color: '#38312C' }}>Total</span>
                  <span className="font-bold text-base" style={{ color: '#38312C' }}>${plan.price}{plan.billingLabel}</span>
                </div>
                {plan.showCredit && (
                  <p className="text-xs text-center pt-1" style={{ color: '#779D7C', fontFamily: 'Red Hat Text, sans-serif', fontWeight: 600 }}>
                    $49 credited toward your first protocol upgrade ✓
                  </p>
                )}
                {!plan.showCredit && (
                  <p className="text-xs text-center pt-1" style={{ color: '#B0A89F', fontFamily: 'Red Hat Text, sans-serif' }}>
                    Limited time · price increases at launch close
                  </p>
                )}
              </div>

              {/* Guarantee */}
              <div
                className="mt-5 rounded-xl p-4 flex items-start gap-3"
                style={{ background: 'rgba(119,157,124,0.08)', border: '1px solid rgba(119,157,124,0.2)' }}
              >
                <svg className="flex-shrink-0 mt-0.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#779D7C" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
                <div>
                  <p className="text-xs font-semibold" style={{ color: '#38312C', fontFamily: 'Red Hat Text, sans-serif' }}>
                    30-Day Money-Back Guarantee
                  </p>
                  <p className="text-xs mt-0.5" style={{ color: '#8A7F78', fontFamily: 'Red Hat Text, sans-serif' }}>
                    Not satisfied? Full refund, no questions asked.
                  </p>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <Footer />

      {/* Mobile Sticky CTA Bar */}
      <div
        className="fixed bottom-0 left-0 right-0 lg:hidden z-50 px-4 py-3"
        style={{
          background: 'rgba(247,244,240,0.97)',
          backdropFilter: 'blur(12px)',
          borderTop: '1px solid rgba(56,49,44,0.12)',
          boxShadow: '0 -4px 24px rgba(56,49,44,0.1)',
        }}
      >
        <div className="flex items-center justify-between gap-3 max-w-lg mx-auto">
          <div>
            <p className="text-xs font-semibold" style={{ color: '#38312C', fontFamily: 'Red Hat Text, sans-serif' }}>{plan.mobileLabel}</p>
            <p className="text-xs" style={{ color: '#779D7C', fontFamily: 'Red Hat Text, sans-serif' }}>{plan.mobileSub}</p>
          </div>
          <button
            onClick={step === 1 ? handleContinue : undefined}
            className="flex-shrink-0 py-3 px-5 rounded-xl font-semibold text-sm"
            style={{
              background: '#779D7C',
              color: '#fff',
              fontFamily: 'Red Hat Text, sans-serif',
              fontSize: '13px',
              border: 'none',
              cursor: 'pointer',
              boxShadow: '0 4px 12px rgba(119,157,124,0.35)',
            }}
          >
            {step === 1 ? 'Continue →' : 'Confirm & Start →'}
          </button>
        </div>
      </div>
    </div>
  );
}

export default function CheckoutPage() {
  return (
    <Suspense fallback={
      <div style={{ background: '#F7F4F0', minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <div style={{ fontFamily: 'Red Hat Text, sans-serif', color: '#8A7F78', fontSize: '15px' }}>Loading checkout…</div>
      </div>
    }>
      <CheckoutContent />
    </Suspense>
  );
}
