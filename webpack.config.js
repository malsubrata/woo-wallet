const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const RtlCssPlugin = require('rtlcss-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');

// Remove SASS rule from the default config so we can define our own.
const defaultRules = defaultConfig.module.rules.filter( ( rule ) => {
	return String( rule.test ) !== String( /\.(sc|sa)ss$/ );
} );

const wcDepMap = {
	'@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
	'@woocommerce/price-format': [ 'wc', 'priceFormat' ],
	'@woocommerce/settings'       : ['wc', 'wcSettings']
};

const wcHandleMap = {
	'@woocommerce/blocks-registry': 'wc-blocks-registry',
	'@woocommerce/price-format': 'wc-price-format',
	'@woocommerce/settings'       : 'wc-settings'
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
			'sass-loader'
		]
	}
];

const wcBuildConfig = {
    ...defaultConfig,
    entry: {
        'payment-method/index': './src/payment-method/index.js',
        'partial-payment/index': './src/partial-payment/index.js',
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
        new WooCommerceDependencyExtractionWebpackPlugin({
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
		'admin/settings': './src/admin/settings.js',
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
            new CssMinimizerPlugin() // Minify CSS files
        ],
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: '[name].css',
        }),
        new RtlCssPlugin({
            filename: '[name].rtl.css',
        })
    ]
};

module.exports = [wcBuildConfig, vanillaAssetsConfig];
