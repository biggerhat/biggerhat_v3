# Security policy

## Reporting a vulnerability

Please **do not open a public issue** for a security problem. Report it privately through GitHub:
**Security tab > Report a vulnerability** (<https://github.com/biggerhat/biggerhat_v3/security/advisories/new>).

Include what you found, where (a URL or file), and the steps to reproduce it. We will acknowledge a report within a few days, keep you updated, and credit you
in the fix if you wish. Please give us a reasonable time to fix it before you share it.

## In scope

The code in this repository and the live site at <https://biggerhat.net>: authentication and sessions, access control (viewing or changing another user's data,
reaching `/admin` without a permission), injection (SQL, script, template), file uploads, the Discord bot API, and exposed secrets.

## Out of scope

Denial-of-service and load testing, automated scanners' unverified output, social engineering, physical attacks, and issues in third-party services
(Discord, Cloudflare, the hosting provider) that we do not control.

## For contributors

Never commit secrets (`.env` is ignored; use `.env.example` for names only). Every write action needs a FormRequest with `authorize()`, as described in
[`CLAUDE.md`](CLAUDE.md). If you find a committed secret, report it as above so it can be rotated: removing it from a later commit does not make it safe.
