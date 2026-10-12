# SOS Emergency Relay — Gmail email setup

This project sends the SOS email from the PHP backend using Gmail SMTP. The Gmail app password is kept in `.env` and is never sent to the browser.

## Important security step

The app password previously pasted into chat should be revoked in your Google Account. Generate a fresh Google App Password and use only the new one in `.env`. Do not reuse or share the exposed password.

## Requirements

- PHP 8.1 or newer
- Composer
- A Google account with 2-Step Verification enabled and App Passwords available

## Setup on Windows

1. Extract this folder.
2. Open Command Prompt or PowerShell in this folder.
3. Copy `.env.example` to `.env`.
4. Edit `.env` and replace `PASTE_NEW_16_CHARACTER_APP_PASSWORD_HERE` with your newly generated Google App Password. For the sender and recipient, use the configured addresses shown in the file.
5. Install PHP dependencies:

   ```powershell
   composer install
   ```

6. Start the local PHP server:

   ```powershell
   php -S 127.0.0.1:8000
   ```

7. Open `http://127.0.0.1:8000` in your browser. Do not open `index.html` directly as a `file://` URL.

## How it works

- Click **Share my current location** and allow location access if you want GPS coordinates included.
- Click **Send SOS now**.
- The browser sends only the optional coordinates to `send-sos.php`.
- PHP sends the email via `smtp.gmail.com` over STARTTLS on port 587.
- The dashboard reports success or a generic failure. Detailed SMTP errors are logged by PHP on the server and are not exposed to the browser.

## Troubleshooting

- **Mail service is not installed yet:** run `composer install`.
- **Email delivery failed:** verify the new app password, Gmail address, internet access, and whether your network allows outbound SMTP on port 587. Check the PHP server terminal for the detailed error.
- **App Passwords not available:** confirm 2-Step Verification is enabled and that your Google account type/admin policy supports App Passwords.
- The email is sent to the address in `SOS_RECIPIENT`, not to the phone number shown in the profile.

## Safety

This is a local demonstration, not a guaranteed emergency communications service. Keep the phone number and other emergency contact methods available; if there is immediate danger, call local emergency services as well. Do not deploy this publicly without authentication, rate limiting, abuse protection, and HTTPS.
