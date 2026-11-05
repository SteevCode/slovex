<?php
/**
 * Solvex Report — Mailer Module
 * 
 * Sends emails via SMTP or logs them to file.
 * Uses PHPMailer when available, falls back to PHP mail().
 * In 'log' mode, writes email content to storage/logs/mail.log.
 * When APP_ENV=local AND MAIL_MODE=log, also returns the link for display.
 */

/**
 * Send an email.
 * 
 * @param string $to      Recipient email
 * @param string $subject Email subject
 * @param string $body    HTML body
 * @return array ['success' => bool, 'message' => string, 'debug_link' => string|null]
 */
function send_mail(string $to, string $subject, string $body): array
{
    $debugLink = null;

    if (MAIL_MODE === 'log') {
        // Log mode: write to file
        $logFile = LOG_PATH . '/mail.log';
        $entry   = str_repeat('=', 60) . "\n";
        $entry  .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $entry  .= "To: {$to}\n";
        $entry  .= "Subject: {$subject}\n";
        $entry  .= "Body:\n{$body}\n";
        $entry  .= str_repeat('=', 60) . "\n\n";

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

        // Extract links from the body for debug display
        if (APP_ENV === 'local') {
            if (preg_match('/href=["\']([^"\']*)["\']/', $body, $matches)) {
                $debugLink = $matches[1];
            }
        }

        return [
            'success'    => true,
            'message'    => 'Email logged to file.',
            'debug_link' => $debugLink,
        ];
    }

    // SMTP mode: use PHPMailer if available
    $phpmailerPath = SOLVEX_ROOT . '/vendor/PHPMailer/src/PHPMailer.php';
    if (file_exists($phpmailerPath)) {
        require_once SOLVEX_ROOT . '/vendor/PHPMailer/src/Exception.php';
        require_once SOLVEX_ROOT . '/vendor/PHPMailer/src/PHPMailer.php';
        require_once SOLVEX_ROOT . '/vendor/PHPMailer/src/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            $mail->send();

            return ['success' => true, 'message' => 'Email sent.', 'debug_link' => null];
        } catch (Exception $ex) {
            error_log('Mailer error: ' . $mail->ErrorInfo);
            return ['success' => false, 'message' => 'Failed to send email.', 'debug_link' => null];
        }
    }

    // Fallback: PHP mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";

    $sent = @mail($to, $subject, $body, $headers);

    return [
        'success'    => $sent,
        'message'    => $sent ? 'Email sent.' : 'Failed to send email.',
        'debug_link' => null,
    ];
}

/**
 * Send a verification email to a newly registered user.
 */
function send_verification_email(string $email, string $fullName, string $token): array
{
    $link    = base_url('verify.php?token=' . urlencode($token));
    $subject = 'Verify your Solvex Report account';
    $body    = email_template(
        'Verify Your Email',
        "Hi " . e($fullName) . ",",
        "Thank you for registering with Solvex Report. Please click the button below to verify your email address.",
        $link,
        'Verify Email',
        'This link expires in 24 hours. If you did not create an account, you can safely ignore this email.'
    );

    return send_mail($email, $subject, $body);
}

/**
 * Send a password reset email.
 */
function send_reset_email(string $email, string $token): array
{
    $link    = base_url('reset.php?token=' . urlencode($token));
    $subject = 'Reset your Solvex Report password';
    $body    = email_template(
        'Reset Your Password',
        'Hi,',
        'We received a request to reset your password. Click the button below to set a new password.',
        $link,
        'Reset Password',
        'This link expires in 30 minutes. If you did not request this, you can safely ignore this email.'
    );

    return send_mail($email, $subject, $body);
}

/**
 * Generate a styled email template.
 */
function email_template(
    string $heading,
    string $greeting,
    string $message,
    string $actionUrl,
    string $actionText,
    string $footer
): string {
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f4f5f7;font-family:'Inter',Arial,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:40px 20px;">
            <tr><td align="center">
                <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="background:#0B2545;padding:28px 32px;text-align:center;">
                            <span style="color:#0E9F8E;font-size:24px;font-weight:700;">Solvex</span>
                            <span style="color:#ffffff;font-size:24px;font-weight:300;"> Report</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h2 style="color:#0B2545;margin:0 0 16px;font-size:20px;">{$heading}</h2>
                            <p style="color:#374151;margin:0 0 8px;">{$greeting}</p>
                            <p style="color:#6B7280;margin:0 0 24px;line-height:1.6;">{$message}</p>
                            <p style="text-align:center;margin:0 0 24px;">
                                <a href="{$actionUrl}" style="display:inline-block;background:#0E9F8E;color:#ffffff;padding:12px 32px;border-radius:10px;text-decoration:none;font-weight:600;font-size:15px;">{$actionText}</a>
                            </p>
                            <p style="color:#9CA3AF;font-size:13px;margin:0;line-height:1.5;">{$footer}</p>
                            <p style="color:#D1D5DB;font-size:12px;margin:16px 0 0;word-break:break-all;">
                                <a href="{$actionUrl}" style="color:#6B7280;">{$actionUrl}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb;">
                            <p style="color:#9CA3AF;font-size:12px;margin:0;">&copy; Solvex Report. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>
    HTML;
}
