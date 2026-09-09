<?php

class WPML_Mail_Sender {

	/**
	 * @param string       $to
	 * @param string       $subject
	 * @param string       $message
	 * @param string|array $headers
	 * @param array        $attachments
	 * @param string       $group
	 *
	 * @return bool|null
	 */
	public static function send( $to, $subject, $message, $headers = '', $attachments = array(), $group = '' ) {
		$args = array(
			'to'          => $to,
			'subject'     => $subject,
			'message'     => $message,
			'headers'     => $headers,
			'attachments' => $attachments,
			'group'       => $group,
		);

		/**
		 * Filters whether to send the email.
		 *
		 * @param bool  $enabled Whether to send the email. Default true.
		 * @param array $args {
		 *     Contextual data for the email.
		 *
		 *     @type string       $to          The email recipient.
		 *     @type string       $subject     The email subject.
		 *     @type string       $message     The email message.
		 *     @type string|array $headers     The email headers.
		 *     @type array        $attachments The email attachments.
		 *     @type string       $group       The email group.
		 * }
		 */
		if ( apply_filters( 'wpml_mail_enabled', true, $args ) ) {
			return wp_mail( $to, $subject, $message, $headers, $attachments );
		}

		return null;
	}
}
