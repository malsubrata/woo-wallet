# Extending the TeraWallet Reports page

TeraWallet's **Reports** page (WP Admin → TeraWallet → Reports) is a plain
server-rendered PHP page that shows store-wide wallet **liability** metrics. It
is built entirely from two registries — a metric-card registry and a tab
registry — so a Pro/third-party plugin can inject cards, whole tabs, and extra
API data using only PHP hooks, with **no JS build step**.

The free plugin registers its own cards and tabs through these same hooks, so
the free and Pro extension paths are identical and already proven.

The page is **read-only**. Nothing here writes to the ledger.

---

## At a glance

| Hook | Type | Purpose |
| --- | --- | --- |
| `woo_wallet_reports_metrics` | filter | Add / replace metric cards on the Summary tab. |
| `woo_wallet_reports_summary_data` | filter | Add fields to the REST `/admin/reports/summary` payload. |
| `woo_wallet_reports_query_args` | filter | Inject date-range / segment args (threaded to the data service + cache key). |
| `woo_wallet_reports_tabs` | filter | Add a tab or replace a locked placeholder tab. |
| `woo_wallet_reports_render_tab_{tab_id}` | action | Render the body of a tab. |
| `woo_wallet_reports_before_summary` | action | Output before the Summary card grid. |
| `woo_wallet_reports_after_summary` | action | Output after the Summary card grid. |
| `woo_wallet_reports_page_top` | action | Output at the very top of the page. |
| `woo_wallet_reports_page_bottom` | action | Output at the very bottom of the page. |
| `woo_wallet_reports_capability` | filter | Capability required to view the page and call the REST endpoint. |
| `woo_wallet_reports_cache_ttl` | filter | Transient TTL (seconds) for the summary payload. |
| `woo_wallet_reports_category_labels` | filter | Friendly labels for the liability-composition `category` slugs. |

The free plugin ships **locked placeholder** cards and tabs for three Pro-only
reports — **Breakage**, **Aging**, **Trend over time** (ids `breakage`,
`aging`, `trend`). Pro replaces each by registering a card/tab with the **same
id**.

---

## Data / metrics

### `woo_wallet_reports_metrics`

```php
apply_filters( 'woo_wallet_reports_metrics', array $metrics, array $args );
```

Fires when the Summary tab assembles its cards. Each entry is an array:

| Key | Required | Meaning |
| --- | --- | --- |
| `id` | yes | Unique card id. Re-use a free id to **replace** that card. |
| `label` | yes | Card heading. |
| `value` | yes* | Pre-formatted scalar string shown as the card value. |
| `raw` | no | The underlying scalar/array (handy for `render_callback`). |
| `headline` | no | `true` renders a full-width emphasised card. |
| `pro` | no | `true` renders a locked "Available in TeraWallet Pro" card. |
| `render_callback` | no | `callable( $metric, $context )` that echoes custom card body instead of `value`. |

\* `value` is ignored when `pro` or `render_callback` is set.

`$args` is the (filtered) query-args array — see `woo_wallet_reports_query_args`.

### `woo_wallet_reports_summary_data`

```php
apply_filters( 'woo_wallet_reports_summary_data', array $data, array $args );
```

Applied to the REST payload returned by
`GET /terawallet/v1/admin/reports/summary` **before** it is sent, so Pro can add
extra fields to the API response. The free payload contains: `base_currency`,
`total_liability`, `positive_wallets`, `lifetime_credited`, `lifetime_debited`,
`composition` (array of `{ slug, label, amount }`), `generated_at`.

### `woo_wallet_reports_query_args`

```php
apply_filters( 'woo_wallet_reports_query_args', array $args );
```

Threaded into the data service and the transient cache key, so Pro can add a
date range or customer segment. The free queries are store-wide and ignore
these args; Pro consumes them in its own metric callbacks (or by short-circuiting
the cache via a distinct key).

---

## Tabs

### `woo_wallet_reports_tabs`

```php
apply_filters( 'woo_wallet_reports_tabs', array $tabs, string $current );
```

`$tabs` is keyed by tab id; each value is `array( 'id', 'label', 'locked' => bool )`.
Add a new tab, or **replace a locked placeholder** by registering the same id
without `locked`.

### `woo_wallet_reports_render_tab_{tab_id}`

```php
do_action( "woo_wallet_reports_render_tab_{$tab_id}", array $context );
```

Fires to render the body of the currently active tab. `$context` contains
`args`, `current_tab`, and `data` (the `Woo_Wallet_Reports_Data` service).

---

## Render injection points

```php
do_action( 'woo_wallet_reports_page_top' );
do_action( 'woo_wallet_reports_before_summary', array $context );
do_action( 'woo_wallet_reports_after_summary',  array $context );
do_action( 'woo_wallet_reports_page_bottom' );
```

---

## Capability

```php
apply_filters( 'woo_wallet_reports_capability', 'manage_woocommerce' );
```

Applied wherever the page **and** the REST endpoint check permissions. Return a
different capability to widen/narrow access consistently in both places.

---

## Full example — a Pro "Breakage" card and tab

Drop this in a mu-plugin or your Pro plugin. It (a) replaces the locked
**Breakage** card with a real value, (b) replaces the locked **Breakage** tab
with real content, and (c) adds a field to the REST payload. No build step.

```php
<?php
/**
 * Plugin Name: TeraWallet Reports — Breakage (example)
 */

// (a) Replace the locked "breakage" card with a real metric.
add_filter(
	'woo_wallet_reports_metrics',
	function ( $metrics, $args ) {
		foreach ( $metrics as &$metric ) {
			if ( 'breakage' === $metric['id'] ) {
				$metric['pro']   = false;                 // unlock it
				$metric['label'] = __( 'Estimated breakage', 'my-pro' );
				$metric['value'] = wp_strip_all_tags( wc_price( my_pro_calc_breakage( $args ) ) );
			}
		}
		unset( $metric );
		return $metrics;
	},
	20, // run after free's default cards (priority 10)
	2
);

// (b) Replace the locked "breakage" tab + render its body.
add_filter(
	'woo_wallet_reports_tabs',
	function ( $tabs ) {
		$tabs['breakage'] = array(
			'id'    => 'breakage',
			'label' => __( 'Breakage', 'my-pro' ),
		); // no 'locked' => real tab
		return $tabs;
	},
	20,
	2
);

add_action(
	'woo_wallet_reports_render_tab_breakage',
	function ( $context ) {
		$total = my_pro_calc_breakage( $context['args'] );
		echo '<h2>' . esc_html__( 'Breakage', 'my-pro' ) . '</h2>';
		echo '<p>' . esc_html(
			sprintf(
				/* translators: %s formatted amount */
				__( 'Estimated unredeemed balance: %s', 'my-pro' ),
				wp_strip_all_tags( wc_price( $total ) )
			)
		) . '</p>';
	}
);

// (c) Add a field to the REST summary payload.
add_filter(
	'woo_wallet_reports_summary_data',
	function ( $data, $args ) {
		$data['breakage'] = my_pro_calc_breakage( $args );
		return $data;
	},
	10,
	2
);

function my_pro_calc_breakage( $args ) {
	return 0.0; // your logic here
}
```

After activating, the **Breakage** card shows a real number, the **Breakage**
tab is no longer locked and renders your content, and the REST endpoint returns
a `breakage` field — all with zero JavaScript and no build.
