## What this changes

<!-- One or two sentences. What and why. -->

Closes #

## Type

- [ ] New challenge content
- [ ] New experience
- [ ] Core / platform feature
- [ ] Bug fix
- [ ] UI
- [ ] Documentation
- [ ] Infrastructure / CI
- [ ] Refactor

## Checks

```
./vendor/bin/pint          [ ] pass
./vendor/bin/phpstan       [ ] pass
npx tsc --noEmit           [ ] pass
php artisan test           [ ] pass
```

## Definition of Done

Tick what applies; write `n/a` with a reason for the rest.

- [ ] Backend implemented
- [ ] Frontend implemented
- [ ] Server-side validation
- [ ] Authorization via a policy, with a denied-path test
- [ ] Error states handled and visible to the user
- [ ] Tests added
- [ ] Migration includes indexes, foreign keys and constraints
- [ ] Seed/demo data where it makes the feature usable on a fresh clone
- [ ] Documentation updated
- [ ] Accessibility: keyboard, contrast, reduced motion
- [ ] Performance: no N+1, collections paginated

## Trust and integrity

Required for anything touching input, rewards, AI or execution.

- [ ] No score, XP, completion status or permission is accepted from the client
- [ ] Reward paths are transactional **and** guarded by a database unique constraint
- [ ] There is a test that runs the operation twice and asserts one award
- [ ] No answer key, test case or rubric is sent to an in-progress attempt
- [ ] No user-controlled content reaches `exec`, `eval`, `unserialize` or dynamic dispatch
- [ ] AI output is validated before reaching any privileged sink

## For challenge content

- [ ] Every §70 field is populated
- [ ] **I verified the answer myself** — how: <!-- ran it where / cited which spec -->
- [ ] Difficulty and time estimate are honest
- [ ] The explanation teaches the mechanism, not just the answer
- [ ] Original content, no copyrighted snippets

## Notes for reviewers

<!-- Trade-offs, things you were unsure about, what you deliberately left out. -->
