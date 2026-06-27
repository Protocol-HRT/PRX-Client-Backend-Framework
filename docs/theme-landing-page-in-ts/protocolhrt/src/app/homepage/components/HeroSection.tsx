'use client';
import React, { useEffect, useRef, useState, useCallback } from 'react';
import { useChat } from '@/lib/hooks/useChat';
import ReactMarkdown from 'react-markdown';
import { openIntakeModal } from '@/lib/openIntakeModal';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Message {
  role: 'user' | 'assistant';
  content: string;
  timestamp: number;
}

interface ProtocolRecommendation {
  protocolName: string;
  tagline: string;
  benefits: string[];
  ctaText: string;
  ctaHref: string;
}

interface ContactInfo {
  name: string;
  email: string;
}

type IntakeStage = 'greeting' | 'symptoms' | 'demographics' | 'history' | 'recommendation' | 'complete';

// ─── Constants ────────────────────────────────────────────────────────────────

const STORAGE_KEY = 'protocolhrt_chat_session';

const SYSTEM_PROMPT = `You are ProtocolHRT's AI Concierge — a warm, clinically-informed intake specialist powered by Anthropic Claude. Your mission is to conduct a structured, empathetic health intake and recommend the most appropriate ProtocolHRT protocol.

━━━ AVAILABLE PROTOCOLS ━━━

HIM PROTOCOLS (for men):
• HIM TRT Protocol — Testosterone Replacement Therapy for low T, fatigue, low libido, muscle loss
• HIM Peptide Protocol — Growth hormone peptides for recovery, body composition, anti-aging
• HIM Metabolic Protocol — Weight loss, insulin sensitivity, metabolic optimization
• HIM Cognitive Protocol — Mental clarity, focus, neuroprotection, performance

HER PROTOCOLS (for women):
• HER Hormone Balance Protocol — Estrogen/progesterone balance, perimenopause/menopause relief
• HER Thyroid & Metabolic Protocol — Thyroid optimization, energy, metabolism
• HER Body Composition Protocol — Weight loss, lean muscle, body recomposition
• HER Longevity Protocol — Anti-aging, cellular health, vitality, skin & hair

━━━ INTAKE FLOW ━━━

Stage 1 — GREETING: Introduce yourself warmly. Ask their primary health concern or goal in ONE sentence.
Stage 2 — SYMPTOMS: Explore 2–3 key symptoms. Ask ONE question at a time.
Stage 3 — DEMOGRAPHICS: Ask their age and biological sex (to determine HIM vs HER).
Stage 4 — HISTORY: Ask ONE brief question about existing conditions or current medications/supplements.
Stage 5 — RECOMMENDATION: Deliver a clear, personalized protocol recommendation. End with: "I've prepared your personalized protocol summary below."

━━━ TONE & RULES ━━━
• Warm, professional, empathetic — like a trusted health advisor
• Keep each response to 2–4 sentences max
• Ask ONLY one question per turn
• NEVER provide specific dosing information
• NEVER diagnose — you are an intake specialist, not a physician
• Always note physician review before delivery`;

const SUMMARY_SYSTEM_PROMPT = `You are a protocol card generator for ProtocolHRT. Analyze the conversation and return ONLY a valid JSON object — no markdown, no code blocks, no explanation.

Required structure:
{
  "protocolName": "HIM TRT Protocol",
  "tagline": "Restore testosterone, energy, and drive naturally.",
  "benefits": ["Increased energy & vitality", "Improved libido & sexual health", "Enhanced muscle mass & recovery"],
  "ctaText": "Build My Protocol",
  "ctaHref": "/checkout"
}

Rules:
- protocolName MUST start with "HIM" or "HER" followed by the specific protocol name
- tagline: one compelling sentence, max 10 words
- benefits: exactly 3 items, each 4–7 words, outcome-focused
- ctaHref: always "/checkout"
- Return ONLY the raw JSON object`;

const INITIAL_MESSAGE: Message = {
  role: 'assistant',
  content: "Hi, I'm your ProtocolHRT Concierge. I'll help you find the right hormone or peptide plan based on your goals, symptoms, and biology.\n\nLet's start simple—what would you like to improve most right now?",
  timestamp: Date.now(),
};

const STAGE_PROMPTS: Record<string, string[]> = {
  greeting: ['Low energy & fatigue', 'Hormone balance', 'Burn Fat', 'Build Muscle', 'Better sleep & mood', 'Sexual Health'],
  symptoms: ['Yes, my sleep is poor', 'My libido has dropped', 'I feel foggy & unfocused', "I've gained weight recently"],
  demographics: ["I'm in my 30s", "I'm in my 40s", "I'm in my 50s", "I'm in my 60s+"],
  history: ['No existing conditions', 'I take some supplements', 'I have thyroid issues', "I'm on medication"],
};

const RECOMMENDATION_KEYWORDS = [
  'recommend', 'protocol for you', 'best fit', 'ideal protocol', 'him protocol', 'her protocol',
  'trt protocol', 'hormone balance protocol', 'weight loss protocol', 'peptide protocol',
  'longevity protocol', 'body composition protocol', 'metabolic protocol', 'cognitive protocol',
  'thyroid', 'based on what you', 'based on your', 'suggest the', 'prepared your personalized',
  'protocol summary below', 'physician-reviewed before delivery', 'fits your profile', 'your profile',
];

function detectRecommendation(messages: Message[]): boolean {
  const assistantMessages = messages.filter((m) => m.role === 'assistant');
  if (assistantMessages.length < 4) return false;
  const lastTwo = assistantMessages.slice(-2);
  const combinedText = lastTwo.map((m) => m.content.toLowerCase()).join(' ');
  const matchCount = RECOMMENDATION_KEYWORDS.filter((kw) => combinedText.includes(kw)).length;
  return matchCount >= 2;
}

function inferStage(messages: Message[]): IntakeStage {
  const count = messages.filter((m) => m.role === 'assistant').length;
  if (count <= 1) return 'greeting';
  if (count <= 3) return 'symptoms';
  if (count <= 5) return 'demographics';
  if (count <= 6) return 'history';
  if (detectRecommendation(messages)) return 'recommendation';
  return 'symptoms';
}

// ─── Style helpers ─────────────────────────────────────────────────────────────

const darkLabelStyle: React.CSSProperties = {
  display: 'block',
  fontFamily: 'DM Sans, system-ui, sans-serif',
  fontSize: '11px',
  fontWeight: 600,
  letterSpacing: '0.08em',
  color: 'rgba(255,255,255,0.5)',
  textTransform: 'uppercase',
  marginBottom: '6px',
};

const darkInputStyle = (hasError: boolean): React.CSSProperties => ({
  width: '100%',
  padding: '10px 14px',
  borderRadius: '10px',
  border: hasError ? '1.5px solid #E57373' : '1.5px solid rgba(255,255,255,0.12)',
  background: 'rgba(255,255,255,0.06)',
  color: '#FFFFFF',
  fontSize: '14px',
  fontFamily: 'DM Sans, system-ui, sans-serif',
  outline: 'none',
  transition: 'border-color 0.2s',
  boxSizing: 'border-box',
});

const errorStyle: React.CSSProperties = {
  fontFamily: 'DM Sans, system-ui, sans-serif',
  fontSize: '11px',
  color: '#E57373',
  marginTop: '4px',
};

// ─── HeroSection ──────────────────────────────────────────────────────────────

export default function HeroSection() {
  const sectionRef = useRef<HTMLElement>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const cardRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const hasRestoredSession = useRef(false);

  const [messages, setMessages] = useState<Message[]>([INITIAL_MESSAGE]);
  const [input, setInput] = useState('');
  const [streamingContent, setStreamingContent] = useState('');
  const [hasStarted, setHasStarted] = useState(false);
  const [recommendation, setRecommendation] = useState<ProtocolRecommendation | null>(null);
  const [isFetchingCard, setIsFetchingCard] = useState(false);
  const [cardVisible, setCardVisible] = useState(false);
  const [contactInfo, setContactInfo] = useState<ContactInfo>({ name: '', email: '' });
  const [contactSubmitted, setContactSubmitted] = useState(false);
  const [showContactForm, setShowContactForm] = useState(false);
  const [contactErrors, setContactErrors] = useState<Partial<ContactInfo>>({});
  const [currentStage, setCurrentStage] = useState<IntakeStage>('greeting');
  const [isTyping, setIsTyping] = useState(false);

  const { response, isLoading, error, sendMessage } = useChat('ANTHROPIC', 'claude-sonnet-4-5-20250929', true);
  const { response: summaryResponse, isLoading: summaryLoading, error: summaryError, sendMessage: sendSummary } = useChat('ANTHROPIC', 'claude-sonnet-4-5-20250929', false);

  // ── Intersection observer for reveal animations ──────────────────────────────
  useEffect(() => {
    const el = sectionRef.current;
    if (!el) return;
    const observer = new IntersectionObserver(
      (entries) => { entries.forEach((entry) => { if (entry.isIntersecting) entry.target.classList.add('is-visible'); }); },
      { threshold: 0.05 }
    );
    el.querySelectorAll('.reveal-fade').forEach((item) => observer.observe(item));
    return () => observer.disconnect();
  }, []);

  // ── Session persistence ──────────────────────────────────────────────────────
  useEffect(() => {
    if (hasRestoredSession.current) return;
    hasRestoredSession.current = true;
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed.messages?.length > 1) {
          setMessages(parsed.messages);
          setHasStarted(true);
          if (parsed.recommendation) { setRecommendation(parsed.recommendation); setCardVisible(true); }
          if (parsed.contactSubmitted) setContactSubmitted(true);
        }
      }
    } catch { /* ignore */ }
  }, []);

  useEffect(() => {
    if (!hasStarted) return;
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify({ messages, recommendation, contactSubmitted })); }
    catch { /* ignore */ }
  }, [messages, recommendation, contactSubmitted, hasStarted]);

  // ── Streaming response handler ───────────────────────────────────────────────
  useEffect(() => {
    if (isLoading && response) { setStreamingContent(response); setIsTyping(false); }
    if (!isLoading && response && streamingContent) {
      const newMsg: Message = { role: 'assistant', content: response, timestamp: Date.now() };
      const newMessages = [...messages, newMsg];
      setMessages(newMessages);
      setStreamingContent('');
      setIsTyping(false);
      const stage = inferStage(newMessages);
      setCurrentStage(stage);
      if (!recommendation && !isFetchingCard && detectRecommendation(newMessages)) {
        setIsFetchingCard(true);
        setShowContactForm(true);
      }
    }
  }, [response, isLoading]);

  useEffect(() => {
    if (isLoading && !response) setIsTyping(true);
    else setIsTyping(false);
  }, [isLoading, response]);

  // ── Summary → recommendation card ───────────────────────────────────────────
  useEffect(() => {
    if (!summaryLoading && summaryResponse && isFetchingCard) {
      try {
        const cleaned = summaryResponse.replace(/```json|```/g, '').trim();
        const parsed: ProtocolRecommendation = JSON.parse(cleaned);
        if (parsed.protocolName && parsed.benefits?.length) {
          setRecommendation(parsed);
          setIsFetchingCard(false);
          setTimeout(() => {
            setCardVisible(true);
            setTimeout(() => { cardRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 200);
          }, 500);
        }
      } catch { setIsFetchingCard(false); }
    }
  }, [summaryResponse, summaryLoading]);

  useEffect(() => { messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' }); }, [messages, streamingContent, isTyping]);

  // ── Helpers ──────────────────────────────────────────────────────────────────
  const buildApiMessages = useCallback((newUserMessage: string) => {
    const history = messages.map((m) => ({ role: m.role, content: m.content }));
    return [{ role: 'system' as const, content: SYSTEM_PROMPT }, ...history, { role: 'user' as const, content: newUserMessage }];
  }, [messages]);

  const handleSend = useCallback((text?: string) => {
    const userText = (text ?? input).trim();
    if (!userText || isLoading) return;
    if (!hasStarted) setHasStarted(true);
    const userMsg: Message = { role: 'user', content: userText, timestamp: Date.now() };
    setMessages((prev) => [...prev, userMsg]);
    setInput('');
    sendMessage(buildApiMessages(userText), { temperature: 0.72, max_tokens: 700 });
    inputRef.current?.focus();
  }, [input, isLoading, hasStarted, buildApiMessages, sendMessage]);

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); }
  };

  const handleReset = () => {
    setMessages([INITIAL_MESSAGE]); setInput(''); setStreamingContent(''); setHasStarted(false);
    setRecommendation(null); setCardVisible(false); setIsFetchingCard(false);
    setContactInfo({ name: '', email: '' }); setContactSubmitted(false); setShowContactForm(false);
    setContactErrors({}); setCurrentStage('greeting'); setIsTyping(false);
    try { localStorage.removeItem(STORAGE_KEY); } catch { /* ignore */ }
  };

  const validateContact = (): boolean => {
    const errors: Partial<ContactInfo> = {};
    if (!contactInfo.name.trim()) errors.name = 'Name is required';
    if (!contactInfo.email.trim()) errors.email = 'Email is required';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactInfo.email)) errors.email = 'Please enter a valid email';
    setContactErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleContactSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateContact()) return;
    setContactSubmitted(true);
    setShowContactForm(false);
    const conversationText = messages.map((m) => `${m.role === 'user' ? 'User' : 'AI Concierge'}: ${m.content}`).join('\n\n');
    sendSummary(
      [{ role: 'system', content: SUMMARY_SYSTEM_PROMPT }, { role: 'user', content: `Conversation:\n\n${conversationText}\n\nGenerate the JSON recommendation card.` }],
      { temperature: 0.15, max_tokens: 350 }
    );
  };

  const isHim = recommendation?.protocolName?.toLowerCase().startsWith('him');
  const activePrompts = STAGE_PROMPTS[currentStage] ?? STAGE_PROMPTS.greeting;

  const scrollToSection = (id: string) => {
    const el = document.querySelector(id);
    if (el) el.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <section
      ref={sectionRef}
      id="hero"
      className="hero-section relative overflow-hidden"
      style={{ paddingTop: '100px', paddingBottom: '80px', background: '#0D0D0D', minHeight: '100vh' }}>

      {/* Background Video */}
      <div style={{ position: 'absolute', inset: 0, zIndex: 0, overflow: 'hidden', pointerEvents: 'none' }}>
        <iframe
          src="https://player.vimeo.com/video/1180466038?autoplay=1&muted=1&loop=1&background=1&autopause=0"
          allow="autoplay; fullscreen"
          style={{
            position: 'absolute', top: '50%', left: '50%',
            width: '177.78vh', minWidth: '100%', height: '56.25vw', minHeight: '100%',
            transform: 'translate(-50%, -50%)', opacity: 0.45, border: 'none'
          }}
          title="Hero background video" />
        <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(135deg, rgba(0,0,0,0.82) 0%, rgba(0,0,0,0.55) 50%, rgba(0,0,0,0.75) 100%)' }} />
        <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, height: '30%', background: 'linear-gradient(to top, #0D0D0D 0%, transparent 100%)' }} />
      </div>

      <div className="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10" style={{ position: 'relative', zIndex: 1 }}>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-start">

          {/* ── Left: Hero Content ─────────────────────────────────────────── */}
          <div className="lg:sticky lg:top-28">
            {/* Editorial Tag */}
            <div className="reveal-fade mb-8">
              <span className="editorial-tag">Trusted by 10,000+ patients across all 50 states</span>
            </div>

            {/* Main Headline */}
            <h1
              className="font-display font-bold reveal-fade reveal-delay-1 mb-3"
              style={{
                color: '#FFFFFF', fontSize: 'clamp(46px, 5.8vw, 72px)',
                lineHeight: '1.0', letterSpacing: '-0.03em', fontFamily: 'Cormorant Garamond, serif'
              }}>
Get a Personalized Hormone and Peptide Protocol for $49
            </h1>

            <h2
              className="font-display reveal-fade reveal-delay-1 mb-6"
              style={{
                color: 'rgba(255,255,255,0.85)', fontSize: 'clamp(22px, 2.8vw, 36px)',
                lineHeight: '1.2', letterSpacing: '-0.02em', fontFamily: 'Cormorant Garamond, serif', fontWeight: 400
              }}>
Reviewed by physicians, powered by AI, and fully credited toward your treatment plan
            </h2>

            {/* CTA Buttons */}
            <div className="reveal-fade reveal-delay-3 flex flex-col sm:flex-row gap-3">
              <button
                className="btn-gold"
                onClick={() => openIntakeModal()}
                style={{ height: '54px', minWidth: '200px', fontSize: '15px' }}>
                Start My Protocol — $49
              </button>
              <button
                className="btn-ghost-white"
                onClick={() => scrollToSection('#process')}
                style={{ height: '54px', minWidth: '150px', fontSize: '15px' }}>
                See How It Works
              </button>
            </div>

            {/* Trust micro-copy */}
            <p
              className="reveal-fade reveal-delay-4 mt-5"
              style={{ color: 'rgba(255,255,255,0.35)', fontSize: '13px', fontFamily: 'DM Sans, system-ui, sans-serif' }}>
              Results guaranteed or we make it right · Cancel anytime
            </p>

            {/* AI Concierge label — bridges hero to chat */}
            <div className="reveal-fade reveal-delay-4 mt-10 hidden lg:flex items-center gap-3">
              <div style={{ width: '1px', height: '32px', background: 'rgba(201,168,76,0.3)' }} />
              <div>
                <p style={{ color: 'rgba(255,255,255,0.4)', fontSize: '10px', letterSpacing: '0.12em', fontFamily: 'DM Sans, system-ui, sans-serif', textTransform: 'uppercase', marginBottom: '3px' }}>
                  AI Protocol Concierge
                </p>
                <p style={{ color: 'rgba(255,255,255,0.6)', fontSize: '13px', fontFamily: 'DM Sans, system-ui, sans-serif' }}>
                  Find your protocol in minutes →
                </p>
              </div>
            </div>
          </div>

          {/* ── Right: AI Chat Widget ──────────────────────────────────────── */}
          <div className="reveal-fade reveal-delay-2 flex flex-col gap-4">

            {/* Chat Window */}
            <div
              style={{
                background: 'rgba(13,13,13,0.75)',
                backdropFilter: 'blur(24px)',
                WebkitBackdropFilter: 'blur(24px)',
                borderRadius: '24px',
                border: '1px solid rgba(201,168,76,0.18)',
                boxShadow: '0 8px 60px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.04)',
                overflow: 'hidden',
                display: 'flex',
                flexDirection: 'column',
                minHeight: '520px',
                maxHeight: '600px',
              }}>

              {/* Chat Header */}
              <div
                className="flex items-center justify-between px-5 py-4"
                style={{ background: 'rgba(255,255,255,0.04)', borderBottom: '1px solid rgba(201,168,76,0.12)' }}>
                <div className="flex items-center gap-3">
                  <div
                    className="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"
                    style={{ background: 'rgba(119,157,124,0.2)', border: '1.5px solid rgba(119,157,124,0.45)' }}>
                    <svg width="16" height="16" viewBox="0 0 64 64" fill="none">
                      <circle cx="32" cy="32" r="22" stroke="#779D7C" strokeWidth="2" strokeDasharray="5 3" />
                      <circle cx="32" cy="32" r="10" fill="rgba(119,157,124,0.4)" stroke="#779D7C" strokeWidth="2" />
                    </svg>
                  </div>
                  <div>
                    <p style={{ color: '#FFFFFF', fontSize: '14px', fontWeight: 600, fontFamily: 'DM Sans, system-ui, sans-serif' }}>
                      ProtocolHRT AI Concierge
                    </p>
                    <div className="flex items-center gap-1.5">
                      <div className="w-1.5 h-1.5 rounded-full" style={{ background: isLoading ? '#F5A623' : '#779D7C', transition: 'background 0.3s' }} />
                      <p style={{ color: 'rgba(255,255,255,0.45)', fontSize: '11px', fontFamily: 'DM Sans, system-ui, sans-serif' }}>
                        {isLoading ? 'Thinking…' : 'Online · Powered by Claude'}
                      </p>
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <span style={{ color: 'rgba(201,168,76,0.6)', fontSize: '10px', letterSpacing: '0.1em', fontFamily: 'DM Sans, system-ui, sans-serif' }}>
                    PHYSICIAN-REVIEWED
                  </span>
                  {hasStarted && (
                    <button
                      onClick={handleReset}
                      style={{ color: 'rgba(255,255,255,0.3)', fontSize: '11px', fontFamily: 'DM Sans, system-ui, sans-serif', letterSpacing: '0.05em', background: 'none', border: 'none', cursor: 'pointer', padding: '4px 8px', borderRadius: '6px', transition: 'color 0.2s' }}
                      onMouseEnter={(e) => (e.currentTarget.style.color = 'rgba(255,255,255,0.7)')}
                      onMouseLeave={(e) => (e.currentTarget.style.color = 'rgba(255,255,255,0.3)')}>
                      RESTART
                    </button>
                  )}
                </div>
              </div>

              {/* Messages */}
              <div
                className="flex-1 overflow-y-auto px-5 py-5 space-y-4"
                style={{ scrollbarWidth: 'thin', scrollbarColor: 'rgba(255,255,255,0.08) transparent' }}>
                {messages.map((msg, i) => (
                  <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                    {msg.role === 'assistant' && (
                      <div
                        className="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mr-2 mt-0.5"
                        style={{ background: 'rgba(119,157,124,0.15)', border: '1.5px solid rgba(119,157,124,0.3)' }}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                          <circle cx="12" cy="12" r="9" stroke="#779D7C" strokeWidth="1.5" />
                          <circle cx="12" cy="12" r="4" fill="rgba(119,157,124,0.5)" />
                        </svg>
                      </div>
                    )}
                    <div style={{ maxWidth: '78%' }}>
                      <div
                        style={{
                          padding: '10px 14px',
                          borderRadius: msg.role === 'user' ? '16px 16px 4px 16px' : '16px 16px 16px 4px',
                          background: msg.role === 'user' ? 'rgba(201,168,76,0.15)' : 'rgba(255,255,255,0.07)',
                          border: msg.role === 'user' ? '1px solid rgba(201,168,76,0.25)' : '1px solid rgba(255,255,255,0.06)',
                          color: '#FFFFFF',
                          fontSize: '14px',
                          lineHeight: '1.7',
                          fontFamily: 'DM Sans, system-ui, sans-serif',
                        }}>
                        {msg.role === 'assistant' ? (
                          <div className="prose-chat">
                            <ReactMarkdown
                              components={{
                                p: ({ children }) => <p style={{ margin: '0 0 6px', lineHeight: '1.7', color: 'rgba(255,255,255,0.88)' }}>{children}</p>,
                                strong: ({ children }) => <strong style={{ color: '#FFFFFF', fontWeight: 700 }}>{children}</strong>,
                                ul: ({ children }) => <ul style={{ margin: '6px 0', paddingLeft: '16px' }}>{children}</ul>,
                                li: ({ children }) => <li style={{ marginBottom: '3px', lineHeight: '1.6', color: 'rgba(255,255,255,0.85)' }}>{children}</li>,
                              }}>
                              {msg.content}
                            </ReactMarkdown>
                          </div>
                        ) : (
                          <span>{msg.content}</span>
                        )}
                      </div>
                    </div>
                  </div>
                ))}

                {/* Streaming bubble */}
                {isLoading && (
                  <div className="flex justify-start">
                    <div
                      className="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mr-2 mt-0.5"
                      style={{ background: 'rgba(119,157,124,0.15)', border: '1.5px solid rgba(119,157,124,0.3)' }}>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="#779D7C" strokeWidth="1.5" />
                        <circle cx="12" cy="12" r="4" fill="rgba(119,157,124,0.5)" />
                      </svg>
                    </div>
                    <div
                      style={{
                        maxWidth: '78%', padding: '10px 14px',
                        borderRadius: '16px 16px 16px 4px',
                        background: 'rgba(255,255,255,0.07)',
                        border: '1px solid rgba(255,255,255,0.06)',
                        color: 'rgba(255,255,255,0.88)', fontSize: '14px', lineHeight: '1.7',
                        fontFamily: 'DM Sans, system-ui, sans-serif',
                      }}>
                      {streamingContent ? (
                        <div className="prose-chat">
                          <ReactMarkdown
                            components={{
                              p: ({ children }) => <p style={{ margin: '0 0 6px', lineHeight: '1.7', color: 'rgba(255,255,255,0.88)' }}>{children}</p>,
                              strong: ({ children }) => <strong style={{ color: '#FFFFFF', fontWeight: 700 }}>{children}</strong>,
                            }}>
                            {streamingContent}
                          </ReactMarkdown>
                        </div>
                      ) : (
                        <div className="flex items-center gap-1.5 py-1">
                          {[0, 1, 2].map((i) => (
                            <div key={i} className="w-2 h-2 rounded-full" style={{ background: '#779D7C', animation: `typingBounce 1.2s ease-in-out ${i * 0.2}s infinite` }} />
                          ))}
                        </div>
                      )}
                    </div>
                  </div>
                )}
                <div ref={messagesEndRef} />
              </div>

              {/* Quick prompts */}
              {!recommendation && (
                <div
                  className="px-5 pb-3 flex flex-wrap gap-2"
                  style={{ borderTop: messages.length > 1 ? '1px solid rgba(255,255,255,0.06)' : 'none', paddingTop: messages.length > 1 ? '10px' : '0' }}>
                  {activePrompts.map((prompt) => (
                    <button
                      key={prompt}
                      onClick={() => handleSend(prompt)}
                      disabled={isLoading}
                      style={{
                        padding: '5px 12px', borderRadius: '20px',
                        border: '1px solid rgba(201,168,76,0.3)',
                        background: 'rgba(201,168,76,0.07)',
                        color: 'rgba(255,255,255,0.75)',
                        fontSize: '12px', fontFamily: 'DM Sans, system-ui, sans-serif',
                        cursor: isLoading ? 'not-allowed' : 'pointer',
                        transition: 'all 0.2s', whiteSpace: 'nowrap', opacity: isLoading ? 0.5 : 1,
                      }}
                      onMouseEnter={(e) => { if (!isLoading) { e.currentTarget.style.background = 'rgba(201,168,76,0.18)'; e.currentTarget.style.borderColor = 'rgba(201,168,76,0.6)'; e.currentTarget.style.color = '#FFFFFF'; } }}
                      onMouseLeave={(e) => { e.currentTarget.style.background = 'rgba(201,168,76,0.07)'; e.currentTarget.style.borderColor = 'rgba(201,168,76,0.3)'; e.currentTarget.style.color = 'rgba(255,255,255,0.75)'; }}>
                      {prompt}
                    </button>
                  ))}
                </div>
              )}

              {/* Input Area */}
              <div className="px-4 py-4" style={{ borderTop: '1px solid rgba(255,255,255,0.06)', background: 'rgba(0,0,0,0.2)' }}>
                <div
                  className="flex items-center gap-3"
                  style={{
                    background: 'rgba(255,255,255,0.06)', border: '1.5px solid rgba(255,255,255,0.1)',
                    borderRadius: '14px', padding: '8px 8px 8px 14px', transition: 'border-color 0.2s',
                  }}
                  onFocusCapture={(e) => (e.currentTarget.style.borderColor = 'rgba(201,168,76,0.5)')}
                  onBlurCapture={(e) => (e.currentTarget.style.borderColor = 'rgba(255,255,255,0.1)')}>
                  <input
                    ref={inputRef}
                    type="text"
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder={recommendation ? 'Ask a follow-up question…' : 'Describe your symptoms or goals…'}
                    disabled={isLoading}
                    style={{
                      flex: 1, border: 'none', outline: 'none', background: 'transparent',
                      color: '#FFFFFF', fontSize: '14px', fontFamily: 'DM Sans, system-ui, sans-serif',
                    }} />
                  <button
                    onClick={() => handleSend()}
                    disabled={isLoading || !input.trim()}
                    aria-label="Send message to AI concierge"
                    style={{
                      width: '36px', height: '36px', borderRadius: '10px',
                      background: isLoading || !input.trim() ? 'rgba(119,157,124,0.25)' : '#779D7C',
                      border: 'none', cursor: isLoading || !input.trim() ? 'not-allowed' : 'pointer',
                      display: 'flex', alignItems: 'center', justifyContent: 'center',
                      flexShrink: 0, transition: 'background 0.2s',
                    }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                      <path d="M22 2L11 13" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                      <path d="M22 2L15 22l-4-9-9-4 20-7z" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                  </button>
                </div>
                <p className="text-center mt-2" style={{ color: 'rgba(255,255,255,0.25)', fontSize: '10px', fontFamily: 'DM Sans, system-ui, sans-serif', letterSpacing: '0.05em' }}>
                  AI responses are informational only · All protocols are physician-reviewed
                </p>
              </div>
            </div>

            {/* ── Protocol Recommendation Card ─────────────────────────────── */}
            {(isFetchingCard || recommendation) && (
              <div
                ref={cardRef}
                style={{
                  opacity: showContactForm || cardVisible ? 1 : 0,
                  transform: showContactForm || cardVisible ? 'translateY(0)' : 'translateY(16px)',
                  transition: 'opacity 0.5s ease, transform 0.5s ease',
                  borderRadius: '20px', overflow: 'hidden',
                  border: '1px solid rgba(201,168,76,0.2)',
                  boxShadow: '0 8px 40px rgba(0,0,0,0.4)',
                  background: 'rgba(13,13,13,0.85)',
                  backdropFilter: 'blur(20px)',
                  WebkitBackdropFilter: 'blur(20px)',
                }}>
                {showContactForm ? (
                  <div style={{ padding: '28px' }}>
                    <div className="flex items-center gap-3 mb-6">
                      <div style={{ width: 44, height: 44, borderRadius: '50%', background: 'rgba(119,157,124,0.15)', border: '1.5px solid rgba(119,157,124,0.35)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="#779D7C" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                          <circle cx="12" cy="7" r="4" stroke="#779D7C" strokeWidth="1.5" />
                        </svg>
                      </div>
                      <div>
                        <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '16px', fontWeight: 700, color: '#FFFFFF', marginBottom: '3px' }}>Your protocol is ready</p>
                        <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#779D7C' }}>Enter your details to unlock your personalized recommendation</p>
                      </div>
                    </div>
                    <form onSubmit={handleContactSubmit} noValidate>
                      <div style={{ marginBottom: '14px' }}>
                        <label style={darkLabelStyle}>First Name</label>
                        <input type="text" value={contactInfo.name} onChange={(e) => { setContactInfo((p) => ({ ...p, name: e.target.value })); if (contactErrors.name) setContactErrors((p) => ({ ...p, name: undefined })); }} placeholder="Your first name" style={darkInputStyle(!!contactErrors.name)} onFocus={(e) => { if (!contactErrors.name) e.currentTarget.style.borderColor = '#779D7C'; }} onBlur={(e) => { if (!contactErrors.name) e.currentTarget.style.borderColor = 'rgba(255,255,255,0.12)'; }} />
                        {contactErrors.name && <p style={errorStyle}>{contactErrors.name}</p>}
                      </div>
                      <div style={{ marginBottom: '14px' }}>
                        <label style={darkLabelStyle}>Email Address</label>
                        <input type="email" value={contactInfo.email} onChange={(e) => { setContactInfo((p) => ({ ...p, email: e.target.value })); if (contactErrors.email) setContactErrors((p) => ({ ...p, email: undefined })); }} placeholder="you@example.com" style={darkInputStyle(!!contactErrors.email)} onFocus={(e) => { if (!contactErrors.email) e.currentTarget.style.borderColor = '#779D7C'; }} onBlur={(e) => { if (!contactErrors.email) e.currentTarget.style.borderColor = 'rgba(255,255,255,0.12)'; }} />
                        {contactErrors.email && <p style={errorStyle}>{contactErrors.email}</p>}
                      </div>
                      <button type="submit" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', width: '100%', padding: '14px 20px', borderRadius: '12px', background: '#779D7C', color: '#FFFFFF', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', fontWeight: 600, letterSpacing: '0.06em', border: 'none', cursor: 'pointer', textTransform: 'uppercase', transition: 'opacity 0.2s, transform 0.2s' }} onMouseEnter={(e) => { e.currentTarget.style.opacity = '0.88'; e.currentTarget.style.transform = 'translateY(-1px)'; }} onMouseLeave={(e) => { e.currentTarget.style.opacity = '1'; e.currentTarget.style.transform = 'translateY(0)'; }}>
                        View My Protocol
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M12 5l7 7-7 7" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" /></svg>
                      </button>
                      <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: 'rgba(255,255,255,0.25)', textAlign: 'center', marginTop: '10px', letterSpacing: '0.04em' }}>Your information is private and secure · No spam, ever</p>
                    </form>
                  </div>
                ) : isFetchingCard && !recommendation ? (
                  <div style={{ padding: '28px' }}>
                    <div className="flex items-center gap-3 mb-5">
                      <div style={{ width: 40, height: 40, borderRadius: '50%', background: 'rgba(119,157,124,0.15)', animation: 'skeletonPulse 1.5s ease-in-out infinite' }} />
                      <div style={{ flex: 1 }}>
                        <div style={{ width: '65%', height: 14, borderRadius: 6, background: 'rgba(255,255,255,0.08)', marginBottom: 8, animation: 'skeletonPulse 1.5s ease-in-out infinite' }} />
                        <div style={{ width: '40%', height: 10, borderRadius: 6, background: 'rgba(255,255,255,0.05)', animation: 'skeletonPulse 1.5s ease-in-out 0.2s infinite' }} />
                      </div>
                    </div>
                    {[80, 60, 70].map((w, i) => (
                      <div key={i} style={{ width: `${w}%`, height: 10, borderRadius: 6, background: 'rgba(255,255,255,0.06)', marginBottom: 8, animation: `skeletonPulse 1.5s ease-in-out ${i * 0.15}s infinite` }} />
                    ))}
                    <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#779D7C', marginTop: '16px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                      <span style={{ animation: 'typingBounce 1s ease-in-out infinite' }}>●</span>
                      Generating your protocol recommendation…
                    </p>
                  </div>
                ) : recommendation ? (
                  <>
                    <div style={{ background: isHim ? 'linear-gradient(135deg, #38312C 0%, #4A4038 100%)' : 'linear-gradient(135deg, #3D5C42 0%, #4E7355 100%)', padding: '24px 28px 22px' }}>
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <span style={{ display: 'inline-block', fontSize: '10px', letterSpacing: '0.12em', fontFamily: 'DM Sans, system-ui, sans-serif', fontWeight: 600, color: 'rgba(255,255,255,0.5)', textTransform: 'uppercase', marginBottom: '8px' }}>Your Recommended Protocol</span>
                          <h3 style={{ fontFamily: 'Cormorant Garamond, serif', fontSize: '24px', fontWeight: 700, color: '#FFFFFF', lineHeight: 1.15, letterSpacing: '-0.01em', marginBottom: '8px' }}>{recommendation.protocolName}</h3>
                          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: 'rgba(255,255,255,0.65)', lineHeight: 1.55 }}>{recommendation.tagline}</p>
                        </div>
                        <div style={{ width: 48, height: 48, borderRadius: '50%', background: 'rgba(255,255,255,0.1)', border: '1.5px solid rgba(255,255,255,0.2)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                          <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                            {isHim ? (<><circle cx="12" cy="8" r="4" stroke="white" strokeWidth="1.5" /><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" /></>) : (<><circle cx="12" cy="10" r="4" stroke="white" strokeWidth="1.5" /><path d="M12 14v6M9 17h6" stroke="white" strokeWidth="1.5" strokeLinecap="round" /></>)}
                          </svg>
                        </div>
                      </div>
                    </div>
                    <div style={{ padding: '22px 28px 26px' }}>
                      <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', letterSpacing: '0.1em', color: '#779D7C', fontWeight: 600, textTransform: 'uppercase', marginBottom: '14px' }}>Key Benefits</p>
                      <div className="space-y-3 mb-6">
                        {recommendation.benefits.map((benefit, i) => (
                          <div key={i} className="flex items-center gap-3">
                            <div style={{ width: 22, height: 22, borderRadius: '50%', background: 'rgba(119,157,124,0.12)', border: '1.5px solid rgba(119,157,124,0.35)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                              <svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="#779D7C" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" /><circle cx="12" cy="12" r="9" stroke="#779D7C" strokeWidth="1.5" /></svg>
                            </div>
                            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', color: 'rgba(255,255,255,0.85)', lineHeight: 1.5 }}>{benefit}</p>
                          </div>
                        ))}
                      </div>
                      <div className="flex items-center gap-2 mb-5" style={{ padding: '10px 14px', borderRadius: '10px', background: 'rgba(119,157,124,0.08)', border: '1px solid rgba(119,157,124,0.2)' }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="#779D7C" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" /><circle cx="12" cy="12" r="9" stroke="#779D7C" strokeWidth="1.5" /></svg>
                        <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: 'rgba(255,255,255,0.65)' }}>Physician-reviewed before every delivery</p>
                      </div>
                      <a href={recommendation.ctaHref} style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', width: '100%', padding: '14px 20px', borderRadius: '12px', background: isHim ? '#C9A84C' : '#779D7C', color: '#FFFFFF', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', fontWeight: 600, letterSpacing: '0.06em', textDecoration: 'none', transition: 'opacity 0.2s, transform 0.2s', textTransform: 'uppercase' }} onMouseEnter={(e) => { e.currentTarget.style.opacity = '0.88'; e.currentTarget.style.transform = 'translateY(-1px)'; }} onMouseLeave={(e) => { e.currentTarget.style.opacity = '1'; e.currentTarget.style.transform = 'translateY(0)'; }}>
                        {recommendation.ctaText}
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M12 5l7 7-7 7" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" /></svg>
                      </a>
                      <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: 'rgba(255,255,255,0.25)', textAlign: 'center', marginTop: '10px', letterSpacing: '0.04em' }}>No commitment required · Cancel anytime</p>
                    </div>
                  </>
                ) : null}
              </div>
            )}
          </div>
        </div>
      </div>

      <style jsx>{`
        @keyframes typingBounce {
          0%, 60%, 100% { transform: translateY(0); }
          30% { transform: translateY(-6px); }
        }
        @keyframes skeletonPulse {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.35; }
        }
        .prose-chat p:last-child { margin-bottom: 0; }
      `}</style>
    </section>
  );
}
