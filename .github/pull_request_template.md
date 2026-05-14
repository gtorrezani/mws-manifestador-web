## Quality checklist

- [ ] Controllers stay thin; business rules live in actions, services, policies, validators, or domain objects.
- [ ] Fiscal states use enums/value objects, not string literals.
- [ ] Manifestation transitions have unit tests.
- [ ] Agent API authentication HMAC tests were added or preserved.
- [ ] Command idempotency tests were added or preserved.
- [ ] Command lock/concurrency behavior has test coverage for changed paths.
- [ ] No real fiscal XML, access keys, CNPJs, certificates, PFX/P12, private keys, PINs, or passwords were committed.
- [ ] Fixtures are sanitized and intentionally synthetic.
- [ ] Logs do not include XML content, certificate secrets, PINs, passwords, HMAC secrets, or full sensitive payloads.
- [ ] UI text is Brazilian Portuguese; source code, classes, methods, and domain names are English.
- [ ] Local commands passed: `composer quality`, `npm run quality`.
