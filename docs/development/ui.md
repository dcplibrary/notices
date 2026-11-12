# User interface conventions

Shared look and layout come from [`dcplibrary/ui`](https://github.com/dcplibrary/ui): the staff layout, navigation, headings, cards, badges, status pills and stat cards. Use those components (`<x-dcpl::...>`) and do not re-create them here. If a shared component needs changing, change it in `dcplibrary/ui`.

This package adds only what is specific to notifications. Follow the existing views, and these rules.

## Tables

- Paginated tables sort on the server, so sorting applies to the whole result set and not only the current page.
- Use the `sort` and `dir` (`asc` or `desc`) query parameters, and allow only a fixed list of sortable columns in the controller or service.
- Render headers with the `notices::partials.sortable-header` partial so every table looks and behaves the same.

## Filters

List and data-browser pages use the same filter bar, in this order:

1. A search box with Search and Clear buttons, always visible.
2. A date range, using `<x-notices::date-range-filter>`, always visible.
3. A "Show Advanced Filters" toggle.
4. The advanced filters, collapsed by default, with their own Apply and Clear buttons inside the section.

Controllers accept `date_from`, `date_to` and optionally `quick_range`. Query with a start-of-day lower bound and a next-day upper bound so the date index is used.

## Links into Polaris

- Use a small text link with the external-arrow icon, never a large button.
- Label it "View Patron in Polaris" or "View Item Record in Polaris".
- Place it under the patron or item fields it relates to.

## Failed-contact rows

Dashboard cards and the full Failed Contacts list use the same three-part row:

- Labels: Phone (or Email), Barcode, Name.
- Values: the contact detail and barcode in a monospace font, then the patron name.
- A status line such as "1 failure - Last: Jan 8, 2026", with the Polaris link on the right when a patron ID is known.

Keep this inside one table cell in list views.

## General

- Every colour utility needs its `dark:` counterpart. Check pages in both modes.
- Interactive elements show a visible focus ring.
- Do not rely on colour alone to convey status; pair it with text or an icon.
- After changing Tailwind classes, rebuild the stylesheet as described in [contributing.md](contributing.md).
