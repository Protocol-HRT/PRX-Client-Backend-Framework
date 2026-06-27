import { serve } from "https://deno.land/std@0.192.0/http/server.ts";

serve(async (req) => {
  // ✅ CORS preflight
  if (req.method === "OPTIONS") {
    return new Response("ok", {
      headers: {
        "Access-Control-Allow-Origin": "*",
        "Access-Control-Allow-Methods": "POST, OPTIONS",
        "Access-Control-Allow-Headers": "*",
      },
    });
  }

  try {
    const { to, patientName, planName, planBadge, orderNumber, portalUrl } =
      await req.json();

    const RESEND_API_KEY = Deno.env.get("RESEND_API_KEY");
    if (!RESEND_API_KEY) {
      throw new Error("RESEND_API_KEY is not set");
    }

    const isTRT = planBadge?.toLowerCase().includes("trt");
    const isBlueprint = planBadge?.toLowerCase().includes("blueprint");

    const programLabel = isTRT
      ? "Men's TRT Protocol"
      : isBlueprint
      ? "Protocol Blueprint Assessment" : planName ||"ProtocolHRT Program";

    const onboardingSteps = isTRT
      ? `
        <li style="margin-bottom:10px;">📋 <strong>Complete your clinical intake form</strong> — your physician needs this before your video call.</li>
        <li style="margin-bottom:10px;">🩺 <strong>Book your physician video call</strong> — required before any prescription is written.</li>
        <li style="margin-bottom:10px;">🧪 <strong>Review your AI protocol blueprint</strong> — built from your intake and ready in your portal within 24 hrs.</li>
        <li style="margin-bottom:10px;">💊 <strong>Medication ships after approval</strong> — compounded and delivered discreetly to your door in 5–7 business days.</li>
      `
      : `
        <li style="margin-bottom:10px;">📋 <strong>Complete your clinical intake form</strong> — unlocks your personalized blueprint.</li>
        <li style="margin-bottom:10px;">🧬 <strong>Review your AI-generated blueprint</strong> — physician-reviewed and ready within 24 hrs.</li>
        <li style="margin-bottom:10px;">🔬 <strong>See your lab recommendations</strong> — personalized panel based on your intake.</li>
        <li style="margin-bottom:10px;">⬆️ <strong>Upgrade anytime</strong> — your $49 assessment fee is credited toward any protocol upgrade.</li>
      `;

    const htmlBody = `
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Welcome to ProtocolHRT</title>
</head>
<body style="margin:0;padding:0;background:#F7F4F0;font-family:'DM Sans',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#F7F4F0;padding:40px 16px;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;">

          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#0D0D0D 0%,#1C1C1C 100%);border-radius:16px 16px 0 0;padding:40px 40px 32px;text-align:center;">
              <p style="margin:0 0 8px;font-family:'JetBrains Mono',monospace,sans-serif;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#C9A84C;">ProtocolHRT</p>
              <h1 style="margin:0 0 12px;font-family:Georgia,serif;font-size:32px;font-weight:700;color:#FFFFFF;line-height:1.15;">Your Protocol Begins Now</h1>
              <p style="margin:0;font-size:15px;color:rgba(255,255,255,0.6);line-height:1.6;">Welcome${patientName ? `, ${patientName}` : ""}. Your personalized optimization journey starts today.</p>
              <div style="display:inline-block;margin-top:20px;padding:8px 20px;background:rgba(201,168,76,0.15);border:1px solid rgba(201,168,76,0.3);border-radius:100px;">
                <span style="font-family:'JetBrains Mono',monospace,sans-serif;font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#C9A84C;">${programLabel}</span>
              </div>
            </td>
          </tr>

          <!-- Order Confirmed Badge -->
          <tr>
            <td style="background:#FFFFFF;padding:28px 40px 0;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="background:rgba(90,138,94,0.08);border:1px solid rgba(90,138,94,0.2);border-radius:12px;padding:16px 20px;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                      <tr>
                        <td>
                          <p style="margin:0;font-size:12px;color:#5A8A5E;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;">✅ Payment Confirmed</p>
                          <p style="margin:4px 0 0;font-size:13px;color:#38312C;">Order <strong style="font-family:'JetBrains Mono',monospace;">${orderNumber}</strong></p>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Onboarding Steps -->
          <tr>
            <td style="background:#FFFFFF;padding:28px 40px;">
              <h2 style="margin:0 0 16px;font-family:Georgia,serif;font-size:20px;font-weight:700;color:#38312C;">What Happens Next</h2>
              <ul style="margin:0;padding:0 0 0 4px;list-style:none;font-size:14px;color:#38312C;line-height:1.7;">
                ${onboardingSteps}
              </ul>
            </td>
          </tr>

          <!-- CTA Button -->
          <tr>
            <td style="background:#FFFFFF;padding:0 40px 36px;text-align:center;">
              <a href="${portalUrl}" style="display:inline-block;padding:16px 40px;background:#38312C;color:#FFFFFF;text-decoration:none;border-radius:10px;font-size:15px;font-weight:600;letter-spacing:0.02em;">
                Access Your Patient Portal →
              </a>
              <p style="margin:14px 0 0;font-size:12px;color:#8A7F78;">Or copy this link: <a href="${portalUrl}" style="color:#5A8A5E;word-break:break-all;">${portalUrl}</a></p>
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td style="background:#FFFFFF;padding:0 40px;">
              <hr style="border:none;border-top:1px solid rgba(56,49,44,0.08);margin:0;" />
            </td>
          </tr>

          <!-- Footer note -->
          <tr>
            <td style="background:#FFFFFF;border-radius:0 0 16px 16px;padding:24px 40px 32px;text-align:center;">
              <p style="margin:0;font-size:12px;color:#8A7F78;line-height:1.7;">
                Questions? Reply to this email or contact us at <a href="mailto:support@protocolhrt.com" style="color:#5A8A5E;">support@protocolhrt.com</a><br/>
                ProtocolHRT · Physician-directed hormone optimization
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
    `.trim();

    const res = await fetch("https://api.resend.com/emails", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${RESEND_API_KEY}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        from: "onboarding@resend.dev",
        to: [to],
        subject: `Your ProtocolHRT portal is ready — Order ${orderNumber}`,
        html: htmlBody,
      }),
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data?.message || "Resend API error");
    }

    return new Response(JSON.stringify({ success: true, id: data.id }), {
      headers: {
        "Content-Type": "application/json",
        "Access-Control-Allow-Origin": "*",
      },
    });
  } catch (error) {
    return new Response(
      JSON.stringify({ error: (error as Error).message }),
      {
        status: 500,
        headers: {
          "Content-Type": "application/json",
          "Access-Control-Allow-Origin": "*",
        },
      }
    );
  }
});
