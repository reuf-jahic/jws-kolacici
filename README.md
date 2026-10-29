# JWS kolačići

Cookie consent for WordPress that keeps Google and Meta switched off until the visitor says yes. Built for the EU (GDPR and ePrivacy) and Bosnia and Herzegovina (Law on personal data protection, 12/25). Works with Bricks, Elementor, Divi, Gutenberg or no builder at all.

Made by [Jaha Web Solutions](https://jahaweb.com), a web studio that builds and maintains WordPress sites. Need a site, a migration or monthly care? [jahaweb.com](https://jahaweb.com)

## What it does

- **Nothing optional before consent.** Google Analytics 4 or Google Tag Manager and the Meta Pixel load only after the visitor allows them. Before the click the page makes zero requests to Google or Meta.
- **Google Consent Mode v2.** Default "denied" is set in `<head>` before any Google tag; the choice updates it and pushes a `jws_consent_update` event to the dataLayer for GTM triggers. Basic mode by default, Advanced mode as an option.
- **Meta Pixel and Conversions API.** Pixel in the browser and CAPI on the server, both only with marketing consent, with the same event ID so Meta counts each event once. WooCommerce events included.
- **WooCommerce order attribution** (`sbjs_*` cookies) only with consent. By default WooCommerce tracks before consent.
- **Consent log** in its own table: anonymous ID, choice, notice version, time. No IP address, no name. Records older than 3 years are deleted. CSV export.
- **Withdrawal is as easy as consent.** A "Cookie settings" link in the footer reopens the notice; withdrawing deletes the group's cookies and reloads the page.
- **New consent when things change.** The notice version includes a fingerprint of tools, groups and the cookie list, so any change asks everyone again.
- **Cookie list** with purpose, duration and provider: `[jws_kolacici_spisak]`. Cookies of the built-in tools are added automatically.
- **Three languages out of the box:** Bosnian, English, Slovenian. WPML and Polylang supported for your own texts.
- **Removes cookies of the previous banner** (Complianz `cmplz_*` by default) on every page load.
- **Search Console** verification meta tag (no cookies, no consent needed).

## Three ways to show the notice

1. **Built-in card** (default without Bricks): a small card in the bottom right corner, no overlay, site font, button colours taken from the site's colour variables (Advanced Themer, ACSS or Elementor) or set in the settings. "Accept all" and "Necessary only" are the same size, as the law requires.
2. **Bricks popup:** design your own popup in Bricks; the plugin opens it and reads `data-jwsk` attributes.
3. **Your own HTML** with `data-jwsk-traka`, in any builder.

| Attribute | Purpose |
|---|---|
| `data-jwsk-traka` | Wrapper of the notice |
| `data-jwsk="sve"` | Accept all |
| `data-jwsk="nuzni"` | Necessary only |
| `data-jwsk="postavke"` | Toggle settings (adds `is-postavke` to the wrapper) |
| `data-jwsk="sacuvaj"` | Save the switches |
| `data-jwsk="otvori"` or `a[href$="#kolacici"]` | Reopen from the footer |
| `data-jwsk-switch="statistika\|marketing"` | Switch, `role="switch"`, `aria-checked` |
| `data-jwsk-grupa="statistika\|marketing"` | Row of a group, hidden when the group is off |

## For developers

```php
if ( function_exists( 'jws_kolacici_dozvoljeno' ) && jws_kolacici_dozvoljeno( 'marketing' ) ) { /* ... */ }
```

```js
jwsKolacici.kad('statistika', function () { /* load a tool after consent */ });
jwsKolacici.dozvoljeno('marketing'); // true or false
document.addEventListener('jws:kolacici', function (e) { console.log(e.detail.grupe); });
```

The choice is stored in the `jws_kolacici` cookie (`sve`, `nuzni`, `stat` or `mark`) with the version in `jws_kolacici_v`, so PHP can read it too. Decisions for scripts are made in the browser, so the plugin works with page caching.

## Install and updates

Download `jws-kolacici.zip` from the [latest release](https://github.com/reuf-jahic/jws-kolacici/releases/latest) and upload it in Plugins, Add new. Settings are in Settings, Kolačići. New versions show up as a normal plugin update in WordPress (and in MainWP or any tool that reads plugin updates); the plugin checks this repository every 12 hours.

With WP Rocket, clear the minified files cache after an update, otherwise the old script keeps being served.

## Not legal advice

The plugin covers the technical part of consent. Your privacy policy, the list of processing and the legal basis are your responsibility; for special categories of data ask a lawyer.

---

## Ukratko na bosanskom

Obavijest o kolačićima za WordPress: Google i Meta se pale tek kad posjetilac pristane, Consent Mode v2, evidencija pristanaka bez IP adrese, povlačenje preko linka u podnožju, spisak kolačića, bosanski, engleski i slovenački. Usklađeno sa GDPR-om i Zakonom o zaštiti ličnih podataka BiH (12/25). Postavke su u Postavke > Kolačići.

Napravio [Jaha Web Solutions](https://jahaweb.com). Treba vam sajt ili održavanje? [jahaweb.com](https://jahaweb.com)

License: GPL-2.0-or-later.
