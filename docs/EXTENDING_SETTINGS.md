# Extending the TeraWallet admin settings page

TeraWallet's admin settings page is a React app. Third-party plugins can add tabs, fields, custom field components, and custom icons through a single JS-first API exposed on `window.wooWallet.settings`.

This is the **recommended** way to extend the settings page. The legacy PHP filters (`woo_wallet_settings_sections`, `woo_wallet_settings_fields`) still work for backwards compatibility but new integrations should prefer the JS API documented here.

---

## Quick start — register a tab with fields

The only PHP you need is a tiny enqueue hook to load your JS file after TeraWallet's settings bundle. Everything else happens in JavaScript.

**`my-plugin.php`**

```php
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    // Only load on the TeraWallet settings screen.
    if ( 'terawallet_page_woo-wallet-settings' !== $hook ) {
        return;
    }
    wp_enqueue_script(
        'my-plugin-wallet-settings',
        plugin_dir_url( __FILE__ ) . 'wallet-settings.js',
        array( 'woo-wallet-admin-settings', 'wp-hooks' ),
        '1.0.0',
        true
    );
} );
```

**`wallet-settings.js`**

```js
( function () {
    var settings = window.wooWallet && window.wooWallet.settings;
    if ( ! settings ) return;

    settings.registerTab( {
        id:          'wallet_ext_my_plugin',          // must start with `wallet_ext_` or `_wallet_settings_`
        title:       'My Plugin',
        description: 'Configure my plugin\'s wallet behaviour',
        icon:        'shield',                        // see "Icons" below
        priority:    50,                              // lower = higher in sidebar
        fields: [
            {
                name:        'enable_feature',
                type:        'checkbox',
                label:       'Enable feature',
                default:     'off',
                group:       'my_group',
                group_title: 'Feature',
                // `sanitize` is optional — derived from `type` when omitted.
                // See "Sanitize hints" below for the full whitelist.
                sanitize:    'bool',
            },
            {
                name:    'feature_amount',
                type:    'number',
                label:   'Amount',
                default: 10,
                show_if: { field: 'enable_feature', equals: 'on' },
                sanitize: 'absint',
            },
        ],
    } );
} )();
```

Reload the wallet settings page — your tab appears in the sidebar, fields render, edits save, and the values persist to the WordPress option keyed by your `id`.

### Reading the value from PHP

Use the canonical helper:

```php
$enabled = woo_wallet_get_setting( 'wallet_ext_my_plugin', 'enable_feature', 'off' );
$amount  = woo_wallet_get_setting( 'wallet_ext_my_plugin', 'feature_amount', 0 );
```

It works identically for built-in tabs, legacy PHP-filter tabs, and JS-registered tabs.

---

## Add a single field to an existing tab

To inject one extra field into a built-in tab (or another plugin's tab):

```js
window.wooWallet.settings.registerField( '_wallet_settings_general', {
    name:        'my_addon_referrer_code',
    type:        'text',
    label:       'Referrer code',
    default:     '',
    group:       'wallet_topup',
    sanitize:    'key',
} );
```

Two notes:

- For fields added to **built-in** or **PHP-filter** tabs, the save path goes through `/terawallet/v1/settings/section`. The JS `sanitize` hint is **not** sent over the wire in this case — the endpoint sanitizes by the PHP-side field `type` (and `sanitize_callback` if set). If you need a specific sanitizer for that field, register it via the PHP filter instead.
- For fields added to **your own JS-registered** tab, the hint is sent and honored.

---

## Custom field type

Register a React component for any field `type` not in the built-in registry (`text`, `number`, `checkbox`, `select`, `multiselect`, `multicheck`, `radio`, `textarea`, `html`, `attachment`, `color` and aliases).

```js
function StarRatingField( { field, value, onChange } ) {
    return (
        <div>
            { [ 1, 2, 3, 4, 5 ].map( ( n ) => (
                <button
                    key={ n }
                    type="button"
                    onClick={ () => onChange( n ) }
                    aria-pressed={ value === n }
                >
                    { n <= value ? '★' : '☆' }
                </button>
            ) ) }
        </div>
    );
}

window.wooWallet.settings.registerFieldType( 'rating', StarRatingField );

window.wooWallet.settings.registerTab( {
    id:    'wallet_ext_reviews',
    title: 'Reviews',
    icon:  'check',
    fields: [
        {
            name:     'min_rating',
            type:     'rating',   // matches the registered type
            label:    'Minimum rating',
            default:  3,
            sanitize: 'absint',
        },
    ],
} );
```

**Sanitization is still server-side.** Custom field types must declare a `sanitize` hint from the whitelist (or rely on the type-derived default). The hint is what protects the database; the custom component is purely presentational.

---

## Custom icons

The `icon` property on a tab accepts four shapes:

1. **Built-in name** — `'settings'`, `'wallet'`, `'credit'`, `'shield'` (if registered), etc. The full built-in set lives in `src/admin/settings/components/Icon.jsx`.
2. **Object `{ svg: '<path .../>' }`** — raw SVG markup. Only `path`, `circle`, `rect`, `line`, `polyline`, `polygon`, `g`, `ellipse` tags are allowed; scripts and event handlers are stripped.
3. **Object `{ dashicon: 'dashicons-shield' }`** — renders a WordPress dashicon span.
4. **React component** — receives `{ c, w, size }` props (stroke color, stroke width, size).

To register a reusable named icon:

```js
function ShieldIcon( { c, w } ) {
    return (
        <svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } strokeLinecap="round" strokeLinejoin="round" fill="none">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
    );
}

window.wooWallet.settings.registerIcon( 'shield', ShieldIcon );

// Then in the tab:
window.wooWallet.settings.registerTab( {
    id:    'wallet_ext_security',
    title: 'Security',
    icon:  'shield',
    // ...
} );
```

The inline-SVG shortcut (no JS component needed):

```js
window.wooWallet.settings.registerTab( {
    id:    'wallet_ext_security',
    title: 'Security',
    icon:  { svg: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>' },
    // ...
} );
```

---

## Sanitize hints

The server only honors these values for the `sanitize` field property. Anything else is silently coerced to `text`.

| Hint            | PHP sanitizer used                                         |
|-----------------|------------------------------------------------------------|
| `text`          | `sanitize_text_field` (or array thereof if value is array) |
| `textarea`      | `sanitize_textarea_field`                                  |
| `kses_post`     | `wp_kses_post` (allows safe HTML)                          |
| `number` / `float` | `(float)` after numeric check                           |
| `absint`        | `absint`                                                   |
| `bool`          | Stored as `'on'` / `'off'`                                 |
| `email`         | `sanitize_email`                                           |
| `url`           | `esc_url_raw`                                              |
| `key`           | `sanitize_key`                                             |
| `array_of_text` | array of `sanitize_text_field`                             |
| `array_of_int`  | array of `absint`                                          |
| `attachment_id` | `absint`                                                   |
| `color_hex`     | `sanitize_hex_color` (empty string if invalid)             |

If you omit `sanitize`, the registry picks a default from the field `type` (`checkbox→bool`, `number→float`, `attachment→attachment_id`, `color→color_hex`, `multiselect/multicheck→array_of_text`, everything else → `text`).

---

## Section ID rules

JS-registered tabs must use one of these prefixes:

- `_wallet_settings_…` — keeps your tab grouped with the built-in ones
- `wallet_ext_…` — recommended for third-party plugins

The server rejects anything else with a 400 to prevent accidental writes to unrelated WordPress options.

---

## Initialization order

The TeraWallet bundle constructs `window.wooWallet.settings` synchronously as its first action, before mounting the React app. If your script depends on the bundle (e.g. you list `woo-wallet-admin-settings` as a dependency in `wp_enqueue_script`), the registry is guaranteed to exist when your code runs.

If you need to defer (for example, you load via `defer` or async), listen for the ready event:

```js
document.addEventListener( 'wooWallet.settings.ready', function ( evt ) {
    evt.detail.registry.registerTab( { /* ... */ } );
} );
```

---

## API reference

| Method                                        | Description                                                                                  |
|-----------------------------------------------|----------------------------------------------------------------------------------------------|
| `registerTab( descriptor )`                   | Adds a new tab with its own option key. Returns `true` on success, `false` on validation failure (with a `console.error`). |
| `registerField( sectionId, field )`           | Adds a single field to an existing tab (built-in, legacy, or JS-registered).                 |
| `registerFieldType( typeName, Component )`    | Registers a React component for a custom field `type`.                                       |
| `registerIcon( iconName, renderFn )`          | Registers a render function for a named icon.                                                |
| `getTab( id )`                                | Returns a defensive copy of a registered tab descriptor, or `null`.                          |
| `unregisterTab( id )`                         | Removes a previously-registered tab. Useful for hot-reload scenarios and tests.              |
| `isJsRegistered( id )`                        | Returns `true` if `id` was registered via this API.                                          |
| `SANITIZE_HINTS`                              | Array of the accepted sanitize hint strings (for introspection).                             |

---

## PHP-side actions you may hook

| Action                              | When it fires                                                                       |
|-------------------------------------|-------------------------------------------------------------------------------------|
| `woo_wallet_js_section_saved`       | After a JS-registered tab's values are persisted via the `/js-section` endpoint. Receives `$section_id`, `$sanitized`, `$old_values`. |
| `update_option_{section_id}`        | Fires unchanged via WP core because both endpoints call `update_option()`.          |

---

## Earning actions (`WooWalletAction` subclasses)

Since 1.6.2 the **Actions** tab is rendered by the same React `Panel` that powers
General and Credit Options. Action settings live in a single
`_wallet_settings_actions` option row with namespaced keys
(`{action_id}__{field_key}`), e.g. `daily_visits__amount`. The 1.6.2 install
migration copies any pre-existing per-action option rows
(`woo_wallet_daily_visits_settings`, etc.) into the merged option without
deleting them, so a rollback is safe.

Custom action subclasses keep working unchanged: `WooWalletAction::init_settings()`
reads the merged option first and falls back to the legacy
`{$plugin_id}{$id}_settings` row if nothing has been migrated yet.

### Reading an action setting from PHP

```php
$amount = woo_wallet_get_setting( '_wallet_settings_actions', 'daily_visits__amount', 0 );
$enabled_yes_no = woo_wallet_get_setting( '_wallet_settings_actions', 'daily_visits__enabled', 'no' );
```

You can also keep using the existing `$action->settings['amount']` pattern —
that path is back-compat-safe.

### REST surface

- React saves the Actions tab through
  `POST /terawallet/v1/settings/section` with `section_id=_wallet_settings_actions`.
- `POST /terawallet/v1/settings/action` is a deprecated shim that internally
  translates `{action_id, values}` to the equivalent flattened-section save.
  `POST /wc/v3/wallet/settings/action` delegates to the same shim and also
  emits `X-TeraWallet-Deprecated` headers.

---

## Legacy PHP path (deprecated for new code)

The original PHP filters still work — useful only if you cannot ship JavaScript:

- `woo_wallet_settings_sections` — append a section dict.
- `woo_wallet_settings_fields`   — append fields keyed by section ID.

These will keep working indefinitely, but new integrations should use the JS API documented above.
