const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const RtlCssPlugin = require('rtlcss-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');

// Remove SASS rule from the default config so we can define our own.
const defaultRules = defaultConfig.module.rules.filter((rule) => {
    return String(rule.test) !== String(/\.(sc|sa)ss$/);
});

// WooCommerce external mappings – used by the WP dependency extraction plugin
// directly, avoiding the broken @woocommerce/dependency-extraction-webpack-plugin
// which has an incompatible method-signature override with the current WP plugin.
const wcDepMap = {
    '@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
    '@woocommerce/blocks-checkout': ['wc', 'blocksCheckout'],
    '@woocommerce/blocks-components': ['wc', 'blocksComponents'],
    '@woocommerce/price-format': ['wc', 'priceFormat'],
    '@woocommerce/settings': ['wc', 'wcSettings']
};

const wcHandleMap = {
    '@woocommerce/blocks-registry': 'wc-blocks-registry',
    '@woocommerce/blocks-checkout': 'wc-blocks-checkout',
    '@woocommerce/blocks-components': 'wc-blocks-components',
    '@woocommerce/price-format': 'wc-price-format',
    '@woocommerce/settings': 'wc-settings'
};

const requestToExternal = (request) => wcDepMap[request];
const requestToHandle = (request) => wcHandleMap[request];

const sharedRules = [
    ...defaultRules,
    {
        test: /\.(sc|sa)ss$/,
        exclude: /node_modules/,
        use: [
            MiniCssExtractPlugin.loader,
            {
                loader: 'css-loader',
                options: { importLoaders: 1 }
            },
            'postcss-loader',
            {
                loader: 'sass-loader',
                options: {
                    sassOptions: {
                        silenceDeprecations: ['legacy-js-api'],
                    },
                },
            }
        ]
    }
];

const wcBuildConfig = {
    ...defaultConfig,
    entry: {
        'payment-method/index': './src/payment-method/index.js',
        'partial-payment/index': './src/partial-payment/index.js',
        'admin/settings/index': './src/admin/settings/index.js',
    },
    output: {
        path: path.resolve(__dirname, 'build'),
        filename: '[name].js',
    },
    module: {
        ...defaultConfig.module,
        rules: sharedRules
    },
    plugins: [
        ...defaultConfig.plugins.filter(
            (plugin) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
        ),
        new DependencyExtractionWebpackPlugin({
            requestToExternal,
            requestToHandle
        }),
        new MiniCssExtractPlugin({
            filename: '[name].css'
        })
    ]
};

const vanillaAssetsConfig = {
    ...defaultConfig,
    entry: {
        'admin/actions': './src/admin/actions.js',
        'admin/order': './src/admin/order.js',
        'admin/product': './src/admin/product.js',
        // 'admin/settings' replaced by React bundle in wcBuildConfig above
        'admin/export': './src/admin/export.js',
        'admin/main': './src/admin/main.js',

        'frontend/main': './src/frontend/main.js',
    },
    output: {
        path: path.resolve(__dirname, 'build'),
        filename: '[name].js',
    },
    module: {
        ...defaultConfig.module,
        rules: sharedRules
    },
    optimization: {
        minimize: true,
        minimizer: [
            '...',
            new CssMinimizerPlugin()
        ],
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: '[name].css',
        }),
        new RtlCssPlugin({
            filename: '[name]-rtl.css',
        }),
        // Generate .asset.php files for proper WordPress dependency versioning
        new DependencyExtractionWebpackPlugin(),
    ]
};

module.exports = [wcBuildConfig, vanillaAssetsConfig];
