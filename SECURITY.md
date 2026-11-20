# Security Policy

## Supported versions

`angeo/module-ai-assist` follows SemVer. Only the latest tag receives security
fixes.

## Reporting a vulnerability

Email **info@angeo.dev** with the subject line `[security] module-ai-assist`.

Please include the module version, Magento and PHP versions, and steps to
reproduce. I aim to acknowledge within 72 hours and ship a fix within 14 days
for confirmed high-severity issues. Please do not open a public GitHub issue
for security problems.

## Security model

- **Off by default.** Nothing is sent anywhere until an administrator turns
  the assistant on and adds an API key.
- **The browser cannot choose the facts.** It sends a source code and a
  record reference, both checked against strict patterns. The owning module
  loads the record itself and checks its own ACL.
- **Fixed destinations.** Requests go only to the provider URL constants in
  `Model/Provider/*`. TLS is verified, redirects are not followed, the key
  travels in a header.
- **The key** is stored with Magento's encryptor, declared sensitive (so it
  is not exported with `app:config:dump`), and never sent to the browser.
- **CSRF:** the endpoint is POST only, so Magento checks the form key; ACL
  `Angeo_AiAssist::use`.
- **Output:** answers are escaped before a small Markdown subset is applied;
  links are never made clickable.
- **Prompt injection:** the facts can contain text from the store or from
  other AI models. The system prompt says to treat them as data. That lowers
  the risk but cannot remove it, which is why the assistant has no tools and
  cannot act: the worst case is a wrong or odd answer on the screen.
- **Stored data:** answers, hashes of the question and the facts, token
  counts, admin user id. Deleted after 90 days.
