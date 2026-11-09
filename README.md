# xExtension-EmailNotifier

A [FreshRSS](https://github.com/FreshRSS/FreshRSS) extension that sends an **email through your own SMTP server whenever a new item appears in the feeds you select** — handy for the few feeds you never want to miss, like the releases of an app you self-host.

## How it works

Every new item of a selected feed is sent as its own email:

- The **sender name** is the feed name (the address is the one you configure).
- The **subject** is the item title.
- The **body** holds the item title, linked to the original, followed by the item content as FreshRSS shows it.

Only items that are new to FreshRSS are sent: updates of existing items are not. Items that another extension drops before they are stored (for example with [xExtension-PrereleaseOmitter](https://github.com/Konstantian/xExtension-PrereleaseOmitter) or [xExtension-ShortsOmitter](https://github.com/Konstantian/xExtension-ShortsOmitter)) are not sent either. Articles you import from a file are never sent.

Emails are sent while the feed is being refreshed, whether by the cron job, the refresh button, or WebSub.

## Installation

1. Download/clone this repository into the `extensions` directory of your FreshRSS installation, so that you end up with `./extensions/xExtension-EmailNotifier/extension.php`. (If `THIRDPARTY_EXTENSIONS_PATH` is configured, use that directory instead.)
2. In FreshRSS, go to `Configuration` → `Extensions` and enable **Email Notifier**.
3. Open the extension's configuration panel to select feeds and set up SMTP.

Requires FreshRSS 1.29.0 or later.

## Configuration

- **Feeds to send by email**: tick the feeds whose new items you want by email. They are grouped by category.
- **SMTP server**: server, port, encryption (STARTTLS, SSL/TLS or none), and the username and password if the server requires authentication. The saved password is never shown again in the form; leave the field empty to keep it.
- **From address**: the address emails are sent from. Many providers only accept the address of the account you log in with.
- **Send to**: one or more recipient addresses, separated by commas.

Use **Save and send a test email** to check the settings: it saves the form and sends a test message, then shows the server's error if it fails.

Each FreshRSS user has their own selection and SMTP settings.

## Limitations

- An email that cannot be sent (server down, wrong password…) is not retried: the error goes to the FreshRSS log, and the item is still added to FreshRSS as usual. After a failure, the other new items of the same refresh are skipped (and logged) rather than each waiting for the same error.
- Sending happens during the feed refresh, so each email adds a little time to it. The extension waits at most 15 seconds for the SMTP server.
- A feed that publishes many items at once sends that many emails.
