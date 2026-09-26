<?php
/**
 * Partnership Emails Class
 *
 * Handles email notifications for partnership events.
 *
 * @package WB_Ad_Manager
 * @since   2.2.0
 */

namespace WBAM\Modules\Links;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WBAM\Core\Singleton;

/**
 * Partnership Emails class.
 */
class Partnership_Emails {

	use Singleton;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Nothing to construct.
	}

	/**
	 * Initialize email hooks.
	 */
	public function init() {
		// Admin notification on new inquiry.
		add_action( 'wbam_partnership_created', array( $this, 'notify_admin_new_inquiry' ) );

		// Notify requester on acceptance.
		add_action( 'wbam_partnership_accepted', array( $this, 'notify_requester_accepted' ) );

		// Notify requester on rejection.
		add_action( 'wbam_partnership_rejected', array( $this, 'notify_requester_rejected' ) );

		// Allow custom email templates via filters.
		add_filter( 'wbam_partnership_email_headers', array( $this, 'get_email_headers' ) );
	}

	/**
	 * Send email notification to admin when new inquiry is submitted.
	 *
	 * @param Partnership $partnership Partnership object.
	 */
	public function notify_admin_new_inquiry( $partnership ) {
		/**
		 * Filter whether the admin new-inquiry notification email sends.
		 *
		 * @since 2.0.0
		 * @param bool        $send        Whether to send the email. Default true.
		 * @param Partnership $partnership Partnership object the inquiry was submitted for.
		 */
		if ( ! apply_filters( 'wbam_send_partnership_admin_notification', true, $partnership ) ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] New Link Partnership Inquiry', 'wb-ads-rotator-with-split-test' ),
			$site_name
		);

		$message = $this->get_admin_notification_message( $partnership );

		$this->send_email( $admin_email, $subject, $message );
	}

	/**
	 * Send email notification to requester when partnership is accepted.
	 *
	 * @param Partnership $partnership Partnership object.
	 */
	public function notify_requester_accepted( $partnership ) {
		/**
		 * Filter whether the requester's "accepted" notification email sends.
		 *
		 * @since 2.0.0
		 * @param bool        $send        Whether to send the email. Default true.
		 * @param Partnership $partnership Partnership object that was accepted.
		 */
		if ( ! apply_filters( 'wbam_send_partnership_accepted_notification', true, $partnership ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Your Link Partnership Inquiry Has Been Accepted', 'wb-ads-rotator-with-split-test' ),
			$site_name
		);

		$message = $this->get_accepted_notification_message( $partnership );

		$this->send_email( $partnership->email, $subject, $message );
	}

	/**
	 * Send email notification to requester when partnership is rejected.
	 *
	 * @param Partnership $partnership Partnership object.
	 */
	public function notify_requester_rejected( $partnership ) {
		/**
		 * Filter whether the requester's "rejected" notification email sends.
		 *
		 * @since 2.0.0
		 * @param bool        $send        Whether to send the email. Default true.
		 * @param Partnership $partnership Partnership object that was rejected.
		 */
		if ( ! apply_filters( 'wbam_send_partnership_rejected_notification', true, $partnership ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Regarding Your Link Partnership Inquiry', 'wb-ads-rotator-with-split-test' ),
			$site_name
		);

		$message = $this->get_rejected_notification_message( $partnership );

		$this->send_email( $partnership->email, $subject, $message );
	}

	/**
	 * Get admin notification message.
	 *
	 * @param Partnership $partnership Partnership object.
	 * @return string
	 */
	private function get_admin_notification_message( $partnership ) {
		$site_name = get_bloginfo( 'name' );
		$admin_url = admin_url( 'admin.php?page=wbam-partnerships&view=' . $partnership->id );

		$message = sprintf(
			/* translators: %1$s: site name, %2$s: contact name, %3$s: email, %4$s: website URL, %5$s: partnership type, %6$s: budget range, %7$s: message, %8$s: admin URL, %9$s: site name again */
			__(
				'Hello,

A new link partnership inquiry has been submitted on %1$s.

Details:
---------
Name: %2$s
Email: %3$s
Website: %4$s
Partnership Type: %5$s
Budget Range: %6$s

Message:
%7$s

---------

You can review and respond to this inquiry here:
%8$s

Best regards,
%9$s',
				'wb-ads-rotator-with-split-test'
			),
			$site_name,
			$partnership->name,
			$partnership->email,
			$partnership->website_url,
			$partnership->get_type_label(),
			$partnership->get_budget_range(),
			$partnership->message ? $partnership->message : __( '(No message provided)', 'wb-ads-rotator-with-split-test' ),
			$admin_url,
			$site_name
		);

		/**
		 * Filter the plain-text body of the admin new-inquiry notification email.
		 *
		 * @since 2.0.0
		 * @param string      $message     Rendered email body.
		 * @param Partnership $partnership Partnership object the inquiry was submitted for.
		 */
		return apply_filters( 'wbam_partnership_admin_notification_message', $message, $partnership );
	}

	/**
	 * Get accepted notification message.
	 *
	 * @param Partnership $partnership Partnership object.
	 * @return string
	 */
	private function get_accepted_notification_message( $partnership ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		$message = sprintf(
			/* translators: %1$s: requester name, %2$s: site name */
			__(
				'Hello %1$s,

Great news! Your link partnership inquiry for %2$s has been accepted.

Partnership Details:
---------
Type: %3$s
Website: %4$s

---------

We will be in touch with you shortly to discuss the next steps.

If you have any questions, please feel free to reply to this email.

Best regards,
%2$s
%5$s',
				'wb-ads-rotator-with-split-test'
			),
			$partnership->name,
			$site_name,
			$partnership->get_type_label(),
			$partnership->website_url,
			$site_url
		);

		/**
		 * Filter the plain-text body of the requester's "accepted" notification email.
		 *
		 * @since 2.0.0
		 * @param string      $message     Rendered email body.
		 * @param Partnership $partnership Partnership object that was accepted.
		 */
		return apply_filters( 'wbam_partnership_accepted_notification_message', $message, $partnership );
	}

	/**
	 * Get rejected notification message.
	 *
	 * @param Partnership $partnership Partnership object.
	 * @return string
	 */
	private function get_rejected_notification_message( $partnership ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		$message = sprintf(
			/* translators: %1$s: requester name, %2$s: site name */
			__(
				'Hello %1$s,

Thank you for your interest in partnering with %2$s.

After careful review, we have decided not to proceed with your partnership inquiry at this time.

This decision may be due to various factors, and we encourage you to reach out again in the future if circumstances change.

Thank you for your understanding.

Best regards,
%2$s
%3$s',
				'wb-ads-rotator-with-split-test'
			),
			$partnership->name,
			$site_name,
			$site_url
		);

		/**
		 * Filter the plain-text body of the requester's "rejected" notification email.
		 *
		 * @since 2.0.0
		 * @param string      $message     Rendered email body.
		 * @param Partnership $partnership Partnership object that was rejected.
		 */
		return apply_filters( 'wbam_partnership_rejected_notification_message', $message, $partnership );
	}

	/**
	 * Send email.
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject Email subject.
	 * @param string $message Email message.
	 * @return bool
	 */
	private function send_email( $to, $subject, $message ) {
		/**
		 * Short-circuit sending a partnership email, e.g. to route it through
		 * another mailer or email layout.
		 *
		 * @since 3.2.0
		 *
		 * @param bool|null $sent    Null to send with wp_mail() as plain text; a bool to skip it and report that result.
		 * @param string    $to      Recipient email.
		 * @param string    $subject Email subject.
		 * @param string    $message Plain-text email body.
		 */
		$sent = apply_filters( 'wbam_partnership_pre_send_email', null, $to, $subject, $message );

		if ( null === $sent ) {
			/**
			 * Filter the wp_mail() headers used for a partnership email. This
			 * class's own get_email_headers() adds the plain-text Content-Type
			 * and From header at default priority.
			 *
			 * @since 2.0.0
			 * @param string[] $headers Email headers. Empty until a callback adds to it.
			 */
			$headers = apply_filters( 'wbam_partnership_email_headers', array() );
			$sent    = wp_mail( $to, $subject, $message, $headers );
		}

		/**
		 * Fires after a partnership email has been sent (or skipped/failed).
		 *
		 * @since 2.0.0
		 * @param string    $to      Recipient email.
		 * @param string    $subject Email subject.
		 * @param string    $message Plain-text email body.
		 * @param bool|null $sent    wp_mail()'s result, or the pre_send_email filter's result.
		 */
		do_action( 'wbam_partnership_email_sent', $to, $subject, $message, $sent );

		return $sent;
	}

	/**
	 * Get default email headers.
	 *
	 * @param array $headers Existing headers.
	 * @return array
	 */
	public function get_email_headers( $headers = array() ) {
		$site_name   = get_bloginfo( 'name' );
		$admin_email = get_option( 'admin_email' );

		$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		$headers[] = sprintf( 'From: %s <%s>', $site_name, $admin_email );

		return $headers;
	}
}
