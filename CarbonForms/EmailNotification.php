<?php
namespace CarbonForms;

/**
 * Form email notification
 */
class EmailNotification {
	/**
	 * Settings
	 */
	private $base_settings;

	/**
	 * Path to the template file
	 */
	private $template;

	/**
	 * @var PhpMailer
	 */
	public $mailer;

	/**
	 * The last sending error will be stored here. 
	 * @var string
	 */
	public $error;

	function __construct($settings=[]) {
		$this->base_settings = [
			'recipients'  => [],
			'from'        => '',
			'from_name'   => '',
			'subject'     => '',

			'template'    => __DIR__ . '/../email-templates/form.php',

			'smtp_config' => [
				'enable'     => false,
				'host'       => '',
				'port'       => '',
				'username'   => '',
				'password'   => '',
				'encryption' => '',

			],
		];

		$settings = array_merge($this->base_settings, $settings);

		$mailer = new \PHPMailer();
		$mailer->isHTML(true);
		$mailer->charSet = "UTF-8"; 

		$mailer->From     = $settings['from'];
		$mailer->FromName = $settings['from_name'];

		$recipients = $this->normalize_recipients($settings['recipients']);

		foreach ($recipients as $email => $name) {
			$mailer->AddAddress($email, $name);
		}

		$mailer->Subject = $settings['subject'];

		if (isset($settings['smtp_config']) && $settings['smtp_config']['enable']) {
			$smtp_config = $settings['smtp_config'];

			$mailer->isSMTP();
			$mailer->SMTPAuth   = true;

			$mailer->Host       = $smtp_config['host'];
			$mailer->Port       = $smtp_config['port'];
			$mailer->Username   = $smtp_config['username'];
			$mailer->Password   = $smtp_config['password'];
			$mailer->SMTPSecure = $smtp_config['encryption'];
		}

		$this->mailer = $mailer;
		$this->set_template($settings['template']);
	}

	public function set_template($file) {
		if (!file_exists($file)) {
			throw new Exception("Couldn't find template file $file");
		}

		$this->template = $file;
	}

	public function get_template() {
		return $this->template;
	}

	/**
	 * Render the HTML with the $context variables.
	 * @param $template string path to the template file
	 * @param $context $context array with the variables used in the template
	 */
	function render_message($context) {
		extract($context);

		ob_start();
		include($this->template);
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * Build hash containing email addresses as keys and names as
	 * values from the following formats:
	 * 
	 *  * "johndoe@gmail.com"
	 *  * ["johndoe@gmail.com", "doejane@gmail.com"]
	 *  * ["johndoe@gmail.com" => "John Doe", "doejane@gmail.com"]
	 */
	protected function normalize_recipients($recipients) {
		$result = array();

		// Scalar strings are supported
		$recipients = (array)$recipients;

		foreach ($recipients as $key => $value) {
			// Allow recipients to be passed as
			// ["user@gmail.com"] or ["user@gmail.com" => "User Name"]
			if (is_numeric($key)) {
				$recipient_mail = $value;
				$recipient_name = '';
			} else {
				$recipient_mail = $key;
				$recipient_name = $value;
			}

			$result[$recipient_mail] = $recipient_name;
		}

		return $result;
	}

	/**
	 * Add an attachment to the message
	 */
	function attach($file_path, $display_name) {
		if (!file_exists($file_path)) {
			throw new MailDeliveryException("Attachment file doesn't exists: $file_path");
		}
		$this->mailer->AddAttachment($file_path, $display_name);
	}

	/**
	 * Sends an HTML email message. 
	 */
	function send( $fields ) {
		$this->mailer->Body = $this->render_message([
			'fields' => $fields,
		]);

		$result = $this->mailer->send();

		if (!$result) {
			throw new MailDeliveryException($this->mailer->ErrorInfo);
		}

		return $result;
	}
}
