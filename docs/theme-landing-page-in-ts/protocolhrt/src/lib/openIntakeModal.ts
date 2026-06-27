/**
 * Opens the AI Intake Modal from anywhere in the app.
 * Dispatches a custom event that AiIntakeModal listens for.
 */
export function openIntakeModal() {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('openIntakeModal'));
  }
}
