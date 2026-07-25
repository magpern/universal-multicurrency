# CLAUDE.md

## Core invariants

1.  WooCommerce owns inventory.
2.  Never split stock by currency.
3.  Base prices stay in base currency.
4.  Convert prices at runtime.
5.  Orders store exchange-rate snapshots permanently.
6.  HPOS required.
7.  One approved milestone at a time.
8.  Tests accompany every feature.
