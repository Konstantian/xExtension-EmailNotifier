<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends an email through the configured SMTP server for every new entry of the selected feeds.
 *
 * New entries are caught with the `EntryBeforeAdd` hook, which only sees entries that are not in
 * the database yet (not updates of existing ones), after the other extensions' `EntryBeforeInsert`
 * filters and FreshRSS's own filter actions have run. Entries are only sent while their feed is
 * being refreshed: articles imported from a file go through the same hook and are left alone.
 */
final class EmailNotifierExtension extends Minz_Extension {
	public const ENCRYPTIONS = ['tls', 'ssl', 'none'];
	public const DEFAULT_PORT = 587;
	/** Seconds to wait for the SMTP server, so that an unreachable server does not stall feed refreshes for long */
	private const SMTP_TIMEOUT = 15;

	/** @var array<int,true> Selected feeds being refreshed in this request */
	private array $refreshingFeedIds = [];
	/** Set after a failed email, so that the next entries of the same refresh do not wait for the same error */
	private bool $sendFailed = false;

	#[\Override]
	public function init(): void {
		$this->registerTranslates();

		$this->registerHook(Minz_HookType::FeedBeforeActualize, [$this, 'rememberRefreshingFeed']);
		// Run after every other extension's hook, so that entries they drop are not sent
		$this->registerHook(Minz_HookType::EntryBeforeAdd, [$this, 'notifyEntry'], PHP_INT_MAX);
	}

	#[\Override]
	public function handleConfigureAction(): void {
		$this->registerTranslates();

		if (!Minz_Request::isPost()) {
			return;
		}

		$this->setUserConfigurationValue('feeds', Minz_Request::paramArrayInt('feeds'));
		$this->setUserConfigurationValue('smtp_host', Minz_Request::paramString('smtp_host', plaintext: true));
		$this->setUserConfigurationValue('smtp_port', Minz_Request::paramInt('smtp_port') ?: self::DEFAULT_PORT);
		$encryption = Minz_Request::paramString('smtp_encryption');
		$this->setUserConfigurationValue('smtp_encryption', in_array($encryption, self::ENCRYPTIONS, true) ? $encryption : self::ENCRYPTIONS[0]);
		$username = Minz_Request::paramString('smtp_username', plaintext: true);
		$this->setUserConfigurationValue('smtp_username', $username);
		// The saved password is never sent back to the form: an empty field keeps it, unless the username was removed
		$password = Minz_Request::paramString('smtp_password', plaintext: true);
		if ($password !== '' || $username === '') {
			$this->setUserConfigurationValue('smtp_password', $password);
		}
		$this->setUserConfigurationValue('mail_from', Minz_Request::paramString('mail_from', plaintext: true));
		$this->setUserConfigurationValue('mail_to', Minz_Request::paramString('mail_to', plaintext: true));

		$url = ['c' => 'extension', 'a' => 'configure', 'params' => ['e' => $this->getName()]];
		if (!Minz_Request::paramBoolean('send_test')) {
			Minz_Request::good(_t('feedback.conf.updated'), $url);
			return;
		}

		$body = _t('ext.email_notifier.test_body');
		$error = $this->send('FreshRSS', _t('ext.email_notifier.test_subject'), '<p>' . $body . '</p>', $body);
		if ($error === null) {
			Minz_Request::good(_t('ext.email_notifier.test_sent'), $url);
		} else {
			Minz_Request::bad(_t('ext.email_notifier.test_failed', htmlspecialchars($error, ENT_NOQUOTES, 'UTF-8')), $url);
		}
	}

	/** @return list<int> */
	public function selectedFeedIds(): array {
		return array_values(array_map('intval', $this->getUserConfigurationArray('feeds') ?? []));
	}

	/**
	 * Hook for FeedBeforeActualize: remembers the selected feeds refreshed in this request, so that
	 * only their new entries get sent.
	 */
	public function rememberRefreshingFeed(FreshRSS_Feed $feed): FreshRSS_Feed {
		if (in_array($feed->id(), $this->selectedFeedIds(), true)) {
			$this->refreshingFeedIds[$feed->id()] = true;
		}
		return $feed;
	}

	/**
	 * Hook for EntryBeforeAdd: sends an email for a new entry of a selected feed.
	 * The entry is always kept, even when the email could not be sent.
	 */
	public function notifyEntry(FreshRSS_Entry $entry): FreshRSS_Entry {
		if (isset($this->refreshingFeedIds[$entry->feedId()])) {
			$this->sendEntry($entry);
		}
		return $entry;
	}

	private function sendEntry(FreshRSS_Entry $entry): void {
		$title = self::plainText($entry->title());
		if ($this->sendFailed) {
			Minz_Log::warning('EmailNotifier: skipped "' . $title . '" after an earlier failure in this refresh');
			return;
		}

		$link = self::plainText($entry->link());
		$heading = htmlspecialchars($title, ENT_NOQUOTES, 'UTF-8');
		if ($link !== '') {
			$heading = '<a href="' . htmlspecialchars($link, ENT_COMPAT, 'UTF-8') . '">' . $heading . '</a>';
		}
		$content = $entry->content();
		$html = '<h2>' . $heading . "</h2>\n" . $content;
		$text = trim($title . "\n" . $link) . "\n\n" . trim(self::plainText(strip_tags($content)));

		$feedName = self::plainText($entry->feed()?->name() ?? '');
		$error = $this->send($feedName, $title, $html, $text);
		if ($error !== null) {
			$this->sendFailed = true;
			Minz_Log::warning('EmailNotifier: failed to send "' . $title . '": ' . $error);
		}
	}

	/** Decodes the HTML entities that FreshRSS keeps in titles, names and links */
	private static function plainText(string $html): string {
		return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/**
	 * Sends one email with the saved SMTP settings.
	 * @return string|null null when the email was sent, or the error message
	 */
	private function send(string $fromName, string $subject, string $html, string $text): ?string {
		$mail = new PHPMailer(true);
		try {
			$mail->isSMTP();
			$mail->Host = $this->getUserConfigurationString('smtp_host') ?? '';
			$mail->Port = $this->getUserConfigurationInt('smtp_port') ?? self::DEFAULT_PORT;
			$encryption = $this->getUserConfigurationString('smtp_encryption') ?? self::ENCRYPTIONS[0];
			$mail->SMTPSecure = $encryption === 'none' ? '' : $encryption;
			$mail->SMTPAutoTLS = $encryption !== 'none';
			$mail->Username = $this->getUserConfigurationString('smtp_username') ?? '';
			$mail->Password = $this->getUserConfigurationString('smtp_password') ?? '';
			$mail->SMTPAuth = $mail->Username !== '';
			$mail->Timeout = self::SMTP_TIMEOUT;
			$mail->CharSet = PHPMailer::CHARSET_UTF8;

			$mail->setFrom($this->getUserConfigurationString('mail_from') ?? '', $fromName);
			foreach (explode(',', $this->getUserConfigurationString('mail_to') ?? '') as $address) {
				if (trim($address) !== '') {
					$mail->addAddress(trim($address));
				}
			}

			$mail->isHTML();
			$mail->Subject = $subject;
			$mail->Body = $html;
			$mail->AltBody = $text;
			$mail->send();
			return null;
		} catch (Exception $e) {
			return $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
		}
	}
}
