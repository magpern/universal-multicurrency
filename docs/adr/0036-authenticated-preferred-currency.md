# ADR-0036 — Authenticated Preferred Currency

## Status

Accepted.

## Context

Authenticated shoppers need a durable preferred currency without weakening the
existing request precedence or allowing geolocation to overwrite that choice.
Universal Multilingual and Universal Multicurrency also need to compose one
Regional Preferences section without directly depending on each other.

## Decision

- Store the uppercase ISO code in user meta `umc_preferred_currency`.
- Resolve shopper currency as explicit → session → cookie → valid authenticated
  user preference → store base.
- Retain invalid stored values for diagnosis, but use base as the effective
  currency and report `invalid_fallback`.
- Treat a valid user preference as an existing shopper source so geo routing
  does not overwrite it.
- Clearing user meta does not clear the current WooCommerce session or cookie.
- Saving a non-empty preference for the current shopper also persists it to the
  current session with origin `user_preference`; editing another user changes
  meta only.
- Preserve the user meta on uninstall.
- Compose profile/account fields through the string-stable hooks documented in
  `docs/HOOKS.md`, with Universal Multilingual at priority 10 and UMC's fallback
  host at priority 20.

## Consequences

Session and cookie selections remain stronger than the durable account default.
The public API enforces self-or-`edit_user` authorization, and invalid or
unauthorized stored values are never activated.
