# User Regional Preferences

Universal Multicurrency participates in the shared Regional Preferences
composition surface for WordPress profiles and WooCommerce account details.
UMC owns the `currency` slot and provides a priority-20 fallback host; Universal
Multilingual owns the priority-10 primary host and the `language` slot when it is
active.

The cross-plugin contract and rollout sequence are defined in the
[Universal Multilingual implementation plan](https://github.com/magpern/universal-multilingual/blob/main/docs/plans/USER_REGIONAL_PREFERENCES_IMPLEMENTATION_PLAN.md).
UMC deliberately does not duplicate that plan under `docs/plans/`.
