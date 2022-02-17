<?php

/**
 * REST API Wallet controller
 *
 * Handles requests to the /wallet endpoint.
 *
 * @author   Subrata Mal
 * @category API
 * @since   1.3.23
 */
defined('ABSPATH') || exit;

/**
 * REST API TeraWallet controller class.
 *
 * @extends WC_REST_Controller
 */
class WC_REST_TeraWallet_V3_Controller extends WC_REST_Controller {

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wc/v3';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'wallet';

    /**
     * Register the routes for customers.
     */
    public function register_routes() {
        register_rest_route(
                $this->namespace,
                '/' . $this->rest_base,
                array(
                    array(
                        'methods' => WP_REST_Server::READABLE,
                        'callback' => array($this, 'get_items'),
                        'permission_callback' => array($this, 'get_items_permissions_check'),
                        'args' => $this->get_collection_params(),
                    ),
                    array(
                        'methods' => WP_REST_Server::CREATABLE,
                        'callback' => array($this, 'create_item'),
                        'permission_callback' => array($this, 'create_item_permissions_check'),
                        'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
                    ),
                    'schema' => array($this, 'get_public_item_schema'),
                )
        );
//
//        register_rest_route(
//                $this->namespace,
//                '/' . $this->rest_base . '/(?P<id>[\d]+)',
//                array(
//                    'args' => array(
//                        'id' => array(
//                            'description' => __('Unique identifier for the resource.', 'woocommerce'),
//                            'type' => 'integer',
//                        ),
//                    ),
//                    array(
//                        'methods' => WP_REST_Server::READABLE,
//                        'callback' => array($this, 'get_item'),
//                        'permission_callback' => array($this, 'get_item_permissions_check'),
//                        'args' => array(
//                            'context' => $this->get_context_param(
//                                    array(
//                                        'default' => 'view',
//                                    )
//                            ),
//                        ),
//                    ),
//                    array(
//                        'methods' => WP_REST_Server::EDITABLE,
//                        'callback' => array($this, 'update_item'),
//                        'permission_callback' => array($this, 'update_item_permissions_check'),
//                        'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
//                    ),
//                    array(
//                        'methods' => WP_REST_Server::DELETABLE,
//                        'callback' => array($this, 'delete_item'),
//                        'permission_callback' => array($this, 'delete_item_permissions_check'),
//                        'args' => array(
//                            'force' => array(
//                                'default' => false,
//                                'description' => __('Whether to bypass trash and force deletion.', 'woocommerce'),
//                                'type' => 'boolean',
//                            ),
//                        ),
//                    ),
//                    'schema' => array($this, 'get_public_item_schema'),
//                )
//        );

        register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/balance',
                array(
                    array(
                        'methods' => WP_REST_Server::READABLE,
                        'callback' => array($this, 'get_balance'),
                        'permission_callback' => array($this, 'get_items_permissions_check'),
                        'args' => array_merge($this->get_endpoint_args_for_item_schema(WP_REST_Server::READABLE), array(
                            'email' => array(
                                'required' => true,
                                'type' => 'string',
                                'description' => __('User email address', 'woo-wallet'),
                                'sanitize_callback' => 'sanitize_email',
                                'validate_callback' => 'rest_validate_request_arg',
                                'format' => 'email'
                            )
                        )),
                    ),
                    'schema' => array($this, 'get_public_batch_schema'),
                )
        );
    }

    public function get_collection_params() {
        $params = parent::get_collection_params();
        $params['email'] = array(
            'required' => true,
            'type' => 'string',
            'description' => __('User email address', 'woo-wallet'),
            'sanitize_callback' => 'sanitize_email',
            'validate_callback' => 'rest_validate_request_arg',
            'format' => 'email'
        );
        return $params;
    }

    /**
     * Check whether a given request has permission to read transactions.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|boolean
     */
    public function get_items_permissions_check($request) {
        if (!apply_filters('woo_wallet_rest_check_permissions', current_user_can('manage_woocommerce'), 'read', $request)) {
            return new WP_Error('woocommerce_rest_cannot_view', __('Sorry, you cannot list resources.', 'woo-wallet'), array('status' => rest_authorization_required_code()));
        }

        return true;
    }
    
    public function get_items($request) {
        //get parameters from request
        $params = $request->get_params();
        $user = get_user_by('email', $params['email']);
        if (!$user) {
            return new WP_Error("terawallet_rest_invalid_email", __('Invalid User.', 'woo-wallet'), array('status' => 404));
        }
        $transactions = get_wallet_transactions(array('user_id' => $user->ID, 'fields' => 'all_with_meta', 'nocache' => true));
        return new WP_REST_Response($transactions, 200);
    }

    public function get_balance($request) {
        //get parameters from request
        $params = $request->get_params();
        $user = get_user_by('email', $params['email']);
        if (!$user) {
            return new WP_Error("terawallet_rest_invalid_email", __('Invalid User.', 'woo-wallet'), array('status' => 404));
        }
        $balance = woo_wallet()->wallet->get_wallet_balance($user->ID, 'edit');
        return new WP_REST_Response(['balance' => $balance, 'currency' => get_woocommerce_currency()], 200);
    }

}
