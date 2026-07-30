# ADR-0017: GeoContext document and Geo Detection admin hub

**Status:** Accepted (Milestone 13, target v0.12.0)

## Context

Milestone 12 shipped the Geo Detection engine with a monolithic admin section:
enable/mode, routing rules, fallback, checkout policy, and a text-based simulation
form on one page. Upcoming milestones need provider chains, evidence traces,
trusted-proxy visibility, and richer sandbox tooling without rewriting the
routing engine.

## Decision

### GeoContext document (schema v1)

Introduce a versioned **GeoContext** value object (`UMC\Geo\GeoContext`) as the
canonical geographic context document for sandbox runs and future runtime
resolution. Schema v1 includes:

- `shopper` — currency precedence simulation flags (no persisted IP)
- `geo` — country and optional hints (city, region, timezone)
- `network` — reserved; **no raw IP persistence** in settings or sandbox output
- `providers` — reserved for M14 provider-chain overrides
- `resolution` — source, confidence, evidence, trace, timing
- `routing` — evaluation result and final currency

Serialization uses `GeoContextSerializer` for admin round-trips (user meta,
JSON display). Sandbox input is built from POST via `from_sandbox_post()`.

### Geo Detection admin hub (v0.12)

Replace the monolithic page with secondary navigation inside
`section=geo_detection`, routed by `geo_panel`:

| Panel | Purpose | Saveable |
|---|---|---|
| Overview | Status summary and quick links | No |
| Detection | Ordered routing rules | Yes (rules only) |
| Geo Sandbox | GeoContext simulation with presets | No (`admin_post`) |
| Providers | Read-only provider status (M14 editable) | No |
| Trusted Proxies | UGC boundary placeholder | No |
| Diagnostics | Links and counts stub | No |
| Settings | Enable/mode, fallback, checkout | Yes (non-rule fields) |

Panel-aware saves merge POST subsets with persisted settings so saving rules
does not wipe operational settings and vice versa.

### Geo Sandbox

- Handler: `admin_post_umc_geo_sandbox_run` (`GeoSandboxController`)
- Service: `GeoSandboxService` — read-only; no session/cookie mutation
- Presets: quick picks (SE/NO/DK/FI/DE/GB/US) + per-user recent countries
  (`umc_geo_sandbox_recent` user meta, ISO codes only)
- Last result: `umc_geo_sandbox_last_result` user meta (JSON document)
- Legacy `admin_post_umc_geo_simulate` redirects to the Sandbox panel

## Consequences

- M14 can extend `providers` and editable provider UI without restructuring
  the hub shell.
- M15 can populate Diagnostics from resolution traces already modeled in
  GeoContext.
- Settings schema remains **v5** for v0.12; no migration required.
- Security guards allow `get_user_meta` / `update_user_meta` in sandbox stores
  only (ISO codes and JSON results, no IP).

## Related

- [ADR-0016](0016-geo-detection-ordered-routing.md) — ordered routing engine
- [`docs/GEO_DETECTION.md`](../GEO_DETECTION.md) — administrator guide
