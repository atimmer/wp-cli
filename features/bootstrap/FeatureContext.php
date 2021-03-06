<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Event\SuiteEvent;

use \WP_CLI\Utils;

require_once 'PHPUnit/Framework/Assert/Functions.php';

require_once __DIR__ . '/../../php/utils.php';

/**
 * Features context.
 */
class FeatureContext extends BehatContext implements ClosuredContextInterface {

	private static $cache_dir;

	private static $db_settings = array(
		'dbname' => 'wp_cli_test',
		'dbuser' => 'wp_cli_test',
		'dbpass' => 'password1'
	);

	private static $additional_args;

	public $variables = array();

	// We cache the results of `wp core download` to improve test performance
	// Ideally, we'd cache at the HTTP layer for more reliable tests
	private static function cache_wp_files() {
		self::$cache_dir = sys_get_temp_dir() . '/wp-cli-test-core-download-cache';

		if ( is_readable( self::$cache_dir . '/wp-config-sample.php' ) )
			return;

		$cmd = Utils\esc_cmd( 'wp core download --force --path=%s', self::$cache_dir );
		Process::create( $cmd )->run_check();
	}

	/**
	 * @BeforeSuite
	 */
	public static function prepare( SuiteEvent $event ) {
		self::cache_wp_files();

		self::$additional_args = array(
			'wp core config' => self::$db_settings,

			'wp core install-network' => array(
				'title' => 'WP CLI Network'
			)
		);
	}

	/**
	 * @AfterScenario
	 */
	public function afterScenario( $event ) {
		if ( !isset( $this->variables['RUN_DIR'] ) )
			return;

		// remove altered WP install, unless there's an error
		if ( $event->getResult() < 4 ) {
			Process::create( Utils\esc_cmd( 'rm -r %s', $this->variables['RUN_DIR'] ) )->run();
		}
	}

	/**
	 * Initializes context.
	 * Every scenario gets it's own context object.
	 *
	 * @param array $parameters context parameters (set them up through behat.yml)
	 */
	public function __construct( array $parameters ) {
		$this->drop_db();
		$this->set_cache_dir();
	}

	public function getStepDefinitionResources() {
		return array( __DIR__ . '/../steps/basic_steps.php' );
	}

	public function getHookDefinitionResources() {
		return array();
	}

	public function replace_variables( $str ) {
		return preg_replace_callback( '/\{([A-Z_]+)\}/', array( $this, '_replace_var' ), $str );
	}

	private function _replace_var( $matches ) {
		$cmd = $matches[0];

		foreach ( array_slice( $matches, 1 ) as $key ) {
			$cmd = str_replace( '{' . $key . '}', $this->variables[ $key ], $cmd );
		}

		return $cmd;
	}

	public function create_run_dir() {
		if ( !isset( $this->variables['RUN_DIR'] ) ) {
			$this->variables['RUN_DIR'] = sys_get_temp_dir() . '/' . uniqid( "wp-cli-test-run-", TRUE );
			mkdir( $this->variables['RUN_DIR'] );
		}
	}

	private function set_cache_dir() {
		$path = sys_get_temp_dir() . '/wp-cli-test-cache';
		Process::create( Utils\esc_cmd( 'mkdir -p %s', $path ) )->run_check();
		$this->variables['CACHE_DIR'] = $path;
	}

	private static function run_sql( $sql ) {
		Utils\run_mysql_query( $sql, array(
			'host' => 'localhost',
			'user' => self::$db_settings['dbuser'],
			'pass' => self::$db_settings['dbpass'],
		) );
	}

	public function create_db() {
		$dbname = self::$db_settings['dbname'];
		self::run_sql( "CREATE DATABASE IF NOT EXISTS $dbname" );
	}

	public function drop_db() {
		$dbname = self::$db_settings['dbname'];
		self::run_sql( "DROP DATABASE IF EXISTS $dbname" );
	}

	public function proc( $command, $assoc_args = array() ) {
		foreach ( self::$additional_args as $start => $additional_args ) {
			if ( 0 === strpos( $command, $start ) ) {
				$assoc_args = array_merge( $additional_args, $assoc_args );
				break;
			}
		}

		if ( !empty( $assoc_args ) )
			$command .= Utils\assoc_args_to_str( $assoc_args );

		return Process::create( $command, $this->variables['RUN_DIR'] );
	}

	public function move_files( $src, $dest ) {
		rename( $this->variables['RUN_DIR'] . "/$src", $this->variables['RUN_DIR'] . "/$dest" );
	}

	public function add_line_to_wp_config( &$wp_config_code, $line ) {
		$token = "/* That's all, stop editing!";

		$wp_config_code = str_replace( $token, "$line\n\n$token", $wp_config_code );
	}

	public function download_wp( $subdir = '' ) {
		$dest_dir = $this->variables['RUN_DIR'] . "/$subdir";

		if ( $subdir ) mkdir( $dest_dir );

		Process::create( Utils\esc_cmd( "cp -r %s/* %s", self::$cache_dir, $dest_dir ) )->run_check();
	}

	public function install_wp( $subdir = '' ) {
		$this->create_db();
		$this->create_run_dir();
		$this->download_wp( $subdir );

		$dbprefix = $subdir ?: 'wp_';
		$this->proc( 'wp core config', compact( 'dbprefix' ) )->run_check( $subdir );

		$install_args = array(
			'url' => 'http://example.com',
			'title' => 'WP CLI Site',
			'admin_email' => 'admin@example.com',
			'admin_password' => 'password1'
		);

		$this->proc( 'wp core install', $install_args )->run_check( $subdir );
	}
}

