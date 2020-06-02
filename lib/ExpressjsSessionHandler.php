<?php declare(strict_types=1);

namespace GeekJOB;

/**
 * Node.js epress-session compatible handler
 *
 * Created by PhpStorm.
 * User: Alexander Mayorov <major@geekjob.ru>
 * Date: 2019-08-09
 * Time: 06:42
 */
class ExpressjsSessionHandler extends \SessionHandler {
	/**
	 * @var string
	 */
	private
		$secret
	,	$config
	;

	/**
	 * @param array $cfg
	 * @return ExpressjsSessionHandler
	 */
	public static function register(array $cfg): self {
		//ini_set('session.name', $cfg['name']);
		if (!empty($cfg['name'])) session_name($cfg['name']);

		$session_name = session_name();

		// Session cookie exists
		if (isset($_COOKIE[$session_name])) {
			// If enabled secure mode, session ID checks on valid
			if (true === $cfg['secure'] && self::sid_tampered($_COOKIE[$session_name], $cfg['secret']))
				// If tampered - delete session cookie and generate new session ID
				unset($_COOKIE[$session_name]);
			;
		}

		// Create session handler object and register
		return (new self($cfg))();
	}

	/**
	 * Constructor
	 *
	 * @param string $secret The secret defined in your express-session library.
	 */
	public function __construct(array $cfg) {
		// Set session cookie settings
		if (!empty($cfg['cookie'])) {
			$cfg['cookie']['maxage'] = (int)$cfg['cookie']['maxage'];

			session_set_cookie_params(
				$cfg['cookie']['maxage'],
				$cfg['cookie']['path'],
				$cfg['cookie']['domain'],
				$cfg['cookie']['secure'] ??true,
				$cfg['cookie']['httpOnly'] ??true
			);

			if (empty($cfg['cookie']['expires']))
				$cfg['cookie']['expires'] = str_replace(
					'+00:00',
					'Z',
					gmdate('c', time() + $cfg['cookie']['maxage'])
				)
			;
		}

		// Configure storage.
		// A common method of storing sessions is the Radish database.
		// These settings can be made in php.ini for the production version
		if (!empty($cfg['store'])) {
			ini_set('session.save_handler', $cfg['store']['handler']);
			$save_path = $cfg['store']['path'];
			switch ($cfg['store']['handler']) {
				case 'redis':
					if (!empty($cfg['store']['prefix']))
						$save_path .= "?prefix={$cfg['store']['prefix']}"
					;
					break;
				// todo: case 'mongo': break;
			}

			ini_set('session.save_path', $save_path);
			ini_set('session.serialize_handler', 'php_serialize');

			if ($cfg['store']['ttl'] > 0)
				ini_set('session.gc_maxlifetime', (string)$cfg['store']['ttl'])
			;
			if (isset($cfg['cookie']['maxage']))
				ini_set('session.cookie_lifetime',(string)$cfg['cookie']['maxage'])
			;
		}

		$this->secret = (string) $cfg['secret'];
		$this->config = $cfg;
	}

	/**
	 * Register session handler object.
	 * @return $this
	 */
	public function __invoke(): self {
		session_set_save_handler($this, true);
		session_start();
		$this->register_session_cookie();
		return $this;
	}

	/**
	 * Read session data
	 * @link https://php.net/manual/en/sessionhandlerinterface.read.php
	 * @param string $id The session id to read data for.
	 * @return string <p>
	 * Returns an encoded string of the read data.
	 * If nothing was read, it must return an empty string.
	 * Note this value is returned internally to PHP for processing.
	 * </p>
	 * @since 5.4.0
	 */
	public function read($id): string {
		return
			serialize(
				json_decode(
					parent::read($this->idmutator($id)),
					true
				)
			)
		;
	}

	/**
	 * Write session data
	 * @link https://php.net/manual/en/sessionhandlerinterface.write.php
	 * @param string $id The session id.
	 * @param string $data <p>
	 * The encoded session data. This data is the
	 * result of the PHP internally encoding
	 * the $_SESSION superglobal to a serialized
	 * string and passing it as this parameter.
	 * Please note sessions use an alternative serialization method.
	 * </p>
	 * @return bool <p>
	 * The return value (usually TRUE on success, FALSE on failure).
	 * Note this value is returned internally to PHP for processing.
	 * </p>
	 * @since 5.4.0
	 */
	public function write($id, $data): bool {
		return parent::write(
			$this->idmutator($id),
			json_encode(unserialize($data))
		);
	}

	/**
	 * Destroy a session
	 * @link https://www.php.net/manual/en/sessionhandlerinterface.destroy.php
	 * @param string $id The session id.
	 * @return bool <p>
	 * The return value (usually TRUE on success, FALSE on failure).
	 * Note this value is returned internally to PHP for processing.
	 * </p>
	 * @since 5.4.0
	 */
	public function destroy($id): bool {		
		return parent::destroy($this->idmutator($id));
	}

	/**
	 * Generate session ID compatible with node.js express-session
	 *
	 * @return string
	 */
	public function create_sid(): string {
		$sid = parent::create_sid();
		$hmac =
			str_replace('=', '',
				base64_encode(
					hash_hmac('sha256', $sid, $this->secret, true)
				)
			)
		;
		return "s:$sid.$hmac";
	}

	/**
	 * Transforms the session ID that is stored on `$_COOKIE` into the ID used on data base
	 *
	 * @param string $id The session ID that is stored on cookie.
	 * @return string
	 */
	private function idmutator(string $id): string {
		if (preg_match('~^s:(.*?)\.~', $id, $a)) $id = $a[1];
		return $id;
	}

	/**
	 * @param string $id
	 * @param string $signature
	 * @return bool
	 */
	public static function sid_tampered(string $id, string $secret): bool {
		$signature = null;
		if (preg_match('~^s:(.*?)\.(.*)$~', $id, $a)) {
			$id = $a[1];
			$signature = $a[2];
		}
		return $signature !==
			str_replace(
				'=', '',
				base64_encode(
					hash_hmac('sha256', $id, $secret, true)
				)
			)
		;
	}

	/**
	 * Add the session cookie metadata that express-session requires.
	 *
	 * Warning! If this block doesn't work, the Node.js application will fail with an error:
	 *
	 *   node_modules/express-session/session/store.js:87
	 *   var expires = sess.cookie.expires
	 *                            ^
	 *   TypeError: Cannot read property 'expires' of undefined
	 *   at RedisStore.Store.createSession (node_modules/express-session/session/store.js:87:29)
	 *   at node_modules/express-session/index.js:478:15
	 *
	 * @return void
	 */
	public function register_session_cookie(): void {
		if (isset($_SESSION['cookie'])) return;
		$cookie = session_get_cookie_params();
		$_SESSION['cookie'] = [
			'originalMaxAge' => $this->config['cookie']['maxage'],
			'httpOnly' => $cookie['httponly'],
			'domain' => $cookie['domain'],
			'path' => $cookie['path'],
		];
		if (!empty($this->config['cookie']['expires']))
			$_SESSION['cookie']['expires'] = $this->config['cookie']['expires']
		;
	}

}


//EOF//
