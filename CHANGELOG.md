# Changelog

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
