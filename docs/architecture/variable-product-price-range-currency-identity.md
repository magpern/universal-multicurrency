# Variable-product price range currency identity (v1.1.1)

**Status:** Authoritative corrective specification for v1.1.1.

**ADR:** [`docs/adr/0033-variable-product-price-range-currency-identity.md`](../adr/0033-variable-product-price-range-currency-identity.md)

**Branch:** `fix/v1.1.1-variable-price-range`

## Defect

After a storefront currency switch to a foreign currency, a variable product
may show a parent min/max range that is still the **base-currency numeric
amounts** formatted with the **foreign** symbol (e.g. `35,99 kr. – 65,99 kr.`),
while a selected variation shows the correctly resolved amount (e.g.
`269,05 kr.`).

## Root cause

`WC_Product_Variable::is_on_sale()` ignores the `$context` argument and always
calls `get_variation_prices()`. When `PriceHooks` resolves a **parent**
`get_price()` it sets `$resolving = true` and asks
`ProductSaleStateResolver` for sale state via `is_on_sale( 'edit' )`. That
nests range construction under the re-entrancy guard, so
`woocommerce_variation_prices_*` callbacks return base amounts that are then
cached under the foreign-currency hash.

## Corrective invariants

See ADR-0033 A–L. Summary:

- Range values = resolved active-currency variation prices.
- Fixed / FX / base rules unchanged; mixed sets valid.
- Hash identity (code + rate + fixed/sale fingerprint) retained.
- No schema, CacheState, snapshot, or HTML-string workaround as primary fix.

## Acceptance probes

1. Foreign currency; call `$variable->get_price()` then
   `$variable->get_variation_prices( true )` — min/max must be converted (or
   fixed), never base amounts.
2. Selected variation `get_price()` agrees with its entry in the range array.
3. EUR → foreign → EUR and rate-change cases from ADR-0033 F–I.
4. Simple products unchanged.
