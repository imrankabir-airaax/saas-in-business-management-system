<?php
//Begin Really Simple Security key
define('RSSSL_KEY', 'EFBLLB21LioHuVCJK39HgkmsRM0JnOtVAMe0iD0dRE97QZuBLZY4ELj8MxD5g0vD');
//END Really Simple Security key
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'nxgtzvqa_wp515' );

/** Database username */
define( 'DB_USER', 'nxgtzvqa_wp515' );

/** Database password */
define( 'DB_PASSWORD', '-N65ay]S.p0g!9(1' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'jkkcqyxx68qrphx41drfsxsbbeqx0eq90qxorqcw1qpnhot8anf8yfrxgouorfea' );
define( 'SECURE_AUTH_KEY',  '5jzbhgtrin6msjjhwnr6s3sw2rooyntfwivsnwxlbjgu670wwyx9jh6qymc2g81f' );
define( 'LOGGED_IN_KEY',    'jg3wpotcimkynfjilyilp6b6syjwztggqburdatortkljrdtdfinn1quzrjkgbbc' );
define( 'NONCE_KEY',        'mwoi3h7hpqrysubyvz8xspxoja7yckd8uahfx55vmt2bu8zlbhrjf9dymnw1adiy' );
define( 'AUTH_SALT',        '8a2wb3tjjrkbgrd194ieuq49uqeakkb3yysn2ce06ejk1yhua88xw4fa9tgvjdo9' );
define( 'SECURE_AUTH_SALT', 'jivxrv0mpburhzj3bjxir6yhtbfaee7sljdqeeguianpbo8z0fau06dnhv2ifhxv' );
define( 'LOGGED_IN_SALT',   'f1esqjlsl9plimyeoadvampzq69swaovuh2bs5uzpzalllvf1heu7rg6zcmxkfff' );
define( 'NONCE_SALT',       'izfythqqekvvdfnalismlzlzxmvcr21i8yhvisf3nqkbftbojzkafkwl9mx9kglt' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp0h_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
