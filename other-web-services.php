<?php

/*
 * Queue up a message for IRC API,
 * or alternatively empty the queue and
 * return its contents.
 */
function vipgoci_irc_api_alert_queue(
	$message = null,
	$dump = false
) {
	static $msg_queue = array();

	if ( true === $dump ) {
		$msg_queue_tmp = $msg_queue;

		$msg_queue = array();

		return $msg_queue_tmp;
	}

	$msg_queue[] = $message;
}

/**
 * Remove any sections found in $message string bounded between the
 * VIPGOCI_IRC_IGNORE_STRING_START and VIPGOCI_IRC_IGNORE_STRING_END
 * constants.
 *
 * @param string $message Message string to filter.
 *
 * @return string Message with ignorable strings removed.
 */
function vipgoci_irc_api_filter_ignorable_strings(
	string $message
) :string {
	do {
		$ignore_section_start = strpos( $message, VIPGOCI_IRC_IGNORE_STRING_START );

		if ( false !== $ignore_section_start ) {
			/*
			 * Ensure the end mark is relative to the start mark.
			 * This is so we can process multiple such marks in one string.
			 */
			$ignore_section_end = strpos(
				$message,
				VIPGOCI_IRC_IGNORE_STRING_END,
				$ignore_section_start
			);
		} else {
			$ignore_section_end = false;
		}

		if (
			( false === $ignore_section_start ) ||
			( false === $ignore_section_end )
		) {
			// Neither string was found, stop processing here.
			continue;
		}

		if ( $ignore_section_end > $ignore_section_start ) {
			// End mark should always come after start mark.
			$message = substr_replace(
				$message,
				'',
				$ignore_section_start,
				( $ignore_section_end + strlen( VIPGOCI_IRC_IGNORE_STRING_END ) ) -
					$ignore_section_start
			);
		} elseif ( $ignore_section_end <= $ignore_section_start ) {
			// Invalid usage.
			vipgoci_sysexit(
				'Incorrect usage of VIPGOCI_IRC_IGNORE_STRING_START and VIPGOCI_IRC_IGNORE_STRING_END; former should be placed before the latter',
				array(
					'message' => $message,
				)
			);
		}
	} while (
		( false !== $ignore_section_start ) &&
		( false !== $ignore_section_end )
	);

	return $message;
}

/**
 * Clean IRC ignorable constants away from message specified.
 *
 * Useful for functions submitting messages to GitHub, were the
 * constants should not be part of the HTML submitted.
 *
 * @param string $message Message to process.
 *
 * @return string Message, with constants removed (if any).
 */
function vipgoci_irc_api_clean_ignorable_constants(
	string $message
) :string {
	return str_replace(
		array(
			VIPGOCI_IRC_IGNORE_STRING_START,
			VIPGOCI_IRC_IGNORE_STRING_END,
		),
		array(
			'',
			'',
		),
		$message
	);
}

/*
 * Make messages in IRC queue unique, but add
 * a prefix to those messages that were not unique
 * indicating how many they were.
 */
function vipgoci_irc_api_alert_queue_unique( array $msg_queue ) {
	$msg_queue_unique = array_unique(
		$msg_queue
	);

	/*
	 * If all messages were unique,
	 * nothing more to do.
	 */
	if (
		count( $msg_queue ) ===
		count( $msg_queue_unique )
	) {
		return $msg_queue;
	}

	/*
	 * Not all unique, count values
	 */
	$msg_queue_count = array_count_values(
		$msg_queue
	);

	$msg_queue_new = array();

	/*
	 * Add prefix where needed.
	 */
	foreach( $msg_queue_count as $msg => $cnt ) {
		$msg_prefix = '';

		if ( $cnt > 1 ) {
			$msg_prefix = '(' . $cnt . 'x) ';
		}

		$msg_queue_new[] = $msg_prefix . $msg;
	}

	return $msg_queue_new;
}

/**
 * Empty IRC message queue and send off
 * to the IRC API.
 *
 * @codeCoverageIgnore
 */
function vipgoci_irc_api_alerts_send(
	$irc_api_url,
	$irc_api_token,
	$botname,
	$channel
) {
	// Get IRC message queue.
	$msg_queue = vipgoci_irc_api_alert_queue(
		null, true
	);

	// Filter away removable strings.
	$msg_queue = array_map(
		'vipgoci_irc_api_filter_ignorable_strings',
		$msg_queue
	);

	// Ensure all strings we log are unique; if not make unique and add prefix.
	$msg_queue = vipgoci_irc_api_alert_queue_unique(
		$msg_queue
	);

	vipgoci_log(
		'Sending messages to IRC API',
		array(
			'msg_queue' => $msg_queue,
		)
	);

	foreach( $msg_queue as $message ) {
		$irc_api_postfields = array(
			'message' => $message,
			'botname' => $botname,
			'channel' => $channel,
		);

		$ch = curl_init();

		curl_setopt(
			$ch,
			CURLOPT_URL,
			$irc_api_url
		);

		curl_setopt(
			$ch,
			CURLOPT_RETURNTRANSFER,
			1
		);

		curl_setopt(
			$ch,
			CURLOPT_CONNECTTIMEOUT,
			VIPGOCI_HTTP_API_SHORT_TIMEOUT
		);

		curl_setopt(
			$ch,
			CURLOPT_USERAGENT,
			VIPGOCI_CLIENT_ID
		);

		curl_setopt(
			$ch,
			CURLOPT_POST,
			1
		);

		curl_setopt(
			$ch,
			CURLOPT_POSTFIELDS,
			json_encode( $irc_api_postfields )
		);

		curl_setopt(
			$ch,
			CURLOPT_HEADERFUNCTION,
			'vipgoci_curl_headers'
		);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array( 'Authorization: Bearer ' . $irc_api_token )
		);

		vipgoci_curl_set_security_options(
			$ch
		);

		/*
		 * Execute query, keep record of how long time it
		 * took, and keep count of how many requests we do.
		 */

		vipgoci_runtime_measure( VIPGOCI_RUNTIME_START, 'irc_api_post' );

		vipgoci_counter_report(
			VIPGOCI_COUNTERS_DO,
			'irc_api_request_post',
			1
		);

		$resp_data = curl_exec( $ch );

		vipgoci_runtime_measure( VIPGOCI_RUNTIME_STOP, 'irc_api_post' );

		$resp_headers = vipgoci_curl_headers(
			null,
			null
		);

		curl_close( $ch );

		/*
		 * Enforce a small wait between requests.
		 */

		time_nanosleep( 0, 500000000 );
	}
}

/**
 * Send statistics to pixel API so
 * we can keep track of actions we
 * take during runtime.
 *
 * @codeCoverageIgnore
 */
function vipgoci_send_stats_to_pixel_api(
	$pixel_api_url,
	$stat_names_to_report,
	$statistics
) {
	vipgoci_log(
		'Sending statistics to pixel API service',
		array(
			'stat_names_to_report' =>
				$stat_names_to_report
		)
	);

	$stat_names_to_groups = array(
	);

	foreach(
		array_keys( $stat_names_to_report ) as
			$statistic_group
	) {
		foreach(
			$stat_names_to_report[
				$statistic_group
			] as $stat_name
		) {
			$stat_names_to_groups[
				$stat_name
			] = $statistic_group;
		}
	}

	foreach(
		$statistics as
			$stat_name => $stat_value
	) {

		/*
		 * We are to report only certain
		 * values, so skip those who we should
		 * not report on.
		 */
		if ( false === array_key_exists(
			$stat_name,
			$stat_names_to_groups
		) ) {
			/*
			 * Not found, so nothing to report, skip.
			 */
			continue;
		}

		/*
		 * Compose URL.
		 */
		$url =
			$pixel_api_url .
			'?' .
			'v=wpcom-no-pv' .
			'&' .
			'x_' . rawurlencode(
				$stat_names_to_groups[
					$stat_name
				]
			) .
			'/' .
			rawurlencode(
				$stat_name
			) . '=' .
			rawurlencode(
				$stat_value
			);

		/*
		 * Call service, do nothing with output.
		 * Specify a short timeout.
		 */
		$ctx = stream_context_create(
			array(
				'http' => array(
					'timeout' => 5
				)
			)
		);

		file_get_contents( $url, 0, $ctx );

		/*
		 * Sleep a short while between
		 * requests.
		 */
		time_nanosleep(
			0,
			500000000
		);
	}
}

