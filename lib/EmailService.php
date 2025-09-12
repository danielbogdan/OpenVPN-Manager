<?php
namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private static ?PHPMailer $mailer = null;
    
    /**
     * Initialize PHPMailer with configuration
     */
    private static function getMailer(): PHPMailer
    {
        if (self::$mailer === null) {
            self::$mailer = new PHPMailer(true);
            
            // Server settings
            self::$mailer->isSMTP();
            self::$mailer->Host = getenv('SMTP_HOST') ?: 'localhost';
            self::$mailer->SMTPAuth = (bool)(getenv('SMTP_AUTH') ?: false);
            self::$mailer->Username = getenv('SMTP_USERNAME') ?: '';
            self::$mailer->Password = getenv('SMTP_PASSWORD') ?: '';
            self::$mailer->SMTPSecure = getenv('SMTP_SECURE') ?: PHPMailer::ENCRYPTION_STARTTLS;
            self::$mailer->Port = (int)(getenv('SMTP_PORT') ?: 587);
            
            // Sender
            self::$mailer->setFrom(
                getenv('SMTP_FROM_EMAIL') ?: 'noreply@openvpn.local',
                getenv('SMTP_FROM_NAME') ?: 'OpenVPN Manager'
            );
        }
        
        return self::$mailer;
    }
    
    /**
     * Send certificate via email
     */
    public static function sendCertificate(
        int $tenantId,
        int $userId,
        string $emailTo,
        string $certificateContent,
        string $username,
        string $tenantName
    ): bool {
        $pdo = DB::pdo();
        
        // Get email template
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE is_default = 1 LIMIT 1");
        $stmt->execute();
        $template = $stmt->fetch();
        
        if (!$template) {
            throw new \RuntimeException("No default email template found");
        }
        
        // Get tenant details
        $tenant = OpenVPNManager::getTenant($tenantId);
        if (!$tenant) {
            throw new \RuntimeException("Tenant not found");
        }
        
        // Replace template variables
        $subject = str_replace([
            '{tenant_name}',
            '{username}',
            '{server_ip}',
            '{server_port}'
        ], [
            $tenantName,
            $username,
            $tenant['public_ip'],
            $tenant['listen_port']
        ], $template['subject']);
        
        $body = str_replace([
            '{tenant_name}',
            '{username}',
            '{server_ip}',
            '{server_port}'
        ], [
            $tenantName,
            $username,
            $tenant['public_ip'],
            $tenant['listen_port']
        ], $template['body']);
        
        // Log email attempt
        $logId = self::logEmail($tenantId, $userId, $emailTo, $subject, 'pending');
        
        try {
            $mailer = self::getMailer();
            $mailer->clearAddresses();
            $mailer->clearAttachments();
            
            // Recipient
            $mailer->addAddress($emailTo, $username);
            
            // Content
            $mailer->isHTML(false);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            
            // Add certificate as attachment
            $mailer->addStringAttachment(
                $certificateContent,
                "{$username}.ovpn",
                'base64',
                'application/octet-stream'
            );
            
            $mailer->send();
            
            // Update log as sent
            self::updateEmailLog($logId, 'sent');
            
            return true;
            
        } catch (Exception $e) {
            // Update log as failed
            self::updateEmailLog($logId, 'failed', $e->getMessage());
            throw new \RuntimeException("Email sending failed: " . $e->getMessage());
        }
    }
    
    /**
     * Log email attempt
     */
    private static function logEmail(int $tenantId, int $userId, string $emailTo, string $subject, string $status): int
    {
        $pdo = DB::pdo();
        
        $stmt = $pdo->prepare("
            INSERT INTO email_logs (tenant_id, user_id, email_to, subject, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$tenantId, $userId, $emailTo, $subject, $status]);
        return (int)$pdo->lastInsertId();
    }
    
    /**
     * Update email log status
     */
    private static function updateEmailLog(int $logId, string $status, ?string $errorMessage = null): void
    {
        $pdo = DB::pdo();
        
        $stmt = $pdo->prepare("
            UPDATE email_logs 
            SET status = ?, error_message = ?, sent_at = ?
            WHERE id = ?
        ");
        
        $sentAt = $status === 'sent' ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$status, $errorMessage, $sentAt, $logId]);
    }
    
    /**
     * Get email logs for a tenant
     */
    public static function getEmailLogs(int $tenantId, int $limit = 50): array
    {
        $pdo = DB::pdo();
        
        $stmt = $pdo->prepare("
            SELECT el.*, vu.username, t.name as tenant_name
            FROM email_logs el
            JOIN vpn_users vu ON el.user_id = vu.id
            JOIN tenants t ON el.tenant_id = t.id
            WHERE el.tenant_id = ?
            ORDER BY el.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$tenantId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Test email configuration
     */
    public static function testEmailConfiguration(): array
    {
        try {
            $mailer = self::getMailer();
            $mailer->smtpConnect();
            $mailer->smtpClose();
            
            return [
                'success' => true,
                'message' => 'SMTP connection successful'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get email templates
     */
    public static function getEmailTemplates(): array
    {
        $pdo = DB::pdo();
        
        $stmt = $pdo->prepare("SELECT * FROM email_templates ORDER BY is_default DESC, name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Create or update email template
     */
    public static function saveEmailTemplate(
        ?int $id,
        string $name,
        string $subject,
        string $body,
        bool $isDefault = false
    ): int {
        $pdo = DB::pdo();
        
        if ($isDefault) {
            // Remove default flag from other templates
            $pdo->prepare("UPDATE email_templates SET is_default = 0")->execute();
        }
        
        if ($id) {
            // Update existing template
            $stmt = $pdo->prepare("
                UPDATE email_templates 
                SET name = ?, subject = ?, body = ?, is_default = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $subject, $body, $isDefault ? 1 : 0, $id]);
            return $id;
        } else {
            // Create new template
            $stmt = $pdo->prepare("
                INSERT INTO email_templates (name, subject, body, is_default)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $subject, $body, $isDefault ? 1 : 0]);
            return (int)$pdo->lastInsertId();
        }
    }
}
