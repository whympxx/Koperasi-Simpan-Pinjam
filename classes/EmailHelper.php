<?php
/**
 * Email Helper Class
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

class EmailHelper {
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpEncryption;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $this->smtpHost = SMTP_HOST;
        $this->smtpPort = SMTP_PORT;
        $this->smtpUsername = SMTP_USERNAME;
        $this->smtpPassword = SMTP_PASSWORD;
        $this->smtpEncryption = SMTP_ENCRYPTION;
        $this->fromEmail = FROM_EMAIL;
        $this->fromName = FROM_NAME;
    }
    
    /**
     * Send email using PHP mail function (fallback)
     */
    public function sendEmail($to, $subject, $message, $isHtml = true) {
        try {
            $headers = [];
            $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
            $headers[] = 'Reply-To: ' . $this->fromEmail;
            $headers[] = 'X-Mailer: PHP/' . phpversion();
            
            if ($isHtml) {
                $headers[] = 'MIME-Version: 1.0';
                $headers[] = 'Content-type: text/html; charset=UTF-8';
            }
            
            $headersString = implode("\r\n", $headers);
            
            // For development, we'll log the email instead of sending
            if (empty($this->smtpPassword)) {
                $this->logEmail($to, $subject, $message);
                return true;
            }
            
            return mail($to, $subject, $message, $headersString);
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($to, $name, $resetLink) {
        $subject = 'Reset Password - ' . APP_NAME;
        $message = $this->getPasswordResetEmailTemplate($name, $resetLink);
        
        return $this->sendEmail($to, $subject, $message, true);
    }
    
    /**
     * Send notification email
     */
    public function sendNotificationEmail($to, $name, $title, $message, $actionUrl = null) {
        $subject = $title . ' - ' . APP_NAME;
        $emailMessage = $this->getNotificationEmailTemplate($name, $title, $message, $actionUrl);
        
        return $this->sendEmail($to, $subject, $emailMessage, true);
    }
    
    /**
     * Get password reset email template
     */
    private function getPasswordResetEmailTemplate($name, $resetLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Reset Password</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Reset Password</h2>
                    <p>PT. Puninar Jaya Logistik</p>
                </div>
                <div class='content'>
                    <p>Halo <strong>{$name}</strong>,</p>
                    
                    <p>Kami menerima permintaan untuk mereset password akun Anda. Jika Anda tidak meminta hal ini, silakan abaikan email ini.</p>
                    
                    <p>Untuk mereset password Anda, silakan klik tombol di bawah ini:</p>
                    
                    <p style='text-align: center;'>
                        <a href='{$resetLink}' class='button'>Reset Password</a>
                    </p>
                    
                    <p>Atau salin dan tempel link berikut ke browser Anda:</p>
                    <p style='word-break: break-all; background: #eee; padding: 10px; border-radius: 5px; font-family: monospace;'>{$resetLink}</p>
                    
                    <div class='warning'>
                        <strong>Catatan penting:</strong>
                        <ul>
                            <li>Link ini hanya berlaku selama 1 jam</li>
                            <li>Link hanya dapat digunakan sekali</li>
                            <li>Jika link sudah kadaluarsa, silakan request reset password lagi</li>
                        </ul>
                    </div>
                    
                    <p>Jika Anda mengalami kesulitan, silakan hubungi administrator.</p>
                    
                    <p>Terima kasih,<br>Tim IT PT. Puninar Jaya Logistik</p>
                </div>
                <div class='footer'>
                    <p>Email ini dikirim secara otomatis, mohon tidak membalas email ini.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get notification email template
     */
    private function getNotificationEmailTemplate($name, $title, $message, $actionUrl = null) {
        $actionButton = '';
        if ($actionUrl) {
            $actionButton = "<p style='text-align: center;'><a href='{$actionUrl}' class='button'>Lihat Detail</a></p>";
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$title}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>{$title}</h2>
                    <p>PT. Puninar Jaya Logistik</p>
                </div>
                <div class='content'>
                    <p>Halo <strong>{$name}</strong>,</p>
                    
                    <p>{$message}</p>
                    
                    {$actionButton}
                    
                    <p>Terima kasih,<br>Tim IT PT. Puninar Jaya Logistik</p>
                </div>
                <div class='footer'>
                    <p>Email ini dikirim secara otomatis, mohon tidak membalas email ini.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Log email for development
     */
    private function logEmail($to, $subject, $message) {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] EMAIL LOG\n";
        $logMessage .= "To: {$to}\n";
        $logMessage .= "Subject: {$subject}\n";
        $logMessage .= "Message: {$message}\n";
        $logMessage .= "---\n";
        
        file_put_contents(ROOT_PATH . 'logs/email_notifications.log', $logMessage, FILE_APPEND | LOCK_EX);
    }
}

?>
