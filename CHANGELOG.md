# Changelog

## 2.3.0

- **The quantity is always shown.** A step labelled "White with carabine" at €20
  said nothing about being ten of them, so the first shop to use this had "10x"
  typed into every label by hand — a person working around software. A label
  that already starts with the count keeps its own wording.
- The order field explains itself: low first, same number sorted by name.

## 2.2.0

- The separators follow the site's language until somebody says otherwise: a
  Dutch site writes 1.234,50, an English one 1,234.50, a Polish one 1 234,50.
  The module knows the language already — it is handed a translator, and a
  translator knows its locale.
- **The symbol does not follow the language**, deliberately. English is the
  pound, the dollar and the euro; guessing a symbol from a language is how a
  Dublin shop ends up priced in sterling.
- Requires rc63: `ModuleContext::locale()` is new, and so is the `$group`
  argument `addMedia()` always used and never had.

## 2.1.0

- How an amount is written is now a setting: symbol, which side, how many
  decimals, and the separators. Two decimals was hard-coded, which is wrong for
  the yen (none) and the dinars (three).

## 2.0.0

- Renamed from `bestellen` to `shop`, and the working language is English.
  **This is a breaking change**: the folder, the marker, the `category`
  parameter and the stored data all move. An install with data in it needs
  `data/modules/bestellen/` copied to `data/modules/shop/`, and
  `[module:bestellen categorie=x]` changed to `[module:shop category=x]`.

## 1.0.0

- First version. Categories, products with price steps, one form under all of
  them, orders stored and mailed. No payment provider.
