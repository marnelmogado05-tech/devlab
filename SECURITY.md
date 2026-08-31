# Security Policy

## Reporting a vulnerability

**Please do not open a public issue.**

Report privately through GitHub's [private vulnerability
reporting](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing-information-about-vulnerabilities/privately-reporting-a-security-vulnerability)
on this repository (Security → Report a vulnerability).

Include: what the issue is, how to reproduce it, what an attacker gains, and — if you have one —
a suggested fix. A concrete reproduction is worth more than a scanner report.

We will acknowledge your report, keep you updated while it is being fixed, and credit you in the
advisory unless you prefer otherwise.

## Scope

DevLab is a platform whose value is its progression system, so these are the areas we care most
about:

- **Progression integrity** — any way to obtain XP, score, a level, an achievement or a
  leaderboard position that the game rules do not allow. Includes replay, race conditions and
  client-supplied values.
- **Answer-key exposure** — any way to see a solution, test case or rubric for a challenge you
  have not completed.
- **Authorization bypass / IDOR** — reading or modifying another user's attempts, submissions,
  profile data or drafts.
- **Code execution** — anything running user-controlled code outside the sandbox, or escaping the
  sandbox once it exists.
- **Resource abuse** — draining AI tokens or execution capacity, or denying it to others.
- **Prompt injection with consequences** — steering the model into a privileged action, not
  merely making it say something odd.
- **Community content** — XSS, SSRF or malicious payloads delivered through submitted challenges.
- **Standard web vulnerabilities** — SQLi, XSS, CSRF, mass assignment, session attacks.

## Out of scope

- Missing security headers with no demonstrated impact
- Automated scanner output without a working reproduction
- Social engineering of maintainers or users
- Denial of service by raw volume against our hosting
- Self-XSS, or issues requiring a user to paste attacker-supplied code into their own console
- Vulnerabilities in third-party services we merely link to

## Testing

Test against your own local instance. Do not test against a hosted DevLab in a way that degrades
service or touches other users' data. Never use another person's account.

## Supported versions

DevLab is pre-release. Only `main` is supported until a first tagged release exists.
