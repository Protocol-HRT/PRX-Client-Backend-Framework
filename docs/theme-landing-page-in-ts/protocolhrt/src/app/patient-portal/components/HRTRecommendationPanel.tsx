'use client';

import React, { useEffect, useState, useRef, useCallback } from 'react';
import { useChat } from '@/lib/hooks/useChat';
import toast from 'react-hot-toast';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Message {
  role: 'user' | 'assistant';
  content: string;
  timestamp: number;
}

interface Protocol {
  id: string;
  name: string;
  status: string;
  startDate: string;
  nextRefill: string;
  dosage: string;
  frequency: string;
  physician: string;
  category: string;
}

interface Order {
  id: string;
  date: string;
  items: string[];
  total: number;
  status: string;
}

interface Refill {
  id: string;
  medication: string;
  requestedDate: string;
  status: string;
  notes?: string;
}

interface HRTRecommendationPanelProps {
  protocols: Protocol[];
  orders: Order[];
  refills: Refill[];
  loading: boolean;
}

type IntakeStage = 'greeting' | 'symptoms' | 'demographics' | 'history' | 'recommendation';

// ─── System Prompt (matches knowledge base) ───────────────────────────────────

const SYSTEM_PROMPT = `You are ProtocolHRT's AI Concierge — think of yourself as a knowledgeable friend who works in men's health. Not a sales bot. Not a clinical intake form. Warm, direct, and confident. Short paragraphs. No bullet lists in conversation. Never sound like you're reading from a script. When someone shares a symptom or struggle, acknowledge it as a real human experience before moving to the next question.

Instead of: "Thank you. Next question: How would you rate your energy levels?" You say:"Yeah, that's one of the things we hear most — grinding through the day and wondering why it used to feel easier. Let me ask you a couple more things so I can build something actually useful for you."

━━━ KNOWLEDGE BASE VERSION: 1.2 ━━━
Scope: Concierge Agent + Checkout Agent
Do NOT apply to: Clinical Intake Agent (post-checkout, separate scope)

━━━ GENDER GATE (HARD RULE — run before all other routing) ━━━

Before any intent detection or offer routing, confirm the patient's biological sex.

• If the patient is NOT male → Route IMMEDIATELY to the $49 Protocol Blueprint Assessment. Do NOT present TRT under any circumstances. This is a hard gate, not a soft signal.
• If the patient IS male → Proceed to the TRT signal questions below before continuing the intake.

━━━ TRT SIGNAL QUESTIONS (male patients only — ask these early, before other intake questions) ━━━

After confirming the patient is male, ask these two questions in order before proceeding with the rest of the intake:

Question 1 (Energy/Drive Score):
"On a scale of 1–10, how would you rate your energy, drive, and motivation over the last 3 months?"
→ Score of 6 or below = TRT signal. Record this.

Question 2 (Symptom Checklist):
"Are any of the following affecting you? Select all that apply: Low libido / Low muscle mass or poor gains despite training / Difficulty recovering from workouts / Brain fog or lack of focus / Mood changes or irritability"
→ 2 or more selections = strong TRT signal. Record this.

━━━ TRUST-BUILDING RULES ━━━

• After the first 2 questions, briefly tell the patient what you're building and why the questions matter. Say something like: "I'm asking these because your protocol needs to be specific to you — not a generic stack someone posted on Reddit."

• When a patient shares anything personal — fatigue, low libido, poor recovery, weight gain — normalize it without dramatizing it. Example: "That's actually one of the most common things men your age notice — and it's very addressable."

• Before presenting any offer, deliver a short 2–3 sentence personalized protocol summary. The patient hears what was built FOR THEM before they see a price. This is the highest-trust moment in the funnel. Never skip it.

• Never rush to checkout. If a patient seems hesitant, ask one clarifying question before re-presenting the offer.

• If the patient has answered 4 or more questions, move toward the protocol summary and offer. Depth comes from quality of listening, not quantity of questions.

━━━ INTENT DETECTION (run silently after TRT signal questions for male patients) ━━━

Assign one of these intent flags based on patient signals:

• trt_primary — Male patient AND (energy/drive score ≤ 6 OR 2+ symptom selections from the checklist above)
• hrt_primary — Female + hormones + menopause + mood concerns [NOTE: female patients are hard-gated to $49 Blueprint — do not route to TRT]
• peptide_primary — Recovery focus, injury healing, fat loss without hormone therapy interest, performance optimization without hormone flags, general biohacking interest, female patient not flagging HRT
• glp1_primary — Weight loss + metabolic focus (route to GLP-1 flow — future)
• undecided — Patient is exploratory, has not expressed a clear primary goal

INTENT BEHAVIOR RULES:
• trt_primary (male + score ≤ 6 OR 2+ symptom selections) → Lead with TRT program at $149/mo. Present peptide add-ons as enhancements to the TRT protocol. Include LTO urgency language ONCE at offer presentation.
• Male + high energy score (7–10) + 0–1 symptom selections → Lead with $49 Protocol Blueprint Assessment. Mention TRT is available if they want labs to check baseline levels.
• peptide_primary → Lead with peptide-only protocol stack. Present $49 assessment with credit mechanic. Do not push TRT unless patient raises it. Do not use LTO urgency language.
• undecided → Complete the full intake before presenting any offer. Default to the $49 Protocol Blueprint Assessment as the primary recommendation. Frame it as the lowest-friction starting point.
• Any female patient → $49 Protocol Blueprint only. Never present TRT. No exceptions.
• Patient is exploratory / skips questions → Complete full intake before presenting any offer.

Never present all offers simultaneously — lead with the most relevant offer based on intent.

━━━ CREDIT BRIDGE (blueprint → TRT upgrade, mid-conversation) ━━━

If a patient who has been routed to the $49 Blueprint path signals TRT interest at any point mid-conversation, say exactly:
"Good news — if you decide to move forward with TRT, your $49 assessment fee is credited toward your first order. You're not paying twice."

Use this message once when the intent shift is detected. Then proceed to present the TRT offer with LTO urgency language.

━━━ OFFER DIFFERENTIATION — USE THIS LANGUAGE EXACTLY ━━━

When presenting the $49 Protocol Blueprint Assessment, say:
"Here's what the $49 gets you: I build your full personalized protocol — diet, sleep, supplements, and a specific peptide recommendation based on everything you just told me. A licensed physician then reviews it and approves your peptide order. And here's the part worth knowing — the $49 comes straight off your first peptide order. So if you move forward, the assessment essentially paid for itself. You're getting a physician-reviewed protocol, not a quiz result."

When presenting the $149 Men's TRT Program, say:
"Based on what you've shared, I want to walk you through our TRT program — and I want to be upfront about why I'm recommending it. What you're describing lines up closely with suboptimal testosterone. The $149/mo covers everything: your medication is included, a physician does a live video call with you before anything is prescribed, and your monthly supply ships to your door. No separate pharmacy bills. One number, everything included. You can also add up to 3 peptides to your protocol at checkout — the physician approves those async after your call, no extra visits needed."

When a patient is on the fence between the two, say:
"Here's the simplest way to think about it: if you want to understand what's going on and start with peptides, the $49 gets you a physician-reviewed protocol and it goes toward your first order. If you already know testosterone is the issue or you want the full program, the $149 gets you everything including your medication. Either way, you're not guessing — a physician reviews your case before anything ships."

━━━ FULL FLOW EXPLANATION — USE THIS WHEN A PATIENT ASKS HOW IT WORKS ━━━

"Here's the full picture: you go through the intake here with me, we figure out the right protocol together, and you check out for either the $49 assessment or the $149 TRT program. After checkout, the patient is routed to the patient portal to complete their intake forms. The physician takes it from there."

━━━ OBJECTION HANDLING — NEVER DISCOUNT, ALWAYS REFRAME ━━━

If a patient hesitates on the $49, say:
"Think of it as a consult fee that disappears the moment you order — it comes straight off your first peptide order."

If a patient hesitates on the $149, say:
"For context — a single TRT consultation at a men's health clinic typically runs $200–$300 before you even get to medication costs. This is all-in at $149 including your monthly supply."

If a patient questions legitimacy or trust, say:
"Every protocol is reviewed by a licensed physician before anything ships. This isn't a supplement company — it's a licensed telemedicine practice operating across all 50 states."

━━━ AVAILABLE PROTOCOLS ━━━

HIM PROTOCOLS (for men):
• HIM TRT Protocol — Testosterone Replacement Therapy · $149/mo all-in · medication included · live physician video call required
• HIM Peptide Protocol — Growth hormone peptides for recovery, body composition, anti-aging · async physician approval
• HIM Metabolic Protocol — Weight loss, insulin sensitivity, metabolic optimization
• HIM Cognitive Protocol — Mental clarity, focus, neuroprotection, performance

HER PROTOCOLS (for women):
• HER Hormone Balance Protocol — Estrogen/progesterone balance, perimenopause/menopause relief · live physician video call required
• HER Thyroid & Metabolic Protocol — Thyroid optimization, energy, metabolism · live physician video call required
• HER Body Composition Protocol — Weight loss, lean muscle, body recomposition
• HER Longevity Protocol — Anti-aging, cellular health, vitality, skin & hair

━━━ OFFER STACK ━━━

OFFER 1 — PROTOCOL BLUEPRINT ASSESSMENT ($49 one-time)
• Full AI concierge intake and protocol build
• Licensed physician review — async for peptide-only, live video call for hormones
• Diet, sleep, exercise, and supplement protocol
• Blood work recommendation if clinically indicated
• Does NOT include compounds (hormones or peptides)
• CREDIT MECHANIC: The $49 is credited in full toward any peptide purchase at checkout.

OFFER 2 — TESTOSTERONE REPLACEMENT THERAPY ($149/mo — LIMITED TIME OFFER)
• Testosterone medication — all-in, no additional pharmacy cost
• Live physician video call — required before prescribing (no exceptions)
• Full AI protocol build delivered before the physician visit
• Blood work kit included if clinically indicated
• Monthly refill, delivered to the patient's door
• Up to 3 peptide add-ons at checkout — async physician approval, no additional call
• Route: AI intake → checkout → live physician video call → lab kit if indicated → Rx + ship

OFFER 3 — PEPTIDE-ONLY PROTOCOL STACK (pricing: FORMULARY PENDING)
• 1–3 peptides, async physician approval, no live call required
• $49 assessment credit applies
• Route: AI intake → checkout → async physician chart review → ship
• PRICING NOTE: FORMULARY PENDING. Do not quote specific peptide prices until the formulary is uploaded and confirmed.

━━━ PRICING RULES ━━━

• TRT is $149/mo all-in including medication. State this clearly if a patient asks.
• The $49 Protocol Blueprint Assessment is credited in full toward any peptide purchase. This is not a discount — it is a credit applied at checkout.
• Peptide pricing is gated behind the formulary upload. Until confirmed, respond to all peptide pricing questions with: "Peptide pricing is confirmed at checkout from our approved formulary."

━━━ INTAKE FLOW ━━━

Stage 1 — GREETING: Introduce yourself warmly. Ask their primary health concern or goal in ONE sentence.
Stage 2 — GENDER GATE: Confirm biological sex. If not male, route to $49 Blueprint immediately.
Stage 3 — TRT SIGNALS (male only): Ask the energy/drive score question, then the symptom checklist question.
Stage 4 — SYMPTOMS: Explore any remaining key symptoms. Ask ONE question at a time. Acknowledge what they share before asking the next question.
Stage 5 — DEMOGRAPHICS: Ask their age.
Stage 6 — HISTORY: Ask ONE brief question about existing conditions or current medications/supplements.
Stage 7 — RECOMMENDATION: Deliver a short 2–3 sentence personalized protocol summary first, then present the offer.

━━━ UPSELL SEQUENCE (strict — do not skip steps) ━━━

Step 1: Present the patient's protocol summary — what was built for them specifically. 2–3 sentences. The patient should feel seen before they see pricing.
Step 2: Present the primary offer based on intent_type flag. Use the verbatim offer language from the OFFER DIFFERENTIATION section above.
Step 3: Present peptide add-on options (max 3, from formulary only).
Step 4: Confirm the $49 credit.
Step 5: Route to checkout.

Do NOT present pricing before the protocol summary is delivered.
Do NOT skip steps or present all offers at once.

━━━ LTO URGENCY LANGUAGE (TRT flow only — when intent_type = trt_primary) ━━━

Use this verbatim when presenting the TRT offer:
"One thing worth knowing before you decide: the $149/mo rate is a special launch offer. Patients who enroll now lock in this price for the life of their subscription. We haven't announced when this window closes, but it will. If TRT is on your radar, now is when the math makes the most sense."

Rules for LTO language:
• Use ONLY when routing to TRT ($149/mo) — NEVER use on the $49 Blueprint path
• Never use a fake countdown or a specific end date you cannot confirm
• Include LTO language ONCE per conversation at the point of offer presentation
• Do not repeat it more than once

━━━ DISQUALIFICATION HANDLING ━━━

If intake flags a hard contraindication:
1. Acknowledge the flag directly and transparently
2. Do NOT proceed to offer presentation for the contraindicated protocol
3. Offer to route the patient to the $49 protocol-only assessment for a physician consult
4. Suggest the patient speak with their primary care physician

━━━ PHYSICIAN ROUTING ━━━

• TRT → Live physician video call — required before prescribing. No exceptions.
• Women's HRT → Live physician video call — required
• Peptide-only → Async chart review — no live call needed
• Peptide add-on (post-TRT call) → Async chart review — no additional live visit required

━━━ CHECKOUT GOAL ━━━

The goal is to get to one of two checkouts: the $49 Protocol Blueprint or the $149 Men's TRT Program. The conversation should feel warm and human, but it should always be moving forward. After checkout, the patient is routed to the patient portal to complete their intake forms. The physician takes it from there.

━━━ CONVERSATION RULES ━━━

• Never quote a peptide price that has not come from the confirmed formulary
• Never diagnose or make clinical claims — route clinical questions to the physician
• Never use fake scarcity — LTO urgency is real, state it plainly and once
• Never provide specific dosing information
• Always note physician review before delivery

━━━ TONE & STYLE ━━━

• Warm, direct, confident — like a knowledgeable friend in men's health, not a sales bot
• Short paragraphs. No bullet lists in conversation.
• Never sound like you're reading from a script
• When someone shares a symptom or struggle, acknowledge it as a real human experience before moving to the next question
• Keep each response to 2–4 sentences max
• Ask ONLY one question per turn
• NEVER diagnose — you are an intake specialist, not a physician`;

// ─── Initial greeting message ─────────────────────────────────────────────────

const INITIAL_MESSAGE: Message = {
  role: 'assistant',
  content: "Welcome to your ProtocolHRT AI Concierge. I'm here to conduct a brief health intake and help identify the most appropriate protocol for your goals.\n\n**What would you most like to change about how you feel right now?**",
  timestamp: Date.now(),
};

// ─── Quick-reply helpers ──────────────────────────────────────────────────────

function getContextualPrompts(lastAiMessage: string, stage: IntakeStage): string[] {
  const msg = lastAiMessage.toLowerCase();

  if (
    msg.includes('biological sex') || msg.includes('male or female') ||
    msg.includes('man or woman') || msg.includes('him or her') ||
    msg.includes('gender') || (msg.includes('sex') && (msg.includes('born') || msg.includes('biolog')))
  ) {
    return ['Male', 'Female'];
  }

  if (
    msg.includes('how old') || msg.includes('your age') ||
    msg.includes('age range') || msg.includes('age are you') ||
    (msg.includes('age') && msg.includes('?'))
  ) {
    return ["I'm in my 30s", "I'm in my 40s", "I'm in my 50s", "I'm in my 60s+"];
  }

  if (
    msg.includes('medication') || msg.includes('supplement') ||
    msg.includes('prescription') || msg.includes('currently taking') ||
    msg.includes('existing condition') || msg.includes('health condition') ||
    msg.includes('medical history') || msg.includes('thyroid') || msg.includes('diagnosed')
  ) {
    return ['No medications or conditions', 'I take supplements', 'I have a thyroid condition', "I'm on prescription medication", 'I have diabetes or metabolic issues'];
  }

  if (msg.includes('sleep') || msg.includes('insomnia') || msg.includes('rest') || msg.includes('wake up')) {
    return ['Yes, my sleep is poor', 'I wake up frequently', 'I sleep okay but still tired', 'Energy is okay but could be better'];
  }

  if (msg.includes('libido') || msg.includes('sex drive') || msg.includes('sexual') || msg.includes('intimacy') || msg.includes('erectile')) {
    return ['Yes, my libido has dropped', 'Somewhat, it fluctuates', 'No change in libido', 'This is my main concern'];
  }

  if (msg.includes('energy') || msg.includes('fatigue') || msg.includes('exhausted') || msg.includes('low energy') || msg.includes('sluggish')) {
    return ['Yes, I feel exhausted daily', 'Afternoon energy crashes', 'Moderate fatigue', 'Energy is okay but could be better'];
  }

  if (msg.includes('mood') || msg.includes('anxiety') || msg.includes('depression') || msg.includes('irritab') || msg.includes('mental') || msg.includes('emotional')) {
    return ['Yes, I feel anxious or irritable', 'I have mood swings', 'I feel low or unmotivated', 'My mood is generally okay'];
  }

  if (msg.includes('weight') || msg.includes('body fat') || msg.includes('belly') || msg.includes('muscle') || msg.includes('body composition')) {
    return ["I've gained weight recently", 'I struggle to lose fat', 'I want to build muscle', 'Both fat loss and muscle gain'];
  }

  if (msg.includes('brain fog') || msg.includes('focus') || msg.includes('concentration') || msg.includes('memory') || msg.includes('cognitive') || msg.includes('foggy')) {
    return ['Yes, I have brain fog often', 'I struggle to concentrate', 'My memory has declined', 'Occasional fogginess'];
  }

  if (msg.includes('hot flash') || msg.includes('menopause') || msg.includes('perimenopause') || msg.includes('night sweat') || msg.includes('cycle') || msg.includes('period')) {
    return ['Yes, I have hot flashes', 'I have night sweats', 'My cycle has changed', 'I am post-menopausal'];
  }

  if (msg.includes('recovery') || msg.includes('workout') || msg.includes('exercise') || msg.includes('performance') || msg.includes('athletic') || msg.includes('gym')) {
    return ['My recovery is slow', 'I feel weaker than before', 'I want better performance', 'I rarely exercise currently'];
  }

  if (
    stage === 'greeting' || msg.includes('improve') || msg.includes('goal') ||
    msg.includes('looking for') || msg.includes('help you') ||
    msg.includes('what brings') || msg.includes('start') || msg.includes('concern') ||
    msg.includes('change about how you feel') || msg.includes('most like to change')
  ) {
    return ['Low energy & fatigue', 'Hormone balance', 'Burn fat & lose weight', 'Build muscle & strength', 'Better sleep & mood', 'Sexual health', 'Anti-aging & longevity'];
  }

  if (msg.includes('have you') || msg.includes('do you') || msg.includes('are you') || msg.includes('would you')) {
    return ['Yes', 'No', 'Sometimes', 'Not sure'];
  }

  const STAGE_DEFAULTS: Record<IntakeStage, string[]> = {
    greeting: ['Low energy & fatigue', 'Hormone balance', 'Burn fat', 'Build muscle', 'Better sleep & mood', 'Sexual health', 'Anti-aging'],
    symptoms: ['Yes, my sleep is poor', 'My libido has dropped', 'I feel foggy & unfocused', "I've gained weight recently", 'I feel anxious or moody', 'My recovery is slow'],
    demographics: ["I'm in my 30s", "I'm in my 40s", "I'm in my 50s", "I'm in my 60s+"],
    history: ['No existing conditions', 'I take some supplements', 'I have thyroid issues', "I'm on medication"],
    recommendation: ['Tell me more', 'How do I get started?', 'What does it cost?'],
  };

  return STAGE_DEFAULTS[stage];
}

function inferStage(messages: Message[]): IntakeStage {
  const count = messages.filter((m) => m.role === 'assistant').length;
  if (count <= 1) return 'greeting';
  if (count <= 3) return 'symptoms';
  if (count <= 5) return 'demographics';
  if (count <= 6) return 'history';
  return 'recommendation';
}

// ─── Message renderer ─────────────────────────────────────────────────────────

function renderMessage(content: string) {
  const lines = content.split('\n');
  return lines.map((line, i) => {
    if (line.startsWith('**') && line.endsWith('**') && line.length > 4) {
      return (
        <p key={i} style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', fontWeight: 700, color: '#1A1A1A', margin: '0 0 4px 0', lineHeight: 1.6 }}>
          {line.replace(/\*\*/g, '')}
        </p>
      );
    }
    if (line.startsWith('- ') || line.startsWith('• ')) {
      return (
        <div key={i} style={{ display: 'flex', gap: '8px', alignItems: 'flex-start', marginBottom: '3px' }}>
          <span style={{ width: '5px', height: '5px', borderRadius: '50%', background: '#5A8A5E', flexShrink: 0, marginTop: '7px' }} />
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#3A3A3A', lineHeight: 1.6, margin: 0 }}>
            {line.replace(/^[-•] /, '').replace(/\*\*/g, '')}
          </p>
        </div>
      );
    }
    if (line.trim() === '') return <div key={i} style={{ height: '5px' }} />;
    // Inline bold
    const parts = line.split(/\*\*(.*?)\*\*/g);
    return (
      <p key={i} style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#3A3A3A', lineHeight: 1.7, margin: '0 0 4px 0' }}>
        {parts.map((part, j) => j % 2 === 1 ? <strong key={j}>{part}</strong> : part)}
      </p>
    );
  });
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function HRTRecommendationPanel({
  protocols,
  orders,
  refills,
  loading,
}: HRTRecommendationPanelProps) {
  const [started, setStarted] = useState(false);
  const [messages, setMessages] = useState<Message[]>([INITIAL_MESSAGE]);
  const [input, setInput] = useState('');
  const [streamingContent, setStreamingContent] = useState('');
  const [isTyping, setIsTyping] = useState(false);
  const [currentStage, setCurrentStage] = useState<IntakeStage>('greeting');
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const { response, isLoading, error, sendMessage } = useChat('ANTHROPIC', 'claude-sonnet-4-5-20250929', true);

  useEffect(() => {
    if (error) toast.error(error.message);
  }, [error]);

  // Streaming handler
  useEffect(() => {
    if (isLoading && response) {
      setStreamingContent(response);
      setIsTyping(false);
    }
    if (!isLoading && response && streamingContent) {
      const newMsg: Message = { role: 'assistant', content: response, timestamp: Date.now() };
      const newMessages = [...messages, newMsg];
      setMessages(newMessages);
      setStreamingContent('');
      setIsTyping(false);
      setCurrentStage(inferStage(newMessages));
    }
  }, [response, isLoading]);

  useEffect(() => {
    if (isLoading && !response) setIsTyping(true);
    else setIsTyping(false);
  }, [isLoading, response]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, streamingContent, isTyping]);

  const buildApiMessages = useCallback((userText: string) => {
    const history = messages.map((m) => ({ role: m.role, content: m.content }));
    return [
      { role: 'system' as const, content: SYSTEM_PROMPT },
      ...history,
      { role: 'user' as const, content: userText },
    ];
  }, [messages]);

  const handleSend = useCallback((text?: string) => {
    const userText = (text ?? input).trim();
    if (!userText || isLoading) return;
    const userMsg: Message = { role: 'user', content: userText, timestamp: Date.now() };
    setMessages((prev) => [...prev, userMsg]);
    setInput('');
    sendMessage(buildApiMessages(userText), { temperature: 0.72, max_tokens: 600 });
    setTimeout(() => inputRef.current?.focus(), 50);
  }, [input, isLoading, buildApiMessages, sendMessage]);

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); }
  };

  const handleReset = () => {
    setMessages([{ ...INITIAL_MESSAGE, timestamp: Date.now() }]);
    setInput(''); setStreamingContent(''); setIsTyping(false);
    setCurrentStage('greeting'); setStarted(false);
  };

  const lastAiMessage = [...messages].reverse().find((m) => m.role === 'assistant')?.content ?? '';
  const quickReplies = getContextualPrompts(lastAiMessage, currentStage);

  return (
    <section>
      {/* Header */}
      <div className="flex items-center justify-between mb-5">
        <div>
          <p className="section-label mb-1">AI Concierge</p>
          <h2 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '26px', fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.02em', lineHeight: 1.2 }}>
            Protocol Intake Agent
          </h2>
        </div>
        <span style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', color: '#8A8A8A', background: 'rgba(0,0,0,0.04)', padding: '4px 10px', borderRadius: '20px', letterSpacing: '0.04em' }}>
          Claude AI
        </span>
      </div>

      <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', overflow: 'hidden' }}>
        {/* Accent bar */}
        <div style={{ height: '3px', background: 'linear-gradient(90deg, #C9A84C 0%, #5A8A5E 100%)' }} />

        {!started ? (
          /* Pre-start state */
          <div style={{ padding: '32px 24px', textAlign: 'center' }}>
            <div style={{ width: '52px', height: '52px', borderRadius: '50%', background: 'rgba(201,168,76,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 16px' }}>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
              </svg>
            </div>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '15px', fontWeight: 600, color: '#1A1A1A', marginBottom: '8px' }}>
              Start Your Protocol Intake
            </p>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#8A8A8A', lineHeight: 1.6, maxWidth: '380px', margin: '0 auto 20px' }}>
              Our AI Concierge will ask you a few targeted questions to identify the most appropriate protocol for your goals and biology.
            </p>
            <button
              onClick={() => setStarted(true)}
              style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', background: '#C9A84C', color: '#FFFFFF', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', fontWeight: 600, padding: '11px 28px', borderRadius: '50px', border: 'none', cursor: 'pointer', letterSpacing: '0.01em' }}
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
              </svg>
              Begin Intake
            </button>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#AAAAAA', marginTop: '12px' }}>
              Powered by Claude AI · Takes about 2 minutes
            </p>
          </div>
        ) : (
          /* Chat interface */
          <div style={{ display: 'flex', flexDirection: 'column', height: '480px' }}>
            {/* Status bar */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px 16px', borderBottom: '1px solid rgba(0,0,0,0.05)', background: 'rgba(248,247,245,0.6)' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '7px' }}>
                <div style={{ width: '7px', height: '7px', borderRadius: '50%', background: isLoading ? '#C9A84C' : '#5A8A5E', animation: isLoading ? 'aiPulse 1.2s ease-in-out infinite' : 'none' }} />
                <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: isLoading ? '#C9A84C' : '#5A8A5E', fontWeight: 500 }}>
                  {isLoading ? 'Thinking…' : 'ProtocolHRT Concierge'}
                </span>
              </div>
              <button
                onClick={handleReset}
                style={{ background: 'none', border: '1px solid rgba(0,0,0,0.1)', borderRadius: '8px', padding: '4px 10px', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#8A8A8A', cursor: 'pointer' }}
              >
                Reset
              </button>
            </div>

            {/* Messages */}
            <div style={{ flex: 1, overflowY: 'auto', padding: '16px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
              {messages.map((msg, i) => (
                <div
                  key={i}
                  style={{
                    display: 'flex',
                    justifyContent: msg.role === 'user' ? 'flex-end' : 'flex-start',
                  }}
                >
                  <div
                    style={{
                      maxWidth: '82%',
                      padding: '10px 14px',
                      borderRadius: msg.role === 'user' ? '16px 16px 4px 16px' : '16px 16px 16px 4px',
                      background: msg.role === 'user' ? '#1A1A1A' : 'rgba(248,247,245,0.9)',
                      border: msg.role === 'user' ? 'none' : '1px solid rgba(0,0,0,0.07)',
                    }}
                  >
                    {msg.role === 'user' ? (
                      <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#FFFFFF', lineHeight: 1.6, margin: 0 }}>
                        {msg.content}
                      </p>
                    ) : (
                      <div>{renderMessage(msg.content)}</div>
                    )}
                  </div>
                </div>
              ))}

              {/* Streaming bubble */}
              {(isTyping || (isLoading && streamingContent)) && (
                <div style={{ display: 'flex', justifyContent: 'flex-start' }}>
                  <div style={{ maxWidth: '82%', padding: '10px 14px', borderRadius: '16px 16px 16px 4px', background: 'rgba(248,247,245,0.9)', border: '1px solid rgba(0,0,0,0.07)' }}>
                    {isTyping && !streamingContent ? (
                      <div style={{ display: 'flex', gap: '4px', alignItems: 'center', padding: '2px 0' }}>
                        {[0, 1, 2].map((i) => (
                          <div key={i} style={{ width: '6px', height: '6px', borderRadius: '50%', background: '#C9A84C', animation: `aiDot 1.2s ease-in-out ${i * 0.2}s infinite` }} />
                        ))}
                      </div>
                    ) : (
                      <div>{renderMessage(streamingContent)}</div>
                    )}
                  </div>
                </div>
              )}
              <div ref={messagesEndRef} />
            </div>

            {/* Quick replies */}
            {!isLoading && quickReplies.length > 0 && (
              <div style={{ padding: '8px 16px 4px', display: 'flex', flexWrap: 'wrap', gap: '6px', borderTop: '1px solid rgba(0,0,0,0.04)' }}>
                {quickReplies.map((reply) => (
                  <button
                    key={reply}
                    onClick={() => handleSend(reply)}
                    style={{
                      background: 'none',
                      border: '1px solid rgba(201,168,76,0.4)',
                      borderRadius: '20px',
                      padding: '5px 12px',
                      fontFamily: 'DM Sans, system-ui, sans-serif',
                      fontSize: '12px',
                      color: '#8A6A20',
                      cursor: 'pointer',
                      transition: 'all 0.15s ease',
                      whiteSpace: 'nowrap',
                    }}
                    onMouseEnter={(e) => { (e.target as HTMLButtonElement).style.background = 'rgba(201,168,76,0.1)'; }}
                    onMouseLeave={(e) => { (e.target as HTMLButtonElement).style.background = 'none'; }}
                  >
                    {reply}
                  </button>
                ))}
              </div>
            )}

            {/* Input */}
            <div style={{ padding: '10px 16px 14px', borderTop: '1px solid rgba(0,0,0,0.06)' }}>
              <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                <input
                  ref={inputRef}
                  type="text"
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  onKeyDown={handleKeyDown}
                  placeholder="Type your response…"
                  disabled={isLoading}
                  style={{
                    flex: 1,
                    height: '38px',
                    padding: '0 14px',
                    borderRadius: '20px',
                    border: '1px solid rgba(0,0,0,0.12)',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    fontSize: '13px',
                    color: '#1A1A1A',
                    background: isLoading ? 'rgba(0,0,0,0.03)' : '#FFFFFF',
                    outline: 'none',
                  }}
                />
                <button
                  onClick={() => handleSend()}
                  disabled={isLoading || !input.trim()}
                  style={{
                    width: '38px',
                    height: '38px',
                    borderRadius: '50%',
                    background: isLoading || !input.trim() ? 'rgba(201,168,76,0.3)' : '#C9A84C',
                    border: 'none',
                    cursor: isLoading || !input.trim() ? 'not-allowed' : 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                    transition: 'background 0.2s ease',
                  }}
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13" />
                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                  </svg>
                </button>
              </div>
              <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#BBBBBB', marginTop: '6px', textAlign: 'center' }}>
                For informational purposes only · Not a substitute for physician guidance
              </p>
            </div>
          </div>
        )}
      </div>

      <style>{`
        @keyframes aiPulse {
          0%, 100% { opacity: 1; transform: scale(1); }
          50% { opacity: 0.4; transform: scale(0.85); }
        }
        @keyframes aiDot {
          0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
          40% { opacity: 1; transform: scale(1); }
        }
      `}</style>
    </section>
  );
}
