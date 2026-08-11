# Contributing

Small module, short rules.

- PHP 8.3, no dependencies, no build step — the same constraints as Pluck.
- No JavaScript. The form is a form.
- No wording in the PHP. `lang/en.json` and `lang/nl.json`.
- Escape everything that reaches a page with `Escaper::html()`.
- **Never take a price from the request.** The form shows prices; the order is
  priced again from storage. If you find yourself reading an amount out of
  `$post`, stop.

An admin controller is handed a `ModuleContext`, not the admin's own: `get`,
`set`, `list` and `delete` inside this module's space, `media()` and
`addMedia()` for its own files, `render()` for a template of yours, and `back()`
to redirect.

Adding a payment provider is out of scope. If you need one, that is a different
module that reads these orders.
