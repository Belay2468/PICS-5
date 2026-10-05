# PICS — Setup & QA Checklist for this pass

I couldn't run PHP, MySQL, or `sass` in the sandbox this work was done in
(no PHP binary, no network to install `sass`), so none of this has been
executed against a live stack. Everything below was verified by static
review (brace/tag balance, tracing data flow, cross-checking function
definitions) but **please run through this before trusting it in
production.**

## 0. Setup (do this first)

1. Re-import the database: `dist/php/database/philtrack.sql` into
   `philtrack_db`. This is safe to re-run — it adds the new
   `stock_receipts` table and fixes a trailing-comma bug in the
   `categories` table DDL that existed before this pass; nothing is
   dropped.
2. Compile the stylesheet — the checked-in `dist/scss/main.css` is
   **stale** (from before this pass):
   ```
   cd dist
   sass scss/main.scss scss/main.css
   ```
3. Point XAMPP at `dist/php` and confirm the DB credentials in
   `dist/php/config/db.php` match your local setup.

## 1. Request Item bug (Part 5)

- [ ] Search `tape` on `user/search.php` → click **Request Item** on
      "TAPE 1''" → modal opens with correct id/name/quantity, no console error.
- [ ] Repeat for a normal item (no special characters).
- [ ] Add a test item with an apostrophe (`Bob's Widget`) and a double
      quote (`24" Monitor`) and confirm both open the request modal cleanly
      from **both** `user/search.php` and `user/category.php`.
- [ ] `admin/stock.php` → **Reorder** button on a low-stock item with a
      special-character name → modal opens with the right item.

## 2. Branding (Part 4)

- [ ] Login page: PICS LOGO 2 shows on the dark green left panel,
      undistorted, correct proportions.
- [ ] Admin sidebar top-left: PICS LOGO 1, crisp, correctly sized.
- [ ] User topbar top-left: PICS LOGO 1 in a white circular badge on the
      green bar, legible.
- [ ] Browser tab favicon shows the logo on both login and app pages.

## 3. Inventory List report

- [ ] All Categories + Show Current Stock → totals match `admin/inventory.php`.
- [ ] Each individual category filter.
- [ ] Blank Stock Column mode → quantity cells render as a blank line, not "0".
- [ ] Print preview: category headings, no sidebar/topbar in the printout,
      page breaks don't split a table row.

## 4. Requisition & Issue Slip

- [ ] Search and category filter narrow the picker list correctly.
- [ ] Add several items, adjust quantities, remove one — cart updates live.
- [ ] "Review & Print Slip" with an empty cart → blocked with a message.
- [ ] Review screen shows correct Date / Office / Requested By / Purpose
      and the exact items+quantities chosen.
- [ ] "Back to Edit" returns to the builder with selections intact.
- [ ] Print: letterhead, item table, and all three signature blocks appear;
      no builder UI in the printout.

## 5. Issuance Report

- [ ] All Categories, no dates → matches `admin/issuance.php` history.
- [ ] Single category filter.
- [ ] Date range filter (from-only, to-only, both).
- [ ] Print layout matches the other reports' letterhead/print style.

## 6. Monthly Report

- [ ] On `admin/inventory.php`, use **Receive Stock** on an item (enter a
      qty, today's date, source "PRO") → confirm the item's on-hand
      quantity increases immediately.
- [ ] Generate the Monthly Report for the current month/year → the item
      you just received shows the correct "Received from PRO" number.
- [ ] Issue that same item via `admin/issuance.php` → re-generate the
      Monthly Report → "Issued" reflects it.
- [ ] Submit a user item request (leave it pending) → "Outstanding
      Balance" reflects it for that item.
- [ ] Switch Month/Year to a period with no activity → all-zero rows,
      no errors.
- [ ] Read the footnote at the bottom of the generated report — confirm
      it's clear about what "On Hand" and "Outstanding Balance" actually
      represent (current snapshot vs. per-month figures).

## 7. Responsive / general UI (Parts 2–3)

- [ ] Resize down to tablet/mobile: sidebar collapses to the off-canvas
      drawer, hamburger toggle appears, backdrop dims content.
- [ ] Each report's filter toolbar stacks cleanly at narrow widths (no
      overflow).
- [ ] Report tables scroll horizontally on mobile instead of breaking layout.
- [ ] The Requisition builder's two-column layout (picker + cart) stacks
      to one column below ~992px.

## 8. Regression pass on existing functionality

- [ ] Add / edit / delete an inventory item still works.
- [ ] Issue an item to a user still works and still decrements stock.
- [ ] Approve/reject a pending user request still works.
- [ ] Add/edit a user account still works.
- [ ] Dashboard KPI cards and charts still render correctly (unchanged
      logic, just double-check nothing regressed from the reports.php
      rewrite since some queries were shared/copied).

## Known, intentional limitations (not bugs)

- **Monthly Report "On Hand"** reflects *current* stock, not a frozen
  month-end snapshot — the schema has no historical inventory ledger.
  This is documented in the report's own footnote.
- **Monthly Report "Outstanding Balance"** reflects *currently pending*
  requests, not a value frozen at that historical month's end, for the
  same reason.
- Reports intentionally use browser print for PDF, not a generated file
  — per the brief's explicit instruction not to fake export functionality.
