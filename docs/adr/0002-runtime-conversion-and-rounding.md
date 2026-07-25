# ADR-0002 — Runtime conversion and rounding

## Status

Accepted (Milestone 1).

## Context

The plugin converts base-currency amounts to a selected currency at runtime.
Conversion must be deterministic, must round consistently with WooCommerce's
own totals so converted prices match native ones, and must not silently emit a
value when no rate is available.

## Decision

- **Rate meaning:** a rate means `1 base unit = rate target units` (e.g. base
  EUR, target SEK, rate `11.50` → 1 EUR = 11.50 SEK). Rates are stored as
  decimal strings, base-to-target only.
- **One numeric implementation, no BCMath.** `Converter` casts amount and rate
  to `float`, multiplies, and rounds with PHP `round( …, decimals,
  PHP_ROUND_HALF_UP )` — the same arithmetic WooCommerce's `NumberUtil::round`
  uses. There is no separate BCMath engine and no dual code path. Correctness
  is guaranteed by comprehensive precision/rounding tests, not by an
  alternative backend. Matching WooCommerce is the goal, not out-computing it,
  so a converted unit price rounds identically to a native WooCommerce price.
- **Output format:** a normalized fixed-decimals decimal string with a `.`
  decimal point and no thousands separator (`1150.00`, `14730`, `-11.50`).
- **Base currency is a no-op** with an effective rate of `1`; converting a
  base-currency amount to the base introduces no drift.
- **Missing rates fail explicitly:** an absent, zero, negative or non-numeric
  rate never yields a converted value. `RateProvider` returns `null`; the
  `Converter` raises `MissingRateException`.
- **`Converter` is stateless** and the sole owner of monetary arithmetic.

## Consequences

- Float carries ~15 significant digits — ample for realistic monetary
  magnitudes; extreme magnitudes are out of scope.
- Charm/"pretty" rounding and rate markup are explicitly out of scope; if added
  later they slot into the same conversion step, applied identically to regular
  and sale prices.
