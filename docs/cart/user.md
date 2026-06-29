# Cart Module — Operator Guide

## Overview

The Cart module provides anonymous, database-persisted shopping carts for your storefront's React frontend. Visitors can add products and packages to a cart without logging in. Cart state is tracked on the server using a token the frontend stores locally — no sessions or cookies are required on the client side.

There is **no Filament admin section** for the cart module. Carts are created and managed entirely by the frontend application via API. As an operator, you do not configure the cart directly; it works automatically as visitors interact with your storefront.

---

## How carts work

### Cart tokens

Every cart is identified by a unique token (ULID). When a visitor first hits the storefront, the frontend calls `GET /api/v1/cart` with no token and receives a fresh cart plus its token. The frontend stores that token (typically in `localStorage`) and sends it as the `X-Cart-Token` header on every subsequent cart request.

If a visitor returns after their cart has expired (default: 30 days), a new empty cart is created automatically.

### What can go in a cart

Visitors can add two types of items:

| Item type | Description |
|---|---|
| **Product** | A single standalone product from your catalog |
| **Package + Plan** | A package, paired with one of its subscription plans |

For packages, a plan must always be selected — the plan determines the price that gets locked in at add-to-cart time.

### Price snapshotting

When an item is added to the cart, the current price (sale price if set, otherwise retail price) is **snapshotted** into the cart item. This means price changes you make later in the Catalog admin do not retroactively change items already in someone's cart. The subtotal shown to the visitor reflects the price at the time they added each item.

### Cart expiry

Carts automatically expire **30 days** after creation. An expired cart is treated as non-existent — the next request creates a fresh cart. There is no admin UI to extend or restore expired carts.

---

## Cart and lead pairing

When a visitor submits the first checkout step (name + email), the API creates a **Lead** record and links it to the visitor's cart token. This pairing is verified at checkout submission to prevent a cart from being swapped between browser sessions.

This means:
- A lead and the cart that created it must come from the same browser session.
- If a visitor starts checkout in one browser and switches to another, they will need to restart the checkout flow.

---

## What operators need to know

- **Carts are not visible in the admin panel.** There is no list of active carts or cart contents in Filament. Cart data lives in the `carts` and `cart_items` database tables.
- **Price changes do not affect existing carts.** If you change a product's price in the Catalog, visitors with that item already in their cart will see the old price until checkout completes or they re-add the item.
- **Coupon codes** are a reserved field on the cart (`coupon_code`) but coupon functionality is not yet implemented. The field exists for future use.
- **Email** is a reserved field on the cart (`email`) but is not populated by the current cart flow. Lead capture (first checkout step) handles email collection separately.
- **Deleted or unpublished catalog items** remain in carts as-is at the database level. The `CartService` (used by any future Livewire admin views) flags such items as unavailable, but the API layer does not currently filter them out of the cart response.
