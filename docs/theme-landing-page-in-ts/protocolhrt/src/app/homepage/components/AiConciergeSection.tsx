'use client';
import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useChat } from '@/lib/hooks/useChat';
import toast from 'react-hot-toast';
import ReactMarkdown from 'react-markdown';

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

type IntakeStage =
  | 'greeting' |'symptoms' |'demographics' |'history' |'recommendation' |'complete';

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

Stage 2 — SYMPTOMS: Explore 2–3 key symptoms. Ask about: energy levels, sleep quality, libido/sexual health, mood/anxiety, weight/body composition, cognitive function, recovery. Ask ONE question at a time.

Stage 3 — DEMOGRAPHICS: Ask their age and biological sex (to determine HIM vs HER). Keep it natural and conversational.

Stage 4 — HISTORY: Ask ONE brief question about existing conditions or current medications/supplements. Keep it light.

Stage 5 — RECOMMENDATION: Based on all gathered info, deliver a clear, personalized protocol recommendation. Structure it as:
  - Name the specific protocol
  - Explain WHY it fits their profile (2–3 sentences)
  - List 3 key expected outcomes
  - Note that all protocols are physician-reviewed before delivery
  - End with: "I've prepared your personalized protocol summary below."

━━━ TONE & RULES ━━━
• Warm, professional, empathetic — like a trusted health advisor
• Keep each response to 2–4 sentences max
• Ask ONLY one question per turn
• Use plain language — minimal medical jargon
• NEVER provide specific dosing information
• NEVER diagnose — you are an intake specialist, not a physician
• Always note physician review before delivery
• If user seems hesitant, reassure them this is a no-commitment consultation`;

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
  content:
    "Hi, I'm your ProtocolHRT Concierge. I'll help you find the right hormone or peptide plan based on your goals, symptoms, and biology.\n\nLet's start simple—what would you like to improve most right now?",
  timestamp: Date.now(),
};

// Stage-specific quick prompts
const STAGE_PROMPTS: Record<string, string[]> = {
  greeting: [
    'Low energy & fatigue',
    'Hormone balance',
    'Burn Fat',
    'Build Muscle',
    'Better sleep & mood',
    'Sexual Health',
    'Anti-aging & longevity',
  ],
  symptoms: [
    'Yes, my sleep is poor',
    'My libido has dropped',
    'I feel foggy & unfocused',
    'I\'ve gained weight recently',
    'I feel anxious or moody',
    'My recovery is slow',
  ],
  demographics: [
    'I\'m in my 30s',
    'I\'m in my 40s',
    'I\'m in my 50s',
    'I\'m in my 60s+',
  ],
  history: [
    'No existing conditions',
    'I take some supplements',
    'I have thyroid issues',
    'I\'m on medication',
  ],
};

const RECOMMENDATION_KEYWORDS = [
  'recommend',
  'protocol for you',
  'best fit',
  'ideal protocol',
  'him protocol',
  'her protocol',
  'trt protocol',
  'hormone balance protocol',
  'weight loss protocol',
  'peptide protocol',
  'longevity protocol',
  'body composition protocol',
  'metabolic protocol',
  'cognitive protocol',
  'thyroid',
  'based on what you',
  'based on your',
  'suggest the',
  'prepared your personalized',
  'protocol summary below',
  'physician-reviewed before delivery',
  'fits your profile',
  'your profile',
];

// ─── Helpers ──────────────────────────────────────────────────────────────────

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

function formatTime(ts: number): string {
  return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function AiConciergeSection() {
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
  const [showTimestamps, setShowTimestamps] = useState(false);
  const [isTyping, setIsTyping] = useState(false);

  const { response, isLoading, error, sendMessage } = useChat(
    'ANTHROPIC',
    'claude-sonnet-4-5-20250929',
    true
  );
  const {
    response: summaryResponse,
    isLoading: summaryLoading,
    error: summaryError,
    sendMessage: sendSummary,
  } = useChat('ANTHROPIC', 'claude-sonnet-4-5-20250929', false);

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
          if (parsed.recommendation) {
            setRecommendation(parsed.recommendation);
            setCardVisible(true);
          }
          if (parsed.contactSubmitted) setContactSubmitted(true);
        }
      }
    } catch {
      // ignore
    }
  }, []);

  useEffect(() => {
    if (!hasStarted) return;
    try {
      localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({ messages, recommendation, contactSubmitted })
      );
    } catch {
      // ignore
    }
  }, [messages, recommendation, contactSubmitted, hasStarted]);

  // ── Error handling ───────────────────────────────────────────────────────────
  useEffect(() => {
    if (error) toast.error('Connection issue, please try again.');
  }, [error]);

  useEffect(() => {
    if (summaryError) console.warn('Summary fetch failed silently');
  }, [summaryError]);

  // ── Streaming response handler ───────────────────────────────────────────────
  useEffect(() => {
    if (isLoading && response) {
      setStreamingContent(response);
      setIsTyping(false);
    }
    if (!isLoading && response && streamingContent) {
      const newMsg: Message = {
        role: 'assistant',
        content: response,
        timestamp: Date.now(),
      };
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

  // ── Typing indicator ─────────────────────────────────────────────────────────
  useEffect(() => {
    if (isLoading && !response) {
      setIsTyping(true);
    } else {
      setIsTyping(false);
    }
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
            setTimeout(() => {
              cardRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 200);
          }, 500);
        }
      } catch {
        setIsFetchingCard(false);
      }
    }
  }, [summaryResponse, summaryLoading]);

  // ── Auto-scroll ──────────────────────────────────────────────────────────────
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, streamingContent, isTyping]);

  // ── Intersection observer for reveal animations ──────────────────────────────
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) entry.target.classList.add('is-visible');
        });
      },
      { threshold: 0.1, rootMargin: '0px 0px -60px 0px' }
    );
    sectionRef.current?.querySelectorAll('.reveal-fade').forEach((el) => observer.observe(el));
    return () => observer.disconnect();
  }, []);

  // ── Helpers ──────────────────────────────────────────────────────────────────
  const buildApiMessages = useCallback(
    (newUserMessage: string) => {
      const history = messages.map((m) => ({ role: m.role, content: m.content }));
      return [
        { role: 'system' as const, content: SYSTEM_PROMPT },
        ...history,
        { role: 'user' as const, content: newUserMessage },
      ];
    },
    [messages]
  );

  const handleSend = useCallback(
    (text?: string) => {
      const userText = (text ?? input).trim();
      if (!userText || isLoading) return;

      if (!hasStarted) setHasStarted(true);
      const userMsg: Message = { role: 'user', content: userText, timestamp: Date.now() };
      setMessages((prev) => [...prev, userMsg]);
      setInput('');
      sendMessage(buildApiMessages(userText), { temperature: 0.72, max_tokens: 700 });
      inputRef.current?.focus();
    },
    [input, isLoading, hasStarted, buildApiMessages, sendMessage]
  );

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  const handleReset = () => {
    setMessages([INITIAL_MESSAGE]);
    setInput('');
    setStreamingContent('');
    setHasStarted(false);
    setRecommendation(null);
    setCardVisible(false);
    setIsFetchingCard(false);
    setContactInfo({ name: '', email: '' });
    setContactSubmitted(false);
    setShowContactForm(false);
    setContactErrors({});
    setCurrentStage('greeting');
    setIsTyping(false);
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch {
      // ignore
    }
  };

  const validateContact = (): boolean => {
    const errors: Partial<ContactInfo> = {};
    if (!contactInfo.name.trim()) errors.name = 'Name is required';
    if (!contactInfo.email.trim()) {
      errors.email = 'Email is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactInfo.email)) {
      errors.email = 'Please enter a valid email';
    }
    setContactErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleContactSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateContact()) return;
    setContactSubmitted(true);
    setShowContactForm(false);
    const conversationText = messages
      .map((m) => `${m.role === 'user' ? 'User' : 'AI Concierge'}: ${m.content}`)
      .join('\n\n');
    sendSummary(
      [
        { role: 'system', content: SUMMARY_SYSTEM_PROMPT },
        {
          role: 'user',
          content: `Conversation:\n\n${conversationText}\n\nGenerate the JSON recommendation card.`,
        },
      ],
      { temperature: 0.15, max_tokens: 350 }
    );
  };

  // ── Derived state ─────────────────────────────────────────────────────────────
  const isHim = recommendation?.protocolName?.toLowerCase().startsWith('him');
  const activePrompts = STAGE_PROMPTS[currentStage] ?? STAGE_PROMPTS.greeting;

  const stageLabels: Record<IntakeStage, string> = {
    greeting: 'Getting Started',
    symptoms: 'Exploring Symptoms',
    demographics: 'Your Profile',
    history: 'Health History',
    recommendation: 'Protocol Match',
    complete: 'Complete',
  };

  const stageOrder: IntakeStage[] = [
    'greeting',
    'symptoms',
    'demographics',
    'history',
    'recommendation',
  ];
  const stageIndex = stageOrder.indexOf(currentStage);

  // ─── Render ────────────────────────────────────────────────────────────────

  return (
    <section
      id="ai-concierge"
      ref={sectionRef}
      className="py-24 lg:py-32 px-4 sm:px-6 lg:px-8"
      style={{ background: '#F8F6F2', borderTop: '1px solid rgba(56,49,44,0.06)' }}
    >
      <div className="max-w-7xl mx-auto">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-start">

          {/* ── Left: Content Panel ─────────────────────────────────────────── */}
          <div className="lg:sticky lg:top-28">
            <div className="reveal-fade mb-5">
              <span className="section-label">02 / AI Protocol Concierge</span>
            </div>

            <h2
              className="font-display font-bold reveal-fade reveal-delay-1 mb-5"
              style={{
                color: '#38312C',
                fontSize: 'clamp(34px, 5vw, 52px)',
                lineHeight: '1.05',
                letterSpacing: '-0.01em',
                fontFamily: 'Cormorant Garamond, serif',
              }}
            >
              Find your protocol in{' '}
              <span style={{ color: '#779D7C' }}>minutes, not months.</span>
            </h2>

            <p
              className="font-body reveal-fade reveal-delay-2 mb-8"
              style={{
                color: '#5C5248',
                fontSize: '17px',
                lineHeight: '1.75',
                fontFamily: 'Red Hat Text, sans-serif',
              }}
            >
              We ask the right questions, listen to what your body is telling us, and design a{' '}
              <strong style={{ color: '#38312C' }}>HIM or HER protocol</strong> tailored to you.
              Every recommendation is carefully reviewed by a licensed physician before you begin.
            </p>

            {/* Feature list */}
            <div className="space-y-3 reveal-fade reveal-delay-3 mb-8">
              {[
                'Conversational intake, no forms, no waiting',
                'Covers hormones, peptides, weight loss & longevity',
                'Personalized to your age, goals & symptoms',
                'Every recommendation physician-reviewed',
                'Available 24/7, start right now',
              ].map((feat) => (
                <div key={feat} className="flex items-start gap-3">
                  <div className="check-icon mt-0.5 flex-shrink-0">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="3">
                      <path d="M20 6L9 17l-5-5" />
                    </svg>
                  </div>
                  <p
                    className="font-body"
                    style={{
                      color: '#5C5248',
                      fontSize: '15px',
                      lineHeight: '1.6',
                      fontFamily: 'Red Hat Text, sans-serif',
                    }}
                  >
                    {feat}
                  </p>
                </div>
              ))}
            </div>

            {/* Intake progress tracker */}
            {hasStarted && (
              <div
                className="reveal-fade mb-8 p-4"
                style={{
                  background: '#FFFFFF',
                  borderRadius: '16px',
                  border: '1px solid rgba(56,49,44,0.08)',
                }}
              >
                <p
                  style={{
                    fontFamily: 'Red Hat Text, sans-serif',
                    fontSize: '10px',
                    letterSpacing: '0.1em',
                    color: '#779D7C',
                    fontWeight: 600,
                    textTransform: 'uppercase' as const,
                    marginBottom: '12px',
                  }}
                >
                  Intake Progress
                </p>
                <div className="flex items-center gap-1.5">
                  {stageOrder.map((stage, i) => (
                    <React.Fragment key={stage}>
                      <div
                        style={{
                          flex: 1,
                          height: '4px',
                          borderRadius: '2px',
                          background:
                            i <= stageIndex
                              ? '#779D7C' :'rgba(119,157,124,0.2)',
                          transition: 'background 0.4s ease',
                        }}
                      />
                    </React.Fragment>
                  ))}
                </div>
                <p
                  style={{
                    fontFamily: 'Red Hat Text, sans-serif',
                    fontSize: '11px',
                    color: '#5C5248',
                    marginTop: '8px',
                  }}
                >
                  {stageLabels[currentStage]}
                  {currentStage === 'recommendation' && ' — Protocol identified ✓'}
                </p>
              </div>
            )}

            {/* Powered by badge */}
            <div className="reveal-fade reveal-delay-4 flex items-center gap-2">
              <div
                className="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0"
                style={{
                  background: 'rgba(119,157,124,0.15)',
                  border: '1.5px solid rgba(119,157,124,0.4)',
                }}
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <circle cx="12" cy="12" r="10" stroke="#779D7C" strokeWidth="1.5" />
                  <path
                    d="M8 12l2.5 2.5L16 9"
                    stroke="#779D7C"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </div>
              <p
                style={{
                  color: '#779D7C',
                  fontSize: '11px',
                  letterSpacing: '0.1em',
                  fontFamily: 'Red Hat Text, sans-serif',
                }}
              >
                POWERED BY ANTHROPIC CLAUDE · PHYSICIAN-REVIEWED · HIPAA-AWARE
              </p>
            </div>
          </div>

          {/* ── Right: Chat + Card ──────────────────────────────────────────── */}
          <div className="reveal-fade reveal-delay-2 flex flex-col gap-5">

            {/* Chat Window */}
            <div
              style={{
                background: '#FFFFFF',
                borderRadius: '24px',
                border: '1px solid rgba(56,49,44,0.1)',
                boxShadow: '0 8px 40px rgba(56,49,44,0.08)',
                overflow: 'hidden',
                display: 'flex',
                flexDirection: 'column',
                minHeight: '540px',
                maxHeight: '640px',
              }}
            >
              {/* Chat Header */}
              <div
                className="flex items-center justify-between px-5 py-4"
                style={{
                  background: '#38312C',
                  borderBottom: '1px solid rgba(255,255,255,0.06)',
                }}
              >
                <div className="flex items-center gap-3">
                  <div
                    className="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"
                    style={{
                      background: 'rgba(119,157,124,0.25)',
                      border: '1.5px solid rgba(119,157,124,0.5)',
                    }}
                  >
                    <svg width="16" height="16" viewBox="0 0 64 64" fill="none">
                      <circle
                        cx="32"
                        cy="32"
                        r="22"
                        stroke="#779D7C"
                        strokeWidth="2"
                        strokeDasharray="5 3"
                      />
                      <circle
                        cx="32"
                        cy="32"
                        r="10"
                        fill="rgba(119,157,124,0.4)"
                        stroke="#779D7C"
                        strokeWidth="2"
                      />
                    </svg>
                  </div>
                  <div>
                    <p
                      style={{
                        color: '#FFFFFF',
                        fontSize: '14px',
                        fontWeight: 600,
                        fontFamily: 'Red Hat Text, sans-serif',
                      }}
                    >
                      ProtocolHRT AI Concierge
                    </p>
                    <div className="flex items-center gap-1.5">
                      <div
                        className="w-1.5 h-1.5 rounded-full"
                        style={{
                          background: isLoading ? '#F5A623' : '#779D7C',
                          transition: 'background 0.3s',
                        }}
                      />
                      <p
                        style={{
                          color: 'rgba(255,255,255,0.5)',
                          fontSize: '11px',
                          fontFamily: 'Red Hat Text, sans-serif',
                        }}
                      >
                        {isLoading ? 'Thinking…' : 'Online · Powered by Claude'}
                      </p>
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-3">
                  {/* Timestamp toggle */}
                  <button
                    onClick={() => setShowTimestamps((v) => !v)}
                    title="Toggle timestamps"
                    style={{
                      color: showTimestamps
                        ? 'rgba(255,255,255,0.7)'
                        : 'rgba(255,255,255,0.3)',
                      background: 'none',
                      border: 'none',
                      cursor: 'pointer',
                      padding: '4px',
                      transition: 'color 0.2s',
                    }}
                  >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
                      <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.5" />
                      <path
                        d="M12 7v5l3 3"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeLinecap="round"
                      />
                    </svg>
                  </button>

                  {hasStarted && (
                    <button
                      onClick={handleReset}
                      style={{
                        color: 'rgba(255,255,255,0.35)',
                        fontSize: '11px',
                        fontFamily: 'Red Hat Text, sans-serif',
                        letterSpacing: '0.05em',
                        background: 'none',
                        border: 'none',
                        cursor: 'pointer',
                        padding: '4px 8px',
                        borderRadius: '6px',
                        transition: 'color 0.2s',
                      }}
                      onMouseEnter={(e) =>
                        (e.currentTarget.style.color = 'rgba(255,255,255,0.8)')
                      }
                      onMouseLeave={(e) =>
                        (e.currentTarget.style.color = 'rgba(255,255,255,0.35)')
                      }
                    >
                      RESTART
                    </button>
                  )}
                </div>
              </div>

              {/* Messages */}
              <div
                className="flex-1 overflow-y-auto px-5 py-5 space-y-4"
                style={{ scrollbarWidth: 'thin', scrollbarColor: 'rgba(56,49,44,0.1) transparent' }}
              >
                {messages.map((msg, i) => (
                  <div
                    key={i}
                    className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
                  >
                    {msg.role === 'assistant' && (
                      <div
                        className="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mr-2 mt-0.5"
                        style={{
                          background: 'rgba(119,157,124,0.15)',
                          border: '1.5px solid rgba(119,157,124,0.3)',
                        }}
                      >
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
                          borderRadius:
                            msg.role === 'user' ?'16px 16px 4px 16px' :'16px 16px 16px 4px',
                          background: msg.role === 'user' ? '#38312C' : '#F8F6F2',
                          color: msg.role === 'user' ? '#FFFFFF' : '#38312C',
                          fontSize: '14px',
                          lineHeight: '1.7',
                          fontFamily: 'Red Hat Text, sans-serif',
                        }}
                      >
                        {msg.role === 'assistant' ? (
                          <div className="prose-chat">
                            <ReactMarkdown
                              components={{
                                p: ({ children }) => (
                                  <p style={{ margin: '0 0 6px', lineHeight: '1.7' }}>{children}</p>
                                ),
                                strong: ({ children }) => (
                                  <strong style={{ color: '#38312C', fontWeight: 700 }}>
                                    {children}
                                  </strong>
                                ),
                                ul: ({ children }) => (
                                  <ul style={{ margin: '6px 0', paddingLeft: '16px' }}>{children}</ul>
                                ),
                                li: ({ children }) => (
                                  <li style={{ marginBottom: '3px', lineHeight: '1.6' }}>{children}</li>
                                ),
                              }}
                            >
                              {msg.content}
                            </ReactMarkdown>
                          </div>
                        ) : (
                          <span>{msg.content}</span>
                        )}
                      </div>
                      {showTimestamps && (
                        <p
                          style={{
                            fontFamily: 'Red Hat Text, sans-serif',
                            fontSize: '10px',
                            color: 'rgba(92,82,72,0.4)',
                            marginTop: '3px',
                            textAlign: msg.role === 'user' ? 'right' : 'left',
                          }}
                        >
                          {formatTime(msg.timestamp)}
                        </p>
                      )}
                    </div>
                  </div>
                ))}

                {/* Streaming bubble */}
                {isLoading && (
                  <div className="flex justify-start">
                    <div
                      className="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mr-2 mt-0.5"
                      style={{
                        background: 'rgba(119,157,124,0.15)',
                        border: '1.5px solid rgba(119,157,124,0.3)',
                      }}
                    >
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="#779D7C" strokeWidth="1.5" />
                        <circle cx="12" cy="12" r="4" fill="rgba(119,157,124,0.5)" />
                      </svg>
                    </div>
                    <div
                      style={{
                        maxWidth: '78%',
                        padding: '10px 14px',
                        borderRadius: '16px 16px 16px 4px',
                        background: '#F8F6F2',
                        color: '#38312C',
                        fontSize: '14px',
                        lineHeight: '1.7',
                        fontFamily: 'Red Hat Text, sans-serif',
                      }}
                    >
                      {streamingContent ? (
                        <div className="prose-chat">
                          <ReactMarkdown
                            components={{
                              p: ({ children }) => (
                                <p style={{ margin: '0 0 6px', lineHeight: '1.7' }}>{children}</p>
                              ),
                              strong: ({ children }) => (
                                <strong style={{ color: '#38312C', fontWeight: 700 }}>
                                  {children}
                                </strong>
                              ),
                            }}
                          >
                            {streamingContent}
                          </ReactMarkdown>
                        </div>
                      ) : (
                        /* Typing dots */
                        <div className="flex items-center gap-1.5 py-1">
                          {[0, 1, 2].map((i) => (
                            <div
                              key={i}
                              className="w-2 h-2 rounded-full"
                              style={{
                                background: '#779D7C',
                                animation: `typingBounce 1.2s ease-in-out ${i * 0.2}s infinite`,
                              }}
                            />
                          ))}
                        </div>
                      )}
                    </div>
                  </div>
                )}

                <div ref={messagesEndRef} />
              </div>

              {/* Contextual quick prompts */}
              {!recommendation && (
                <div
                  className="px-5 pb-3 flex flex-wrap gap-2"
                  style={{ borderTop: messages.length > 1 ? '1px solid rgba(56,49,44,0.05)' : 'none', paddingTop: messages.length > 1 ? '10px' : '0' }}
                >
                  {activePrompts.map((prompt) => (
                    <button
                      key={prompt}
                      onClick={() => handleSend(prompt)}
                      disabled={isLoading}
                      style={{
                        padding: '5px 12px',
                        borderRadius: '20px',
                        border: '1px solid rgba(119,157,124,0.4)',
                        background: 'rgba(119,157,124,0.06)',
                        color: '#5C5248',
                        fontSize: '12px',
                        fontFamily: 'Red Hat Text, sans-serif',
                        cursor: isLoading ? 'not-allowed' : 'pointer',
                        transition: 'all 0.2s',
                        whiteSpace: 'nowrap',
                        opacity: isLoading ? 0.5 : 1,
                      }}
                      onMouseEnter={(e) => {
                        if (!isLoading) {
                          e.currentTarget.style.background = 'rgba(119,157,124,0.15)';
                          e.currentTarget.style.borderColor = '#779D7C';
                        }
                      }}
                      onMouseLeave={(e) => {
                        e.currentTarget.style.background = 'rgba(119,157,124,0.06)';
                        e.currentTarget.style.borderColor = 'rgba(119,157,124,0.4)';
                      }}
                    >
                      {prompt}
                    </button>
                  ))}
                </div>
              )}

              {/* Input Area */}
              <div
                className="px-4 py-4"
                style={{
                  borderTop: '1px solid rgba(56,49,44,0.08)',
                  background: '#FAFAF8',
                }}
              >
                <div
                  className="flex items-center gap-3"
                  style={{
                    background: '#FFFFFF',
                    border: '1.5px solid rgba(56,49,44,0.12)',
                    borderRadius: '14px',
                    padding: '8px 8px 8px 14px',
                    transition: 'border-color 0.2s',
                  }}
                  onFocusCapture={(e) => (e.currentTarget.style.borderColor = '#779D7C')}
                  onBlurCapture={(e) =>
                    (e.currentTarget.style.borderColor = 'rgba(56,49,44,0.12)')
                  }
                >
                  <input
                    ref={inputRef}
                    type="text"
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder={
                      recommendation
                        ? 'Ask a follow-up question…' :'Describe your symptoms or goals…'
                    }
                    disabled={isLoading}
                    style={{
                      flex: 1,
                      border: 'none',
                      outline: 'none',
                      background: 'transparent',
                      color: '#38312C',
                      fontSize: '14px',
                      fontFamily: 'Red Hat Text, sans-serif',
                    }}
                  />
                  <button
                    onClick={() => handleSend()}
                    disabled={isLoading || !input.trim()}
                    style={{
                      width: '36px',
                      height: '36px',
                      borderRadius: '10px',
                      background:
                        isLoading || !input.trim()
                          ? 'rgba(119,157,124,0.3)'
                          : '#779D7C',
                      border: 'none',
                      cursor: isLoading || !input.trim() ? 'not-allowed' : 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0,
                      transition: 'background 0.2s',
                    }}
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                      <path
                        d="M22 2L11 13"
                        stroke="white"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      />
                      <path
                        d="M22 2L15 22l-4-9-9-4 20-7z"
                        stroke="white"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      />
                    </svg>
                  </button>
                </div>
                <p
                  className="text-center mt-2"
                  style={{
                    color: 'rgba(92,82,72,0.4)',
                    fontSize: '10px',
                    fontFamily: 'Red Hat Text, sans-serif',
                    letterSpacing: '0.05em',
                  }}
                >
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
                  transform:
                    showContactForm || cardVisible ? 'translateY(0)' : 'translateY(16px)',
                  transition: 'opacity 0.5s ease, transform 0.5s ease',
                  borderRadius: '20px',
                  overflow: 'hidden',
                  border: '1px solid rgba(56,49,44,0.1)',
                  boxShadow: '0 8px 40px rgba(56,49,44,0.1)',
                }}
              >
                {showContactForm ? (
                  /* ── Contact Collection Form ─────────────────────────────── */
                  <div style={{ background: '#FFFFFF', padding: '28px' }}>
                    <div className="flex items-center gap-3 mb-6">
                      <div
                        style={{
                          width: 44,
                          height: 44,
                          borderRadius: '50%',
                          background: 'rgba(119,157,124,0.12)',
                          border: '1.5px solid rgba(119,157,124,0.35)',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          flexShrink: 0,
                        }}
                      >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                          <path
                            d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"
                            stroke="#779D7C"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          />
                          <circle cx="12" cy="7" r="4" stroke="#779D7C" strokeWidth="1.5" />
                        </svg>
                      </div>
                      <div>
                        <p
                          style={{
                            fontFamily: 'Red Hat Text, sans-serif',
                            fontSize: '16px',
                            fontWeight: 700,
                            color: '#38312C',
                            marginBottom: '3px',
                          }}
                        >
                          Your protocol is ready
                        </p>
                        <p
                          style={{
                            fontFamily: 'Red Hat Text, sans-serif',
                            fontSize: '13px',
                            color: '#779D7C',
                          }}
                        >
                          Enter your details to unlock your personalized recommendation
                        </p>
                      </div>
                    </div>

                    <form onSubmit={handleContactSubmit} noValidate>
                      {/* Name */}
                      <div style={{ marginBottom: '14px' }}>
                        <label style={labelStyle}>First Name</label>
                        <input
                          type="text"
                          value={contactInfo.name}
                          onChange={(e) => {
                            setContactInfo((p) => ({ ...p, name: e.target.value }));
                            if (contactErrors.name)
                              setContactErrors((p) => ({ ...p, name: undefined }));
                          }}
                          placeholder="Your first name"
                          style={inputStyle(!!contactErrors.name)}
                          onFocus={(e) => {
                            if (!contactErrors.name)
                              e.currentTarget.style.borderColor = '#779D7C';
                          }}
                          onBlur={(e) => {
                            if (!contactErrors.name)
                              e.currentTarget.style.borderColor = 'rgba(56,49,44,0.15)';
                          }}
                        />
                        {contactErrors.name && <p style={errorStyle}>{contactErrors.name}</p>}
                      </div>

                      {/* Email */}
                      <div style={{ marginBottom: '14px' }}>
                        <label style={labelStyle}>Email Address</label>
                        <input
                          type="email"
                          value={contactInfo.email}
                          onChange={(e) => {
                            setContactInfo((p) => ({ ...p, email: e.target.value }));
                            if (contactErrors.email)
                              setContactErrors((p) => ({ ...p, email: undefined }));
                          }}
                          placeholder="you@example.com"
                          style={inputStyle(!!contactErrors.email)}
                          onFocus={(e) => {
                            if (!contactErrors.email)
                              e.currentTarget.style.borderColor = '#779D7C';
                          }}
                          onBlur={(e) => {
                            if (!contactErrors.email)
                              e.currentTarget.style.borderColor = 'rgba(56,49,44,0.15)';
                          }}
                        />
                        {contactErrors.email && <p style={errorStyle}>{contactErrors.email}</p>}
                      </div>

                      <button
                        type="submit"
                        style={{
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          gap: '8px',
                          width: '100%',
                          padding: '14px 20px',
                          borderRadius: '12px',
                          background: '#779D7C',
                          color: '#FFFFFF',
                          fontFamily: 'Red Hat Text, sans-serif',
                          fontSize: '14px',
                          fontWeight: 600,
                          letterSpacing: '0.06em',
                          border: 'none',
                          cursor: 'pointer',
                          textTransform: 'uppercase' as const,
                          transition: 'opacity 0.2s, transform 0.2s',
                        }}
                        onMouseEnter={(e) => {
                          e.currentTarget.style.opacity = '0.88';
                          e.currentTarget.style.transform = 'translateY(-1px)';
                        }}
                        onMouseLeave={(e) => {
                          e.currentTarget.style.opacity = '1';
                          e.currentTarget.style.transform = 'translateY(0)';
                        }}
                      >
                        View My Protocol
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                          <path
                            d="M5 12h14M12 5l7 7-7 7"
                            stroke="white"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          />
                        </svg>
                      </button>

                      <p
                        style={{
                          fontFamily: 'Red Hat Text, sans-serif',
                          fontSize: '10px',
                          color: 'rgba(92,82,72,0.4)',
                          textAlign: 'center',
                          marginTop: '10px',
                          letterSpacing: '0.04em',
                        }}
                      >
                        Your information is private and secure · No spam, ever
                      </p>
                    </form>
                  </div>
                ) : isFetchingCard && !recommendation ? (
                  /* ── Loading skeleton ────────────────────────────────────── */
                  <div style={{ background: '#FFFFFF', padding: '28px' }}>
                    <div className="flex items-center gap-3 mb-5">
                      <div
                        style={{
                          width: 40,
                          height: 40,
                          borderRadius: '50%',
                          background: 'rgba(119,157,124,0.15)',
                          animation: 'skeletonPulse 1.5s ease-in-out infinite',
                        }}
                      />
                      <div style={{ flex: 1 }}>
                        <div
                          style={{
                            width: '65%',
                            height: 14,
                            borderRadius: 6,
                            background: 'rgba(56,49,44,0.08)',
                            marginBottom: 8,
                            animation: 'skeletonPulse 1.5s ease-in-out infinite',
                          }}
                        />
                        <div
                          style={{
                            width: '40%',
                            height: 10,
                            borderRadius: 6,
                            background: 'rgba(56,49,44,0.05)',
                            animation: 'skeletonPulse 1.5s ease-in-out 0.2s infinite',
                          }}
                        />
                      </div>
                    </div>
                    {[80, 60, 70].map((w, i) => (
                      <div
                        key={i}
                        style={{
                          width: `${w}%`,
                          height: 10,
                          borderRadius: 6,
                          background: 'rgba(56,49,44,0.06)',
                          marginBottom: 8,
                          animation: `skeletonPulse 1.5s ease-in-out ${i * 0.15}s infinite`,
                        }}
                      />
                    ))}
                    <p
                      style={{
                        fontFamily: 'Red Hat Text, sans-serif',
                        fontSize: '12px',
                        color: '#779D7C',
                        marginTop: '16px',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '6px',
                      }}
                    >
                      <span style={{ animation: 'typingBounce 1s ease-in-out infinite' }}>●</span>
                      Generating your protocol recommendation…
                    </p>
                  </div>
                ) : recommendation ? (
                  /* ── Recommendation Card ─────────────────────────────────── */
                  <>
                    {/* Card Header */}
                    <div
                      style={{
                        background: isHim
                          ? 'linear-gradient(135deg, #38312C 0%, #4A4038 100%)'
                          : 'linear-gradient(135deg, #3D5C42 0%, #4E7355 100%)',
                        padding: '24px 28px 22px',
                      }}
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <span
                            style={{
                              display: 'inline-block',
                              fontSize: '10px',
                              letterSpacing: '0.12em',
                              fontFamily: 'Red Hat Text, sans-serif',
                              fontWeight: 600,
                              color: 'rgba(255,255,255,0.5)',
                              textTransform: 'uppercase' as const,
                              marginBottom: '8px',
                            }}
                          >
                            Your Recommended Protocol
                          </span>
                          <h3
                            style={{
                              fontFamily: 'Cormorant Garamond, serif',
                              fontSize: '24px',
                              fontWeight: 700,
                              color: '#FFFFFF',
                              lineHeight: 1.15,
                              letterSpacing: '-0.01em',
                              marginBottom: '8px',
                            }}
                          >
                            {recommendation.protocolName}
                          </h3>
                          <p
                            style={{
                              fontFamily: 'Red Hat Text, sans-serif',
                              fontSize: '13px',
                              color: 'rgba(255,255,255,0.65)',
                              lineHeight: 1.55,
                            }}
                          >
                            {recommendation.tagline}
                          </p>
                        </div>
                        <div
                          style={{
                            width: 48,
                            height: 48,
                            borderRadius: '50%',
                            background: 'rgba(255,255,255,0.1)',
                            border: '1.5px solid rgba(255,255,255,0.2)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            flexShrink: 0,
                          }}
                        >
                          <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                            {isHim ? (
                              <>
                                <circle cx="12" cy="8" r="4" stroke="white" strokeWidth="1.5" />
                                <path
                                  d="M4 20c0-4 3.6-7 8-7s8 3 8 7"
                                  stroke="white"
                                  strokeWidth="1.5"
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                />
                              </>
                            ) : (
                              <>
                                <circle cx="12" cy="10" r="4" stroke="white" strokeWidth="1.5" />
                                <path
                                  d="M12 14v6M9 17h6"
                                  stroke="white"
                                  strokeWidth="1.5"
                                  strokeLinecap="round"
                                />
                              </>
                            )}
                          </svg>
                        </div>
                      </div>
                    </div>

                    {/* Card Body */}
                    <div style={{ background: '#FFFFFF', padding: '22px 28px 26px' }}>
                      <p
                        style={{
                          fontFamily: 'Red Hat Text, sans-serif',
                          fontSize: '10px',
                          letterSpacing: '0.1em',
                          color: '#779D7C',
                          fontWeight: 600,
                          textTransform: 'uppercase' as const,
                          marginBottom: '14px',
                        }}
                      >
                        Key Benefits
                      </p>
                      <div className="space-y-3 mb-6">
                        {recommendation.benefits.map((benefit, i) => (
                          <div key={i} className="flex items-center gap-3">
                            <div
                              style={{
                                width: 22,
                                height: 22,
                                borderRadius: '50%',
                                background: 'rgba(119,157,124,0.12)',
                                border: '1.5px solid rgba(119,157,124,0.35)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flexShrink: 0,
                              }}
                            >
                              <svg width="10" height="10" viewBox="0 0 24 24" fill="none">
                                <path
                                  d="M20 6L9 17l-5-5"
                                  stroke="#779D7C"
                                  strokeWidth="2.5"
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                />
                              </svg>
                            </div>
                            <p
                              style={{
                                fontFamily: 'Red Hat Text, sans-serif',
                                fontSize: '14px',
                                color: '#38312C',
                                lineHeight: 1.5,
                              }}
                            >
                              {benefit}
                            </p>
                          </div>
                        ))}
                      </div>

                      {/* Physician review note */}
                      <div
                        className="flex items-center gap-2 mb-5"
                        style={{
                          padding: '10px 14px',
                          borderRadius: '10px',
                          background: 'rgba(119,157,124,0.07)',
                          border: '1px solid rgba(119,157,124,0.2)',
                        }}
                      >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                          <path
                            d="M9 12l2 2 4-4"
                            stroke="#779D7C"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          />
                          <circle cx="12" cy="12" r="9" stroke="#779D7C" strokeWidth="1.5" />
                        </svg>
                        <p
                          style={{
                            fontFamily: 'Red Hat Text, sans-serif',
                            fontSize: '12px',
                            color: '#5C5248',
                          }}
                        >
                          Physician-reviewed before every delivery
                        </p>
                      </div>

                      {/* CTA */}
                      <a
                        href={recommendation.ctaHref}
                        style={{
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          gap: '8px',
                          width: '100%',
                          padding: '14px 20px',
                          borderRadius: '12px',
                          background: isHim ? '#38312C' : '#779D7C',
                          color: '#FFFFFF',
                          fontFamily: 'Red Hat Text, sans-serif',
                          fontSize: '14px',
                          fontWeight: 600,
                          letterSpacing: '0.06em',
                          textDecoration: 'none',
                          transition: 'opacity 0.2s, transform 0.2s',
                          textTransform: 'uppercase' as const,
                        }}
                        onMouseEnter={(e) => {
                          e.currentTarget.style.opacity = '0.88';
                          e.currentTarget.style.transform = 'translateY(-1px)';
                        }}
                        onMouseLeave={(e) => {
                          e.currentTarget.style.opacity = '1';
                          e.currentTarget.style.transform = 'translateY(0)';
                        }}
                      >
                        {recommendation.ctaText}
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                          <path
                            d="M5 12h14M12 5l7 7-7 7"
                            stroke="white"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          />
                        </svg>
                      </a>

                      <p
                        style={{
                          fontFamily: 'Red Hat Text, sans-serif',
                          fontSize: '10px',
                          color: 'rgba(92,82,72,0.4)',
                          textAlign: 'center',
                          marginTop: '10px',
                          letterSpacing: '0.04em',
                        }}
                      >
                        No commitment required · Cancel anytime
                      </p>
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

// ─── Style helpers ─────────────────────────────────────────────────────────────

const labelStyle: React.CSSProperties = {
  display: 'block',
  fontFamily: 'Red Hat Text, sans-serif',
  fontSize: '11px',
  fontWeight: 600,
  letterSpacing: '0.08em',
  color: '#5C5248',
  textTransform: 'uppercase',
  marginBottom: '6px',
};

const inputStyle = (hasError: boolean): React.CSSProperties => ({
  width: '100%',
  padding: '10px 14px',
  borderRadius: '10px',
  border: hasError ? '1.5px solid #E57373' : '1.5px solid rgba(56,49,44,0.15)',
  background: '#FAFAF8',
  color: '#38312C',
  fontSize: '14px',
  fontFamily: 'Red Hat Text, sans-serif',
  outline: 'none',
  transition: 'border-color 0.2s',
  boxSizing: 'border-box',
});

const errorStyle: React.CSSProperties = {
  fontFamily: 'Red Hat Text, sans-serif',
  fontSize: '11px',
  color: '#E57373',
  marginTop: '4px',
};