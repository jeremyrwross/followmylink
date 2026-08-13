---
paths:
  - app/Services/RedirectTester.php
---

# Services

## Throttle Redirect Checks Per Target Host
RedirectTester must protect third-party sites by rate limiting outbound checks per target host. Keep the host limiter in the service path, not only the UI, so redirects and future callers are covered before each outbound request.
