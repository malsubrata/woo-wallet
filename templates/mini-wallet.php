<?php

/**
 * The Template for mini wallet
 *
 * This template can be overridden by copying it to yourtheme/wc-wallet/mini-wallet.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author 	Subrata Mal
 * @version     1.0.5
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
$title = __('Current wallet balance', 'woo-wallet');
$menu_item = '<li class="right"><a class="woo-wallet-menu-contents" href="' . esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_endpoint', 'woo-wallet'))) . '" title="' . $title . '">';
$menu_item .= '<img style="width:16px;height:16px;float:left;margin:4px;" src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/PjxzdmcgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDMzNC44NzcgMzM0Ljg3NyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgMzM0Ljg3NyAzMzQuODc3OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PGc+PHBhdGggZD0iTTMzMy4xOTYsMTU1Ljk5OWgtMTYuMDY3VjgyLjA5YzAtMTcuNzE5LTE0LjQxNS0zMi4xMzQtMzIuMTM0LTMyLjEzNGgtMjEuNzYxTDI0MC45NjUsOS45MTdDMjM3LjU3MSwzLjc5OCwyMzEuMTEyLDAsMjI0LjEwNywwYy0zLjI2NSwwLTYuNTA0LDAuODQyLTkuMzY0LDIuNDI5bC04NS40NjQsNDcuNTI2SDMzLjgxNWMtMTcuNzE5LDAtMzIuMTM0LDE0LjQxNS0zMi4xMzQsMzIuMTM0djIyMC42NTNjMCwxNy43MTksMTQuNDE1LDMyLjEzNCwzMi4xMzQsMzIuMTM0aDI1MS4xOGMxNy43MTksMCwzMi4xMzQtMTQuNDE1LDMyLjEzNC0zMi4xMzR2LTY0LjgwMmgxNi4wNjdWMTU1Ljk5OXogTTI4NC45OTUsNjIuODA5YzkuODk3LDAsMTcuOTgyLDcuNTE5LDE5LjA2OCwxNy4xNGgtMjQuMTUybC05LjUyNS0xNy4xNEgyODQuOTk1eiBNMjIwLjk5NiwxMy42NjNjMy4wMTQtMS42OSw3LjA3LTAuNTA4LDguNzM0LDIuNDk0bDM1LjQ3Niw2My43ODZIMTAxLjc5OEwyMjAuOTk2LDEzLjY2M3ogTTMwNC4yNzUsMzAyLjc0MmMwLDEwLjYzLTguNjUxLDE5LjI4MS0xOS4yODEsMTkuMjgxSDMzLjgxNWMtMTAuNjMsMC0xOS4yODEtOC42NTEtMTkuMjgxLTE5LjI4MVY4Mi4wOWMwLTEwLjYzLDguNjUxLTE5LjI4MSwxOS4yODEtMTkuMjgxaDcyLjM1M0w3NS4zNDUsNzkuOTVIMzcuODMyYy0zLjU1NCwwLTYuNDI3LDIuODc5LTYuNDI3LDYuNDI3czIuODczLDYuNDI3LDYuNDI3LDYuNDI3aDE0LjM5NmgyMzQuODNoMTcuMjE3djYzLjIwMWgtNDYuOTk5Yy0yMS44MjYsMC0zOS41ODksMTcuNzY0LTM5LjU4OSwzOS41ODl2Mi43NjRjMCwyMS44MjYsMTcuNzY0LDM5LjU4OSwzOS41ODksMzkuNTg5aDQ2Ljk5OVYzMDIuNzQyeiBNMzIwLjM0MiwyMjUuMDg3aC0zLjIxM2gtNTkuODUzYy0xNC43NDMsMC0yNi43MzYtMTEuOTkyLTI2LjczNi0yNi43MzZ2LTIuNzY0YzAtMTQuNzQzLDExLjk5Mi0yNi43MzYsMjYuNzM2LTI2LjczNmg1OS44NTNoMy4yMTNWMjI1LjA4N3ogTTI3Ni45NjEsMTk3LjQ5N2MwLDcuODQxLTYuMzUsMTQuMTktMTQuMTksMTQuMTljLTcuODQxLDAtMTQuMTktNi4zNS0xNC4xOS0xNC4xOXM2LjM1LTE0LjE5LDE0LjE5LTE0LjE5QzI3MC42MTIsMTgzLjMwNiwyNzYuOTYxLDE4OS42NjIsMjc2Ljk2MSwxOTcuNDk3eiIvPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48L3N2Zz4=" /> ';
$menu_item .= woo_wallet()->wallet->get_wallet_balance(get_current_user_id());
$menu_item .= '</a></li>';
echo $menu_item;
