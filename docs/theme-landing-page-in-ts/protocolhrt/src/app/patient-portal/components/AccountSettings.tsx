'use client';
import React, { useState, useEffect } from 'react';
import { createClient } from '@/lib/supabase/client';
import { useAuth } from '@/contexts/AuthContext';

interface UserProfile {
  fullName: string;
  email: string;
  phone: string;
  dateOfBirth: string;
  address: string;
  city: string;
  state: string;
  zip: string;
}

interface NotificationPrefs {
  refillReminders: boolean;
  labResultAlerts: boolean;
  physicianMessages: boolean;
  shipmentUpdates: boolean;
  monthlyReports: boolean;
}

interface SubscriptionInfo {
  plan: string;
  price: string;
  billingCycle: string;
  nextBillingDate: string;
  status: 'active' | 'paused' | 'cancelled';
  memberSince: string;
}

const DEFAULT_PREFS: NotificationPrefs = {
  refillReminders: true,
  labResultAlerts: true,
  physicianMessages: true,
  shipmentUpdates: true,
  monthlyReports: false,
};

const DEMO_SUBSCRIPTION: SubscriptionInfo = {
  plan: "Men's TRT Program",
  price: '$149/mo',
  billingCycle: 'Monthly',
  nextBillingDate: 'May 12, 2026',
  status: 'active',
  memberSince: 'Feb 12, 2026',
};

function SectionCard({ title, subtitle, children }: { title: string; subtitle?: string; children: React.ReactNode }) {
  return (
    <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', overflow: 'hidden' }}>
      <div style={{ padding: '20px 24px', borderBottom: '1px solid rgba(0,0,0,0.05)' }}>
        <h3 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '20px', fontWeight: 600, color: '#1A1A1A', margin: 0, letterSpacing: '-0.01em' }}>{title}</h3>
        {subtitle && <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#8A8A8A', margin: '4px 0 0' }}>{subtitle}</p>}
      </div>
      <div style={{ padding: '20px 24px' }}>{children}</div>
    </div>
  );
}

function Toggle({ checked, onChange, label, description }: { checked: boolean; onChange: (v: boolean) => void; label: string; description?: string }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 0', borderBottom: '1px solid rgba(0,0,0,0.04)' }}>
      <div>
        <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', color: '#1A1A1A', fontWeight: 500, margin: 0 }}>{label}</p>
        {description && <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#8A8A8A', margin: '2px 0 0' }}>{description}</p>}
      </div>
      <button
        onClick={() => onChange(!checked)}
        style={{
          width: '44px',
          height: '24px',
          borderRadius: '12px',
          background: checked ? '#5A8A5E' : 'rgba(0,0,0,0.12)',
          border: 'none',
          cursor: 'pointer',
          position: 'relative',
          transition: 'background 0.2s ease',
          flexShrink: 0,
        }}
        aria-label={label}
      >
        <span style={{
          position: 'absolute',
          top: '2px',
          left: checked ? '22px' : '2px',
          width: '20px',
          height: '20px',
          borderRadius: '50%',
          background: '#FFFFFF',
          boxShadow: '0 1px 3px rgba(0,0,0,0.2)',
          transition: 'left 0.2s ease',
        }} />
      </button>
    </div>
  );
}

function InputField({ label, value, onChange, type = 'text', disabled = false }: { label: string; value: string; onChange?: (v: string) => void; type?: string; disabled?: boolean }) {
  return (
    <div>
      <label style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#8A8A8A', textTransform: 'uppercase' as const, letterSpacing: '0.07em', fontWeight: 600, display: 'block', marginBottom: '6px' }}>
        {label}
      </label>
      <input
        type={type}
        value={value}
        onChange={(e) => onChange?.(e.target.value)}
        disabled={disabled}
        style={{
          width: '100%',
          padding: '10px 14px',
          border: '1px solid rgba(0,0,0,0.1)',
          borderRadius: '8px',
          fontFamily: 'DM Sans, system-ui, sans-serif',
          fontSize: '14px',
          color: disabled ? '#8A8A8A' : '#1A1A1A',
          background: disabled ? 'rgba(0,0,0,0.02)' : '#FFFFFF',
          outline: 'none',
          boxSizing: 'border-box' as const,
          cursor: disabled ? 'not-allowed' : 'text',
        }}
      />
    </div>
  );
}

export default function AccountSettings({ loading: parentLoading }: { loading: boolean }) {
  const { user } = useAuth();
  const [profile, setProfile] = useState<UserProfile>({
    fullName: '',
    email: '',
    phone: '',
    dateOfBirth: '',
    address: '',
    city: '',
    state: '',
    zip: '',
  });
  const [prefs, setPrefs] = useState<NotificationPrefs>(DEFAULT_PREFS);
  const [subscription] = useState<SubscriptionInfo>(DEMO_SUBSCRIPTION);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function fetchProfile() {
      try {
        const supabase = createClient();
        const { data: { user: authUser } } = await supabase.auth.getUser();
        if (!authUser) { setLoading(false); return; }

        const { data: profileData } = await supabase
          .from('user_profiles')
          .select('*')
          .eq('id', authUser.id)
          .single();

        if (profileData) {
          setProfile({
            fullName: profileData.full_name || '',
            email: profileData.email || authUser.email || '',
            phone: profileData.phone || '',
            dateOfBirth: profileData.date_of_birth || '',
            address: profileData.address || '',
            city: profileData.city || '',
            state: profileData.state || '',
            zip: profileData.zip || '',
          });
        } else {
          setProfile((prev) => ({ ...prev, email: authUser.email || '', fullName: authUser.user_metadata?.full_name || '' }));
        }

        const { data: prefsData } = await supabase
          .from('patient_notification_prefs')
          .select('*')
          .eq('user_id', authUser.id)
          .single();

        if (prefsData) {
          setPrefs({
            refillReminders: prefsData.refill_reminders ?? true,
            labResultAlerts: prefsData.lab_result_alerts ?? true,
            physicianMessages: prefsData.physician_messages ?? true,
            shipmentUpdates: prefsData.shipment_updates ?? true,
            monthlyReports: prefsData.monthly_reports ?? false,
          });
        }
      } catch {
        // Use defaults
      }
      setLoading(false);
    }
    fetchProfile();
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      const supabase = createClient();
      const { data: { user: authUser } } = await supabase.auth.getUser();
      if (!authUser) return;

      await supabase.from('user_profiles').upsert({
        id: authUser.id,
        email: profile.email,
        full_name: profile.fullName,
        phone: profile.phone,
        date_of_birth: profile.dateOfBirth,
        address: profile.address,
        city: profile.city,
        state: profile.state,
        zip: profile.zip,
      }, { onConflict: 'id' });

      await supabase.from('patient_notification_prefs').upsert({
        user_id: authUser.id,
        refill_reminders: prefs.refillReminders,
        lab_result_alerts: prefs.labResultAlerts,
        physician_messages: prefs.physicianMessages,
        shipment_updates: prefs.shipmentUpdates,
        monthly_reports: prefs.monthlyReports,
      }, { onConflict: 'user_id' });

      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch {
      // silently fail
    }
    setSaving(false);
  };

  const isLoading = loading || parentLoading;

  return (
    <section>
      <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: '24px', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', color: '#C9A84C', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: '4px' }}>Account</p>
          <h2 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '30px', fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.02em', lineHeight: 1.2, margin: 0 }}>
            Account Settings
          </h2>
        </div>
        <button
          onClick={handleSave}
          disabled={saving || isLoading}
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: '6px',
            background: saved ? '#3A7A3E' : saving ? 'rgba(90,138,94,0.5)' : '#5A8A5E',
            color: '#FFFFFF',
            fontFamily: 'DM Sans, system-ui, sans-serif',
            fontSize: '13px',
            fontWeight: 500,
            padding: '10px 20px',
            borderRadius: '50px',
            border: 'none',
            cursor: saving || isLoading ? 'not-allowed' : 'pointer',
            transition: 'background 0.2s ease',
          }}
        >
          {saved ? (
            <>
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
              Saved
            </>
          ) : saving ? 'Saving…' : 'Save Changes'}
        </button>
      </div>

      <div style={{ display: 'grid', gap: '16px' }}>
        {/* Profile Info */}
        <SectionCard title="Personal Information" subtitle="Your profile details used for your medical records">
          {isLoading ? (
            <div style={{ height: '200px', background: 'rgba(0,0,0,0.03)', borderRadius: '8px', animation: 'pulse 1.5s ease-in-out infinite' }}>
              <style>{`@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }`}</style>
            </div>
          ) : (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '16px' }}>
              <InputField label="Full Name" value={profile.fullName} onChange={(v) => setProfile((p) => ({ ...p, fullName: v }))} />
              <InputField label="Email Address" value={profile.email} disabled />
              <InputField label="Phone Number" value={profile.phone} onChange={(v) => setProfile((p) => ({ ...p, phone: v }))} type="tel" />
              <InputField label="Date of Birth" value={profile.dateOfBirth} onChange={(v) => setProfile((p) => ({ ...p, dateOfBirth: v }))} type="date" />
              <div style={{ gridColumn: '1 / -1' }}>
                <InputField label="Street Address" value={profile.address} onChange={(v) => setProfile((p) => ({ ...p, address: v }))} />
              </div>
              <InputField label="City" value={profile.city} onChange={(v) => setProfile((p) => ({ ...p, city: v }))} />
              <InputField label="State" value={profile.state} onChange={(v) => setProfile((p) => ({ ...p, state: v }))} />
              <InputField label="ZIP Code" value={profile.zip} onChange={(v) => setProfile((p) => ({ ...p, zip: v }))} />
            </div>
          )}
        </SectionCard>

        {/* Subscription */}
        <SectionCard title="Subscription & Billing" subtitle="Your current plan and billing details">
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '16px', marginBottom: '20px' }}>
            {[
              { label: 'Current Plan', value: subscription.plan },
              { label: 'Monthly Rate', value: subscription.price },
              { label: 'Next Billing', value: subscription.nextBillingDate },
              { label: 'Member Since', value: subscription.memberSince },
            ].map(({ label, value }) => (
              <div key={label} style={{ background: 'rgba(0,0,0,0.02)', borderRadius: '10px', padding: '12px 14px' }}>
                <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#8A8A8A', textTransform: 'uppercase', letterSpacing: '0.07em', marginBottom: '4px' }}>{label}</p>
                <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', color: '#1A1A1A', fontWeight: 600, margin: 0 }}>{value}</p>
              </div>
            ))}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '14px 16px', background: 'rgba(90,138,94,0.05)', border: '1px solid rgba(90,138,94,0.15)', borderRadius: '10px', marginBottom: '16px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: '#3A7A3E', display: 'inline-block' }} />
              <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#2A2A2A', fontWeight: 500 }}>Subscription Active — Price Locked at Launch Rate</span>
            </div>
          </div>
          <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
            <button style={{ background: 'none', border: '1px solid rgba(0,0,0,0.1)', borderRadius: '8px', padding: '9px 16px', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', fontWeight: 500, color: '#4A4A4A', cursor: 'pointer' }}>
              Update Payment Method
            </button>
            <button style={{ background: 'none', border: '1px solid rgba(0,0,0,0.1)', borderRadius: '8px', padding: '9px 16px', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', fontWeight: 500, color: '#4A4A4A', cursor: 'pointer' }}>
              View Billing History
            </button>
            <button style={{ background: 'none', border: '1px solid rgba(180,60,60,0.2)', borderRadius: '8px', padding: '9px 16px', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', fontWeight: 500, color: '#B43C3C', cursor: 'pointer' }}>
              Pause Subscription
            </button>
          </div>
        </SectionCard>

        {/* Notifications */}
        <SectionCard title="Notification Preferences" subtitle="Choose what updates you receive via email and SMS">
          <Toggle
            checked={prefs.refillReminders}
            onChange={(v) => setPrefs((p) => ({ ...p, refillReminders: v }))}
            label="Refill Reminders"
            description="Get notified 7 days before your next refill is due"
          />
          <Toggle
            checked={prefs.labResultAlerts}
            onChange={(v) => setPrefs((p) => ({ ...p, labResultAlerts: v }))}
            label="Lab Result Alerts"
            description="Receive an alert when new lab results are available"
          />
          <Toggle
            checked={prefs.physicianMessages}
            onChange={(v) => setPrefs((p) => ({ ...p, physicianMessages: v }))}
            label="Physician Messages"
            description="Notifications when your physician sends a message or update"
          />
          <Toggle
            checked={prefs.shipmentUpdates}
            onChange={(v) => setPrefs((p) => ({ ...p, shipmentUpdates: v }))}
            label="Shipment Updates"
            description="Tracking updates when your order ships and is delivered"
          />
          <Toggle
            checked={prefs.monthlyReports}
            onChange={(v) => setPrefs((p) => ({ ...p, monthlyReports: v }))}
            label="Monthly Protocol Reports"
            description="A monthly summary of your protocol progress and metrics"
          />
        </SectionCard>

        {/* Security */}
        <SectionCard title="Security" subtitle="Manage your password and account access">
          <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
            <button style={{ background: 'none', border: '1px solid rgba(0,0,0,0.1)', borderRadius: '8px', padding: '9px 16px', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', fontWeight: 500, color: '#4A4A4A', cursor: 'pointer' }}>
              Change Password
            </button>
            <button style={{ background: 'none', border: '1px solid rgba(0,0,0,0.1)', borderRadius: '8px', padding: '9px 16px', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', fontWeight: 500, color: '#4A4A4A', cursor: 'pointer' }}>
              Enable Two-Factor Auth
            </button>
          </div>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#8A8A8A', marginTop: '14px', lineHeight: 1.5 }}>
            Your account is protected with industry-standard encryption. All medical data is stored securely and never shared with third parties.
          </p>
        </SectionCard>
      </div>
    </section>
  );
}
