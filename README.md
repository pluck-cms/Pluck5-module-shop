# Shop — things to order, in categories, with prices in steps

```
[module:shop category=spring-rolls]
[module:shop category=3d-prints]
```

Requires Pluck 5.0.0-rc63 or newer.

## Installing

1. Copy this folder into `modules/` as `modules/shop/`.
2. In the admin, under **Settings → Extra modules**, tick `shop`.
3. Under **Modules → Shop**, make a category, then products.
4. Put the marker on a page with **Pluck → Modules → shop** in the editor.

Set an e-mail address under Settings: that is where orders are sent.

## Prices come in steps

Spring rolls sell as 5 for €5, 10 for €9, 20 for €17 — the price per item drops,
which is the whole point of buying ten. A 3D print is one thing for one price,
which is the same shape with a single step.

Each step is orderable on its own, so somebody can take one bag of twenty and two
of five without the form having to understand anything about bulk.

The quantity is always put in front of the label, so "White with carabine" at €20
reads as "10× White with carabine". A label that already starts with the count
keeps its own wording.

Products are ordered by the number in their order field, low first, and by name
where those match — leave everything at 0 and the list is alphabetical.

## One module, not two

Spring rolls and 3D prints looked like two modules for about five minutes. Both
are a name, a photo, a description and a price, and both end in somebody typing
their address. A third thing to sell is a category, not another module.

## The price never comes from the form

The page carries the price so somebody can see it; the order is worked out again
from what the site says a thing costs. Otherwise the price is whatever a posted
field claims, and a form that takes the price from the browser is a shop that
sells for nothing.

Tested: an order posted with `tier_price=0.01` and `total=0` is charged the real
price.

## No payment provider

An order is a promise to pay, not a payment. Adding a provider means webhooks,
refunds, VAT and a chain of responsibility that does not belong in a CMS running
on the cheapest shared hosting there is — and for somebody selling food at the
door, paying on collection is what happens anyway.

## What happens to an order

Stored first, mailed second. `mail()` fails on shared hosting in ways nobody
finds out about until somebody asks where their order went; storing first means
the order exists even when the mail does not.

Two mails: one to whoever is selling, one to whoever ordered. The customer's
address never goes in a header — a name typed into a form is not something to put
in `From`.

Orders are listed under **Modules → Shop → Orders**, with a button to mark one
handled.

## Commas are fine

`5,50`, `1.250,00` and `5.50` are all read as the same number. `(float) "5,50"`
is 5 in PHP, which for a price is the difference between selling and giving away.

## Styling

The theme decides how it looks: `.shop`, `.shop-product` with `__photo`,
`__name`, `__what` and `__steps`, `.shop-step` with `__price`, and `.shop-form`.

## Wording

`lang/en.json` and `lang/nl.json`.

## Money

Under **Modules → Shop** there is a block for how an amount is written: symbol,
which side it goes on, how many decimals, and the two separators.

Not a money library — no exchange rates, no locale database. **Two decimals is
not a safe assumption**: the yen has none and the Kuwaiti, Bahraini and Omani
dinars have three.

The separators start from the site's language; the symbol does not, because
English is the pound, the dollar and the euro.

### If you also run the cashbook module

Set it there too. A module deliberately cannot reach another module's settings,
and that isolation is what stops one from setting `theme` — not worth widening
for a currency symbol.

## Licence

GPL-3.0-or-later, the same as Pluck. See `LICENSE`.
