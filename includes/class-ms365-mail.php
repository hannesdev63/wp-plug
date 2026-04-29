<?php
/**
 * WordPress mail replacement via Microsoft Graph sendMail.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MS365_Mail {

	/** Maximum file attachment size for sendMail JSON payload (3 MiB). */
	const MAX_ATTACHMENT_BYTES = 3145728;

	public function __construct() {
		add_filter( 'pre_wp_mail', array( $this, 'maybe_send_via_graph' ), 10, 2 );
	}

	/**
	 * Optionally short-circuit wp_mail and send through Graph.
	 *
	 * @param null|bool $short_circuit Existing short-circuit value.
	 * @param array     $atts          Mail attributes prepared by wp_mail().
	 * @return null|bool
	 */
	public function maybe_send_via_graph( $short_circuit, $atts ) {
		if ( null !== $short_circuit ) {
			return $short_circuit;
		}

		$settings = WP_MS365_Auth::get_settings();
		if ( empty( $settings['mail_enabled'] ) ) {
			return null;
		}

		$sender_user = isset( $settings['mail_sender_user'] ) ? trim( (string) $settings['mail_sender_user'] ) : '';
		if ( '' === $sender_user ) {
			$sender_user = isset( $settings['specific_user'] ) ? trim( (string) $settings['specific_user'] ) : '';
		}

		if ( '' === $sender_user ) {
			$error = new WP_Error( 'ms365_mail_missing_sender', __( 'Graph mail sender is not configured. Set Mail Sender Mailbox (UPN or ID) in plugin settings.', 'wp-ms365-graph' ) );
			$this->emit_wp_mail_failed( $error, $atts );
			WP_MS365_Logger::log( 'error', 'Graph mail failed: sender mailbox not configured' );
			return false;
		}

		$parsed_headers = $this->parse_headers( isset( $atts['headers'] ) ? $atts['headers'] : array() );
		$to             = $this->normalize_recipient_list( isset( $atts['to'] ) ? $atts['to'] : array() );
		$cc             = $this->normalize_recipient_list( $parsed_headers['cc'] );
		$bcc            = $this->normalize_recipient_list( $parsed_headers['bcc'] );
		$reply_to       = $this->normalize_recipient_list( $parsed_headers['reply_to'] );

		if ( empty( $to ) && empty( $cc ) && empty( $bcc ) ) {
			$error = new WP_Error( 'ms365_mail_missing_recipient', __( 'No valid email recipients were provided.', 'wp-ms365-graph' ) );
			$this->emit_wp_mail_failed( $error, $atts );
			WP_MS365_Logger::log( 'error', 'Graph mail failed: no recipients' );
			return false;
		}

		$content_type = strtolower( (string) $parsed_headers['content_type'] );
		$body_type    = ( false !== strpos( $content_type, 'text/html' ) ) ? 'HTML' : 'Text';
		$attachments  = $this->build_attachments( isset( $atts['attachments'] ) ? $atts['attachments'] : array() );

		if ( is_wp_error( $attachments ) ) {
			$this->emit_wp_mail_failed( $attachments, $atts );
			WP_MS365_Logger::log( 'error', 'Graph mail failed while preparing attachments: ' . $attachments->get_error_message() );
			return false;
		}

		$message = array(
			'subject' => isset( $atts['subject'] ) ? (string) $atts['subject'] : '',
			'body'    => array(
				'contentType' => $body_type,
				'content'     => isset( $atts['message'] ) ? (string) $atts['message'] : '',
			),
		);

		if ( ! empty( $to ) ) {
			$message['toRecipients'] = $this->to_graph_recipients( $to );
		}
		if ( ! empty( $cc ) ) {
			$message['ccRecipients'] = $this->to_graph_recipients( $cc );
		}
		if ( ! empty( $bcc ) ) {
			$message['bccRecipients'] = $this->to_graph_recipients( $bcc );
		}
		if ( ! empty( $reply_to ) ) {
			$message['replyTo'] = $this->to_graph_recipients( $reply_to );
		}
		if ( ! empty( $attachments ) ) {
			$message['attachments'] = $attachments;
		}

		$payload = array(
			'message'         => $message,
			'saveToSentItems' => ! empty( $settings['mail_save_to_sent_items'] ),
		);

		$result = WP_MS365_Graph::post( '/users/' . rawurlencode( $sender_user ) . '/sendMail', $payload );

		if ( is_wp_error( $result ) ) {
			$this->emit_wp_mail_failed( $result, $atts );
			WP_MS365_Logger::log(
				'error',
				'Graph mail send failed: ' . $result->get_error_message(),
				array( 'sender_user' => $sender_user )
			);
			return false;
		}

		WP_MS365_Logger::log(
			'debug',
			'Graph mail sent successfully',
			array(
				'sender_user' => $sender_user,
				'to_count'    => count( $to ),
				'cc_count'    => count( $cc ),
				'bcc_count'   => count( $bcc ),
			)
		);

		return true;
	}

	/**
	 * Parse headers input (array|string) into structured fields.
	 *
	 * @param array|string $headers Raw headers from wp_mail.
	 * @return array
	 */
	private function parse_headers( $headers ) {
		$parsed = array(
			'cc'           => array(),
			'bcc'          => array(),
			'reply_to'     => array(),
			'content_type' => '',
		);

		$lines = array();
		if ( is_array( $headers ) ) {
			$lines = $headers;
		} elseif ( is_string( $headers ) ) {
			$lines = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}

			list( $name, $value ) = explode( ':', $line, 2 );
			$name  = strtolower( trim( (string) $name ) );
			$value = trim( (string) $value );

			if ( 'cc' === $name ) {
				$parsed['cc'] = array_merge( $parsed['cc'], $this->normalize_recipient_list( $value ) );
			} elseif ( 'bcc' === $name ) {
				$parsed['bcc'] = array_merge( $parsed['bcc'], $this->normalize_recipient_list( $value ) );
			} elseif ( 'reply-to' === $name ) {
				$parsed['reply_to'] = array_merge( $parsed['reply_to'], $this->normalize_recipient_list( $value ) );
			} elseif ( 'content-type' === $name ) {
				$parsed['content_type'] = $value;
			}
		}

		$parsed['cc']       = array_values( array_unique( $parsed['cc'] ) );
		$parsed['bcc']      = array_values( array_unique( $parsed['bcc'] ) );
		$parsed['reply_to'] = array_values( array_unique( $parsed['reply_to'] ) );

		return $parsed;
	}

	/**
	 * Normalize a recipient source (array|string) to valid email addresses.
	 *
	 * @param array|string $raw Raw recipients.
	 * @return array
	 */
	private function normalize_recipient_list( $raw ) {
		$emails = array();
		$items  = is_array( $raw ) ? $raw : explode( ',', (string) $raw );

		foreach ( $items as $item ) {
			$item = trim( (string) $item );
			if ( '' === $item ) {
				continue;
			}

			if ( preg_match( '/<([^>]+)>/', $item, $matches ) ) {
				$item = trim( (string) $matches[1] );
			}

			$email = sanitize_email( $item );
			if ( is_email( $email ) ) {
				$emails[] = strtolower( $email );
			}
		}

		return array_values( array_unique( $emails ) );
	}

	/**
	 * Convert recipient email list to Graph recipient objects.
	 *
	 * @param array $emails Email addresses.
	 * @return array
	 */
	private function to_graph_recipients( array $emails ) {
		$recipients = array();
		foreach ( $emails as $email ) {
			$recipients[] = array(
				'emailAddress' => array(
					'address' => $email,
				),
			);
		}
		return $recipients;
	}

	/**
	 * Build Graph fileAttachment payload list from wp_mail attachments.
	 *
	 * @param array|string $attachments Attachment paths.
	 * @return array|WP_Error
	 */
	private function build_attachments( $attachments ) {
		$paths = is_array( $attachments ) ? $attachments : ( '' === (string) $attachments ? array() : array( $attachments ) );
		if ( empty( $paths ) ) {
			return array();
		}

		$result = array();
		foreach ( $paths as $path ) {
			$path = (string) $path;
			if ( '' === $path ) {
				continue;
			}

			if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
				return new WP_Error( 'ms365_mail_attachment_missing', sprintf( __( 'Attachment file not readable: %s', 'wp-ms365-graph' ), basename( $path ) ) );
			}

			$size = (int) filesize( $path );
			if ( $size > self::MAX_ATTACHMENT_BYTES ) {
				return new WP_Error( 'ms365_mail_attachment_too_large', sprintf( __( 'Attachment exceeds 3 MiB Graph JSON limit: %s', 'wp-ms365-graph' ), basename( $path ) ) );
			}

			$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $content ) {
				return new WP_Error( 'ms365_mail_attachment_read_failed', sprintf( __( 'Failed reading attachment: %s', 'wp-ms365-graph' ), basename( $path ) ) );
			}

			$mime = function_exists( 'mime_content_type' ) ? (string) mime_content_type( $path ) : '';
			if ( '' === $mime ) {
				$mime = 'application/octet-stream';
			}

			$result[] = array(
				'@odata.type'  => '#microsoft.graph.fileAttachment',
				'name'         => basename( $path ),
				'contentType'  => $mime,
				'contentBytes' => base64_encode( $content ),
			);
		}

		return $result;
	}

	/**
	 * Trigger the wp_mail_failed action for compatibility with other plugins.
	 *
	 * @param WP_Error $error Mail error object.
	 * @param array    $atts  Mail attributes.
	 * @return void
	 */
	private function emit_wp_mail_failed( WP_Error $error, array $atts ) {
		$error->add_data( $atts );
		do_action( 'wp_mail_failed', $error );
	}
}
