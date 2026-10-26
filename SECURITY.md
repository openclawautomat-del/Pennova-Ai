# Security policy

Please **do not open public issues** for vulnerabilities.
Use GitHub's *Report a vulnerability* (Security → Advisories) on this repository, with steps to reproduce.
We aim to acknowledge within 7 days.

In scope: authentication/authorisation bypass in AJAX handlers, XSS/CSRF/SSRF, API-key exposure.
Notes: API keys are encrypted at rest with WordPress salts (protects against DB-only leaks, not against full server compromise).
