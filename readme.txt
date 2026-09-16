=== RayEtun CRM – Sales Pipeline & Lead Management ===
Contributors:      rayetun
Donate link:       https://wise.com/pay/me/mdrayhanu2
Tags:              crm, sales pipeline, lead management, lead capture, kanban
Requires at least: 6.2
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A self-hosted WordPress CRM: visual sales pipeline, lead capture from any form, contacts, tasks, email and reports. Your data, no monthly fees.

== Description ==

📇 **RayEtun CRM** turns your WordPress site into a complete, self-hosted CRM — a visual sales pipeline, automatic lead capture, contact management, tasks, email and reporting — **without a monthly SaaS bill and without your customer data ever leaving your server**.

Built for freelancers, consultants, agencies, and small businesses who already run their site on WordPress and want their leads, deals, and customers in one place — managed from a fast, modern admin app that feels like a dedicated product.

Everything lives in your own WordPress database. There is no external account, no per-seat pricing, and no data sent off-site.

= How RayEtun CRM is organised =

* 💾 **Own your data** — every contact, deal, task, and note is stored in your own database. No third-party cloud, no vendor lock-in, no row-count surcharges.
* 🧲 **Pipeline-first** — a fast, drag-and-drop Kanban board is the heart of the product; see every deal and move it through your stages in seconds.
* 🪶 **Lightweight** — the CRM runs as a self-contained app in wp-admin and adds **zero front-end JavaScript** to your public pages, so your site stays fast.
* 🧩 **Modular** — turn optional features on or off under **Settings → Modules** to keep the admin lean. Switching a module off hides it everywhere but never deletes your data.
* ♿ **Accessible** — the admin app is keyboard-operable end to end, with managed focus, a focus-trapped and Escape-closable slide-over pattern, visible focus rings, and reduced-motion support.
* 🔌 **Extensible** — a documented hooks and filters layer lets add-ons extend the CRM without editing core.

---

= 👥 Contacts =

* ♾️ **Unlimited contacts** with custom fields, tags, notes, and a full activity timeline
* 🏢 Companies, lead status, source, and owner on every record
* ⚡ Fast search, sortable columns, status filters, and **saved filter views**
* 🧮 At-a-glance **summary tiles** on each contact — lead score, status, and lifetime value
* 📬 Per-contact **email subscription status** — sending from the CRM respects an unsubscribed contact
* ☑️ Bulk actions: set status, add tags, delete
* 📥 **CSV import** (with column mapping and duplicate handling) and CSV export

---

= 📊 Visual sales pipeline =

* 🟦 **Drag-and-drop Kanban board** with colour-coded stages — or open a deal and change its stage from the keyboard
* 💼 Deal records with value, currency, expected close date, probability, and linked contacts
* 📈 Per-stage totals and a **pipeline analytics bar**: open value, win rate, average days to close, revenue won this month
* ⏱️ Days-in-stage tracking on every deal card

---

= 🧲 Lead capture =

* 📝 A native, server-rendered lead form via the `[rayetun_crm_form]` shortcode and a **RayEtun CRM Lead Form** block — no front-end JavaScript, with a spam honeypot, and customisable fields right in the block editor
* 🔗 Automatic UTM/source tracking captured from the landing page
* 🔌 Auto-capture from **Contact Form 7**, **WPForms**, and **Fluent Forms** — submissions become contacts with the message and source recorded

---

= 🔥 Lead scoring, tasks & email =

* 🌡️ **Static lead scoring** from form submissions, with Cold / Warm / Hot / Very Hot heat indicators and a daily score-decay routine
* ✅ **Tasks and reminders** with types, priorities, due dates, contact links, an overdue badge, and a due-today email reminder
* ✉️ **Email logging** (every message to a known contact lands on their timeline) and 1-to-1 **send-from-CRM** with reusable templates and merge tags such as `{{first_name}}`

---

= 📈 Dashboard & reports =

* 📊 A **dashboard** with leads this week, open pipeline value, revenue this month, hot leads, pipeline value by stage, quick-action shortcuts, and a recent-activity feed
* 📉 **Reports**: contacts added over time, lead-source breakdown, and pipeline conversion — each exportable to CSV
* 🖊️ Charts are hand-drawn inline SVG with `role="img"` labels and a text legend — no charting library loaded

---

= 🛒 WooCommerce =

* 🧾 New paid orders create or update the buyer as a contact, tagged **Customer**
* 💳 Purchase history (orders and total spent) shown on the contact record
* 🏆 High-value orders can auto-create a won deal in your pipeline

---

= 🧩 Modular by design =

Under **Settings → Modules** you can switch the Sales pipeline, Lead capture forms, Tasks, Email, and WooCommerce sync on or off. Core areas — Contacts, Dashboard, Reports, and Lead scoring — are always on. Turning a module off suspends its menus, REST routes, and scheduled work, but never deletes your data, so you can turn it back on any time.

---

= 🔒 Roles & permissions =

RayEtun CRM maps onto your **existing WordPress roles** — administrators get full management, editors act as agents. It adds three capabilities to those roles rather than creating new ones, and removes them cleanly on uninstall.

---

= 🚀 Pro (coming soon) =

Everything described above is included and fully functional — there are no locked features, usage caps, or upgrade prompts. A separate Pro add-on is planned that would *add* new capabilities not present here — behavioural lead scoring, automation and email sequences, a client portal, multisite management, and an external API — built on the same extension hooks this free plugin exposes. The free plugin bundles no premium SDK.

RayEtun CRM is a CRM, not a bulk email marketing tool. For newsletters and mass campaigns, pair it with a dedicated email plugin.

= Compatible With =

* Contact Form 7, WPForms, and Fluent Forms (lead auto-capture)
* WooCommerce (order sync and purchase history)
* The WordPress block editor (native Lead Form block) and any theme
* Existing WordPress roles — no custom roles created

== Installation ==

1. Upload the plugin to `/wp-content/plugins/`, or install it through the **Plugins** screen in WordPress.
2. Activate the plugin through the **Plugins** screen.
3. Open **RayEtun CRM** from the admin menu. A short welcome walkthrough helps you get started.
4. Add the `[rayetun_crm_form]` shortcode (or the RayEtun CRM Lead Form block) to a page to start capturing leads.

No configuration is required to get started.

== Frequently Asked Questions ==

= 🔒 Does my data leave my server? =

No. All CRM data lives in your own WordPress database, and the plugin makes no external calls of any kind. Nothing is sent to us or to any third party.

= 🔌 Which form plugins are supported? =

Contact Form 7, WPForms, and Fluent Forms are auto-detected — submissions become contacts automatically. There is also a built-in native form (shortcode and block). More integrations are added in future updates.

= 👣 Does it track my visitors? =

No. The free plugin adds zero front-end JavaScript to your public pages and does not track anonymous visitors. Lead scoring is based on form submissions only.

= 🛒 Does it work with WooCommerce? =

Yes. When WooCommerce is active, paid orders sync the customer into the CRM with their purchase history, and can create a deal automatically. If you don't use WooCommerce, the module stays inactive.

= 🧩 Can I turn features off I don't use? =

Yes. Go to **Settings → Modules** and switch the Sales pipeline, Lead capture forms, Tasks, Email, or WooCommerce sync on or off. Your data is preserved either way.

= ♿ Is it accessible? =

Yes. The admin app is keyboard-operable throughout: the Kanban cards, tables, and slide-over dialogs are reachable and operable with the keyboard, focus is moved into and trapped within dialogs (Escape closes them and returns focus), a visible focus ring is shown, and animations respect `prefers-reduced-motion`.

= ⚡ Will it slow down my site? =

No. The CRM runs in the WordPress admin as a self-contained app; your public pages get only a small stylesheet when you place the native lead form, and no JavaScript.

= ♻️ What happens if I uninstall RayEtun CRM? =

Everything is removed cleanly: all of the plugin's custom database tables are dropped, its options and per-user settings are deleted, and the capabilities it granted to your roles are removed. Deleting the plugin removes your CRM data, so export anything you want to keep first. (Deactivating, as opposed to deleting, leaves your data untouched.)

= 🚀 Is there a Pro version? =

This free plugin is fully functional with no locked features or usage caps. A separate Pro add-on is planned that would add new capabilities — behavioural lead scoring, automation and sequences, a client portal, multisite management, and an external API. It bundles no premium SDK.

= 💬 Get Support =

Post in the [WordPress.org support forum](https://wordpress.org/support/plugin/rayetun-crm/).

== Screenshots ==

1. Dashboard — leads this week, open pipeline, revenue, hot leads, quick actions, and recent activity.
2. Contacts — sortable list with heat indicators, tags, saved views, bulk actions, and CSV import/export.
3. Contact record — summary tiles, custom fields, subscription status, activity timeline, email compose, and purchase history.
4. Visual pipeline — drag-and-drop Kanban board with stage totals and analytics.
5. Reports — contacts over time, lead sources, and pipeline conversion, all exportable to CSV.
6. Tasks — due-today, overdue, and upcoming views with priorities.
7. Settings — Modules toggle.
8. Settings — Email templates.

== Development ==

RayEtun CRM's admin interface is built the modern WordPress way, with the official [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts) toolchain (webpack). React and other WordPress packages load from core script handles (`wp-element`, `wp-api-fetch`, and friends) — no third-party libraries are bundled. The complete, unminified source ships in the plugin's `src/` directory; the compiled assets are in `build/`. Nothing is obfuscated.

To build from source:

`npm install && npm run build`

That regenerates everything in `build/`. No build step is needed to *use* the plugin — the compiled output is included.

== External Services ==

This plugin does not connect to any external services. All data stays in your own WordPress database and no information is sent off-site. There is no telemetry, no account, and no phone-home.

== Changelog ==

= 1.0.0 =
* Initial release.
* Contacts with custom fields, tags, notes, activity timeline, saved views, summary tiles, per-contact email subscription status, bulk actions, and CSV import/export.
* Visual drag-and-drop sales pipeline with deals, stage totals, and a pipeline analytics bar.
* Native lead form (shortcode + customisable block) with UTM tracking, plus Contact Form 7, WPForms, and Fluent Forms auto-capture.
* Static lead scoring with heat indicators, tasks and reminders, email logging, and send-from-CRM with templates and merge tags.
* Dashboard with quick actions and CSV-exportable reports (contacts over time, lead sources, pipeline conversion).
* WooCommerce order sync with purchase history and auto-deals.
* Settings → Modules to switch optional features on or off, with always-on core areas.
* Accessibility pass: keyboard-operable Kanban, tables, and dialogs; focus trap and Escape on slide-overs; visible focus rings; reduced-motion support.
* Clean uninstall: drops all custom tables, options, per-user meta, and granted capabilities.

== Upgrade Notice ==

= 1.0.0 =
Initial release of RayEtun CRM.
