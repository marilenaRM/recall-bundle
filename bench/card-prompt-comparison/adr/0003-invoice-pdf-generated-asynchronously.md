# Generate invoice PDFs asynchronously

## Context

Rendering an invoice PDF takes up to four seconds. It used to happen during checkout, and timeouts on
that request (issue #318) left orders paid but without an invoice.

## Proposals

- Proposition A: keep rendering during checkout and raise the timeout to 30 seconds.
- Proposition B: dispatch a `GenerateInvoicePdf` message on Messenger once the order is paid, rendered
  by a worker on the `invoices` transport.

## Decision

Proposition B, as described in the document above. The checkout request only records the order and
dispatches the message, so the payment confirmation no longer depends on the renderer.

## Consequences

- The confirmation email no longer attaches the PDF: it links to the invoice page, which shows
  "being prepared" until the worker has stored the file.
- The message is retried three times with a one-minute delay, then lands on the `failed` transport,
  which the on-call developer checks every morning.
- The worker renders from the invoice snapshot taken at payment, never from the current customer
  record, so a later change of address does not alter an issued invoice (see ADR-0001).
