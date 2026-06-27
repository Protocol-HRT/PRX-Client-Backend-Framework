// ─── n8n Webhook Service ──────────────────────────────────────────────────────
// Replace the placeholder URLs below with your actual n8n webhook URLs.
// Each webhook corresponds to a key automation event in the patient journey.
//
// How to get your n8n webhook URL:
//   1. Open your n8n instance
//   2. Create a new workflow → add a "Webhook" trigger node
//   3. Copy the "Test URL" (for dev) or "Production URL" (for live)
//   4. Paste it into the corresponding env variable in your .env file
// ─────────────────────────────────────────────────────────────────────────────

const N8N_WEBHOOKS = {
  checkoutComplete:    process.env.NEXT_PUBLIC_N8N_CHECKOUT_COMPLETE_URL    ?? 'https://your-n8n-instance.com/webhook/checkout-complete',
  intakeFormSubmitted: process.env.NEXT_PUBLIC_N8N_INTAKE_SUBMITTED_URL     ?? 'https://your-n8n-instance.com/webhook/intake-form-submitted',
  physicianApproved:   process.env.NEXT_PUBLIC_N8N_PHYSICIAN_APPROVED_URL   ?? 'https://your-n8n-instance.com/webhook/physician-approved',
  orderShipped:        process.env.NEXT_PUBLIC_N8N_ORDER_SHIPPED_URL        ?? 'https://your-n8n-instance.com/webhook/order-shipped',
} as const;

// ─── Shared Fire Helper ───────────────────────────────────────────────────────

async function fireWebhook(url: string, payload: Record<string, unknown>): Promise<void> {
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...payload, firedAt: new Date().toISOString() }),
    });
    if (!res.ok) {
      console.warn(`[n8n] Webhook responded with ${res.status} for ${url}`);
    }
  } catch (err) {
    // Non-blocking — never let webhook failures affect the user experience
    console.error('[n8n] Webhook fire failed:', err);
  }
}

// ─── Event: Checkout Complete ─────────────────────────────────────────────────
// Fired immediately after a patient completes checkout.

export interface CheckoutCompletePayload {
  userId?: string;
  email?: string;
  orderNumber: string;
  planKey: string;
  planName: string;
  planPrice: number;
}

export async function triggerCheckoutComplete(payload: CheckoutCompletePayload): Promise<void> {
  await fireWebhook(N8N_WEBHOOKS.checkoutComplete, {
    event: 'checkout_complete',
    ...payload,
  });
}

// ─── Event: Intake Form Submitted ─────────────────────────────────────────────
// Fired after a patient successfully submits a clinical intake form.

export interface IntakeFormSubmittedPayload {
  userId: string;
  serviceType: string;
  formType: string;
  submissionId: string;
  flaggedQuestions: string[];
  isFlagged: boolean;
}

export async function triggerIntakeFormSubmitted(payload: IntakeFormSubmittedPayload): Promise<void> {
  await fireWebhook(N8N_WEBHOOKS.intakeFormSubmitted, {
    event: 'intake_form_submitted',
    ...payload,
  });
}

// ─── Event: Physician Approved ────────────────────────────────────────────────
// Fired when a protocol transitions to 'active' status (physician approval).

export interface PhysicianApprovedPayload {
  userId: string;
  protocolId: string;
  protocolName: string;
  serviceCategory: string;
  physician?: string;
}

export async function triggerPhysicianApproved(payload: PhysicianApprovedPayload): Promise<void> {
  await fireWebhook(N8N_WEBHOOKS.physicianApproved, {
    event: 'physician_approved',
    ...payload,
  });
}

// ─── Event: Order Shipped ─────────────────────────────────────────────────────
// Fired when a shipment status transitions to 'in_transit' or 'out_for_delivery'.

export interface OrderShippedPayload {
  userId: string;
  shipmentId: string;
  medication: string;
  carrier: string;
  trackingNumber: string;
  estimatedDelivery: string;
  status: string;
}

export async function triggerOrderShipped(payload: OrderShippedPayload): Promise<void> {
  await fireWebhook(N8N_WEBHOOKS.orderShipped, {
    event: 'order_shipped',
    ...payload,
  });
}
