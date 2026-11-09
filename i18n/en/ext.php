<?php

/******************************************************************************/
/* Each entry of that file can be associated with a comment to indicate its   */
/* state. When there is no comment, it means the entry is fully translated.   */
/* The recognized comments are (comment matching is case-insensitive):        */
/*   + TODO: the entry has never been translated.                             */
/*   + DIRTY: the entry has been translated but needs to be updated.          */
/*   + IGNORE: the entry does not need to be translated.                      */
/******************************************************************************/

return [
	'email_notifier' => [
		'description' => 'Every new item of the feeds selected below is sent as its own email: the sender name is the feed name, the subject is the item title, and the body holds the item content with a link to the original. Use "Save and send a test email" to check the SMTP settings.',
		'feeds' => 'Feeds to send by email',
		'smtp' => 'SMTP server',
		'smtp_host' => 'Server',
		'smtp_port' => 'Port',
		'smtp_port_help' => 'Usually 587 with STARTTLS, 465 with SSL/TLS.',
		'smtp_encryption' => 'Encryption',
		'encryption_tls' => 'STARTTLS',
		'encryption_ssl' => 'SSL/TLS',
		'encryption_none' => 'None',
		'smtp_username' => 'Username',
		'smtp_username_help' => 'Leave empty if the server does not require authentication.',
		'smtp_password' => 'Password',
		'smtp_password_help' => 'A password is saved. Leave empty to keep it.',
		'mail_from' => 'From address',
		'mail_to' => 'Send to',
		'mail_to_help' => 'Separate several addresses with commas.',
		'send_test' => 'Save and send a test email',
		'test_subject' => 'FreshRSS test email',
		'test_body' => 'Your SMTP settings work. New items of the selected feeds will be sent to this address.',
		'test_sent' => 'Test email sent.',
		'test_failed' => 'The test email could not be sent: %s',
	],
];
