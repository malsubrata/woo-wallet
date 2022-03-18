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
                        'args' => array_merge($this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE), array(
                            'email' => array(
                                'required' => true,
                                'type' => 'string',
                                'description' => __('User email address', 'woo-wallet'),
                                'sanitize_callback' => 'sanitize_email',
                                'validate_callback' => 'rest_validate_request_arg',
                                'format' => 'email'
                            ),
                            'type' => array(
                                'required' => true,
                                'type' => 'string',
                                'description' => __('Wallet transaction type.', 'woo-wallet'),
                            ),
                            'amount' => array(
                                'required' => true,
                                'description' => __('Wallet transaction amount.', 'woo-wallet'),
                                'type' => 'number',
                            ),
                            'note' => array(
                                'required' => false,
                                'description' => __('Wallet transaction details.', 'woo-wallet'),
                                'type' => 'string',
                            ),
                        )),
                    ),
                    'schema' => array($this, 'get_public_item_schema'),
                )
        );

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
    /**
     * Collection params for get request.
     * 
     * @return array
     */
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
        $params['per_page'] = array(
            'required' => false,
            'type' => 'number',
            'description' => __('Transactions per page', 'woo-wallet'),
            'validate_callback' => 'rest_validate_request_arg',
            'default' => -1
        );
        $params['page'] = array(
            'required' => false,
            'type' => 'integer',
            'description' => __('Current page', 'woo-wallet'),
            'sanitize_callback' => 'absint',
            'validate_callback' => 'rest_validate_request_arg',
            'default' => 1
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

    /**
     * Check whether a given request has permission to create new transactions.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|boolean
     */
    public function create_item_permissions_check($request) {
        if (!apply_filters('woo_wallet_rest_check_permissions', current_user_can('manage_woocommerce'), 'create', $request)) {
            return new WP_Error('woocommerce_rest_cannot_create', __('Sorry, you are not allowed to create resources.', 'woo-wallet'), array('status' => rest_authorization_required_code()));
        }
        return true;
    }
    /**
     * Get all wallet transactions
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response | WP_Error
     */
    public function get_items($request) {
        //get parameters from request
        $params = $request->get_params();
        $user = get_user_by('email', $params['email']);
        if (!$user) {
            return new WP_Error("terawallet_rest_invalid_email", __('Invalid User.', 'woo-wallet'), array('status' => 404));
        }
        $args = apply_filters('woo_wallet_rest_api_get_items_args', [
            'user_id' => $user->ID,
            'fields' => 'all_with_meta',
            'nocache' => true
        ]);
        if ($params['per_page'] > 0) {
            $offset = ($params['page'] - 1) * $params['per_page'];
            $args['limit'] = "{$offset}, {$params['per_page']}";
        }
        $transactions = get_wallet_transactions($args);
        return new WP_REST_Response($transactions, 200);
    }
    /**
     * Get user wallet balance.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Request | WP_Error
     */
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
    /**
     * Create new wallet transaction.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response | WP_Error
     */
    public function create_item($request) {
        $params = $request->get_params();
        $user = get_user_by('email', $params['email']);
        if (!$user) {
            return new WP_Error("terawallet_rest_invalid_email", __('Invalid User.', 'woo-wallet'), array('status' => 404));
        }
        if (isset($params['type']) && isset($params['amount'])) {
            $note = isset($params['note']) ? $params['note'] : '';
            $transaction_id = false;
            if ('credit' === $params['type']) {
                $transaction_id = woo_wallet()->wallet->credit($user->ID, $params['amount'], $note);
            } else if ('debit' === $params['type']) {
                $transaction_id = woo_wallet()->wallet->debit($user->ID, $params['amount'], $note);
            }
            if($transaction_id){
                return new WP_REST_Response(array('response' => 'success', 'id' => $transaction_id), 200);
            } else{
                return new WP_REST_Response(array('response' => 'error'), 200);
            }
        } else {
            return new WP_REST_Response(array('response' => 'Invalid Request'), 401);
        }
    }

}
