<?php
/**
 * Security audit against SPEC section 8, run over shipped code only.
 * Each rule is a grep with a justification; findings are printed for review.
 */

$root = str_replace( '\\', '/', dirname( __DIR__ ) );

$files = array();
$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );

foreach ( $it as $f ) {
	$p = str_replace( '\\', '/', (string) $f );
	if ( ! in_array( strtolower( pathinfo( $p, PATHINFO_EXTENSION ) ), array( 'php', 'js' ), true ) ) {
		continue;
	}
	foreach ( array( '/vendor/', '/node_modules/', '/tests/', '/bin/', '/docs/', '/build/' ) as $x ) {
		if ( false !== strpos( $p, $x ) ) {
			continue 2;
		}
	}
	$files[ ltrim( substr( $p, strlen( $root ) ), '/' ) ] = file_get_contents( $p );
}

$rules = array(
	'8.1 dangerous constructs'      => '/(?<![\w$>])(eval|create_function|assert|extract|system|exec|shell_exec|passthru|proc_open|popen)\s*\(/i',
	'8.2 variable functions'        => '/\$\w+\s*\(\s*\)|call_user_func\s*\(\s*\$/',
	'8.3 direct database access'    => '/\$wpdb|->query\s*\(|mysqli_|SELECT\s+.*\s+FROM\s/i',
	'8.4 file writes'               => '/\b(file_put_contents|fwrite|fputs|unlink|rename|mkdir|rmdir|touch|chmod|copy)\s*\(/',
	'8.5 outbound requests'         => '/wp_remote_|curl_init|curl_exec|fsockopen|stream_socket_client/',
	'8.6 remote file reads'         => '/(file_get_contents|fopen|include|require)\s*\(\s*[\'"]?https?:/i',
	'8.7 permission_callback true'  => '/permission_callback[\'"]?\s*(=>|:)\s*[\'"]?__return_true/',
	'8.8 unserialize'               => '/(?<![\w$>])unserialize\s*\(/',
	'8.9 base64_decode'             => '/base64_decode\s*\(/',
	'8.10 superglobal write'        => '/\$_(GET|POST|REQUEST|COOKIE|SERVER)\s*\[[^\]]*\]\s*=[^=]/',
	'8.11 innerHTML assignment'     => '/\.innerHTML\s*=/',
	'8.12 document.write'           => '/document\.write\s*\(/',
	'8.13 JS eval / new Function'   => '/(?<![\w.])eval\s*\(|new\s+Function\s*\(/',
	'8.14 jQuery .html() with data' => '/\.html\s*\(\s*[^)\'"]/',
);

$total = 0;

foreach ( $rules as $label => $pattern ) {
	$hits = array();

	foreach ( $files as $rel => $src ) {
		if ( ! preg_match_all( $pattern, $src, $m, PREG_OFFSET_CAPTURE ) ) {
			continue;
		}

		foreach ( $m[0] as $hit ) {
			$line = substr_count( substr( $src, 0, $hit[1] ), "\n" ) + 1;
			$text = trim( explode( "\n", substr( $src, $hit[1] - 60 < 0 ? 0 : $hit[1] - 60, 160 ) )[0] );
			$hits[] = sprintf( '%s:%d  %s', $rel, $line, substr( $text, 0, 90 ) );
		}
	}

	$total += count( $hits );
	printf( "%-34s %s\n", $label, $hits ? count( $hits ) . ' HIT(S)' : 'clean' );

	foreach ( $hits as $h ) {
		echo "    $h\n";
	}
}

// Positive checks: things that MUST be present.
echo "\n--- required protections ---\n";

$required = array(
	'ABSPATH guard on every shipped PHP file' => function ( $files ) {
		$bad = array();
		foreach ( $files as $rel => $src ) {
			if ( 'js' === pathinfo( $rel, PATHINFO_EXTENSION ) ) {
				continue;
			}
			if ( false === strpos( $src, "defined( 'ABSPATH' ) || exit;" )
				&& false === strpos( $src, "defined( 'WP_UNINSTALL_PLUGIN' ) || exit;" ) ) {
				$bad[] = $rel;
			}
		}
		return $bad;
	},
	'every register_rest_route has a permission_callback' => function ( $files ) {
		$bad = array();
		foreach ( $files as $rel => $src ) {
			$routes = preg_match_all( '/register_rest_route\s*\(/', $src );
			$perms  = preg_match_all( '/permission_callback/', $src );
			if ( $routes > 0 && $perms < $routes ) {
				$bad[] = "$rel ($routes routes, $perms permission callbacks)";
			}
		}
		return $bad;
	},
	'every form-handling branch verifies a nonce' => function ( $files ) {
		$bad = array();
		foreach ( $files as $rel => $src ) {
			if ( false === strpos( $src, '$_POST' ) ) {
				continue;
			}
			if ( false === strpos( $src, 'check_admin_referer' )
				&& false === strpos( $src, 'wp_verify_nonce' )
				&& false === strpos( $src, 'check_ajax_referer' ) ) {
				$bad[] = $rel;
			}
		}
		return $bad;
	},
	'no unescaped echo of a variable' => function ( $files ) {
		$bad = array();
		foreach ( $files as $rel => $src ) {
			if ( 'php' !== pathinfo( $rel, PATHINFO_EXTENSION ) ) {
				continue;
			}
			// echo $var  or  echo "$var"  without an esc_ / wp_kses / wp_json / implode of escaped
			if ( preg_match_all( '/echo\s+\$[a-z_]/i', $src, $m, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $m[0] as $hit ) {
					$line = substr_count( substr( $src, 0, $hit[1] ), "\n" ) + 1;
					$bad[] = "$rel:$line";
				}
			}
		}
		return $bad;
	},
);

foreach ( $required as $label => $check ) {
	$bad = $check( $files );
	printf( "%-52s %s\n", $label, $bad ? count( $bad ) . ' PROBLEM(S)' : 'OK' );
	foreach ( $bad as $b ) {
		echo "    $b\n";
	}
	$total += count( $bad );
}

echo "\n", 0 === $total ? "AUDIT CLEAN\n" : "$total item(s) to review\n";
echo 'files audited: ' . count( $files ) . "\n";
