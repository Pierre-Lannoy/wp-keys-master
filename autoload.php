<?php
/**
 * Autoload for Keys Master.
 *
 * @package Bootstrap
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

spl_autoload_register(
	function ( $class ) {
		$classname = $class;
		$filepath  = __DIR__ . '/';
		if ( strpos( $classname, 'KeysMaster\\' ) === 0 ) {
			while ( strpos( $classname, '\\' ) !== false ) {
				$classname = substr( $classname, strpos( $classname, '\\' ) + 1, 1000 );
			}
			$filename = 'class-' . str_replace( '_', '-', strtolower( $classname ) ) . '.php';
			if ( strpos( $class, 'KeysMaster\System\\' ) === 0 ) {
				$filepath = POKM_INCLUDES_DIR . 'system/';
			}
			if ( strpos( $class, 'KeysMaster\Plugin\Feature\\' ) === 0 ) {
				$filepath = POKM_INCLUDES_DIR . 'features/';
			} elseif ( strpos( $class, 'KeysMaster\Plugin\Integration\\' ) === 0 ) {
				$filepath = POKM_INCLUDES_DIR . 'integrations/';
			} elseif ( strpos( $class, 'KeysMaster\Plugin\\' ) === 0 ) {
				$filepath = POKM_INCLUDES_DIR . 'plugin/';
			} elseif ( strpos( $class, 'KeysMaster\API\\' ) === 0 ) {
				$filepath = POKM_INCLUDES_DIR . 'api/';
			}
			if ( strpos( $class, 'KeysMaster\Library\\' ) === 0 ) {
				$filepath = POKM_VENDOR_DIR;
			}
			if ( strpos( $filename, '-public' ) !== false ) {
				$filepath = POKM_PUBLIC_DIR;
			}
			if ( strpos( $filename, '-admin' ) !== false ) {
				$filepath = POKM_ADMIN_DIR;
			}
			$file = $filepath . $filename;
			if ( file_exists( $file ) ) {
				include_once $file;
			}
		}
	}
);
