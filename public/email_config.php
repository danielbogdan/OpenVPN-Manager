<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\EmailService;

Auth::require();

$action = $_POST['action'] ?? '';
$csrf = Auth::csrf();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['csrf'] ?? '')) {
    try {
        switch ($action) {
            case 'test_email':
                $result = EmailService::testEmailConfiguration();
                $message = $result['success'] ? 
                    "✅ " . $result['message'] : 
                    "❌ " . $result['message'];
                break;
                
            case 'save_template':
                $id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;
                $name = trim($_POST['name'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $body = trim($_POST['body'] ?? '');
                $isDefault = isset($_POST['is_default']);
                
                if ($name && $subject && $body) {
                    EmailService::saveEmailTemplate($id, $name, $subject, $body, $isDefault);
                    $message = "✅ Email template saved successfully!";
                } else {
                    $message = "❌ All fields are required!";
                }
                break;
        }
    } catch (\Throwable $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

$templates = EmailService::getEmailTemplates();
$message = $message ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Email Configuration - OpenVPN Manager</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/client.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/dashboard.css?v=<?= time() + 5 ?>">
</head>
<body>
    <!-- Admin Header -->
    <div class="client-header">
        <div class="client-header-content">
            <div class="client-title">
                <h1>OpenVPN Admin</h1>
                <div class="client-meta">
                    <span class="client-role">Administrator</span>
                    <span class="client-tenant">Admin Panel</span>
                    <div class="current-time">
                      <span id="live-clock"><?php
                        $currentTime = new DateTime('now', new DateTimeZone('Europe/Bucharest'));
                        echo $currentTime->format('M j, Y H:i:s');
                      ?></span>
                      <span class="timezone">(Local Time)</span>
                    </div>
                </div>
            </div>
            <div class="client-actions">
                <a href="/dashboard.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">🏠</span>
                    Dashboard
                </a>
                <a href="/tenants.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">🏢</span>
                    Manage Tenants
                </a>
                <a href="/email_config.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">📧</span>
                    Email Config
                </a>
                <a href="/admin_settings.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">⚙️</span>
                    Settings
                </a>
                <a href="/logout.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">🚪</span>
                    Logout
                </a>
            </div>
        </div>
    </div>

    <main>
        <?php if ($message): ?>
            <div class="flash <?= strpos($message, '❌') === 0 ? 'err' : '' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- SMTP Configuration -->
        <div class="card">
            <h2>SMTP Configuration</h2>
            <p>Configure your SMTP settings using environment variables:</p>
            <div style="background: #1f2937; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 0.875rem;">
                <div>SMTP_HOST=your-smtp-server.com</div>
                <div>SMTP_PORT=587</div>
                <div>SMTP_USERNAME=your-username</div>
                <div>SMTP_PASSWORD=your-password</div>
                <div>SMTP_SECURE=tls</div>
                <div>SMTP_FROM_EMAIL=noreply@yourdomain.com</div>
                <div>SMTP_FROM_NAME=OpenVPN Manager</div>
            </div>
            
            <form method="post" style="margin-top: 16px;">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="test_email">
                <button type="submit" class="btn">Test SMTP Connection</button>
            </form>
        </div>

        <!-- Email Templates -->
        <div class="card">
            <h2>Email Templates</h2>
            
            <?php foreach ($templates as $template): ?>
                <div style="border: 1px solid #374151; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                    <h3>
                        <?= htmlspecialchars($template['name']) ?>
                        <?php if ($template['is_default']): ?>
                            <span style="background: #10B981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; margin-left: 8px;">DEFAULT</span>
                        <?php endif; ?>
                    </h3>
                    <p><strong>Subject:</strong> <?= htmlspecialchars($template['subject']) ?></p>
                    <div style="background: #1f2937; padding: 12px; border-radius: 4px; margin-top: 8px;">
                        <pre style="white-space: pre-wrap; margin: 0; color: #e5e7eb;"><?= htmlspecialchars($template['body']) ?></pre>
                    </div>
                    <div style="margin-top: 12px;">
                        <button onclick="editTemplate(<?= $template['id'] ?>)" class="btn">Edit</button>
                        <?php if (!$template['is_default']): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="save_template">
                                <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                <input type="hidden" name="name" value="<?= htmlspecialchars($template['name']) ?>">
                                <input type="hidden" name="subject" value="<?= htmlspecialchars($template['subject']) ?>">
                                <input type="hidden" name="body" value="<?= htmlspecialchars($template['body']) ?>">
                                <input type="hidden" name="is_default" value="1">
                                <button type="submit" class="btn" style="background: #10B981; color: white;">Set as Default</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <button onclick="newTemplate()" class="btn" style="background: #3B82F6; color: white;">+ New Template</button>
        </div>

        <!-- Template Editor Modal -->
        <div id="templateModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #111827; border: 1px solid #374151; border-radius: 12px; padding: 24px; width: 90%; max-width: 600px; max-height: 80%; overflow-y: auto;">
                <h2 id="modalTitle">Edit Template</h2>
                <form method="post" id="templateForm">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="template_id" id="templateId">
                    
                    <div class="form-group">
                        <label>Template Name</label>
                        <input type="text" name="name" id="templateName" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" id="templateSubject" required>
                        <small style="color: #9ca3af;">Available variables: {username}, {tenant_name}, {server_ip}, {server_port}</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Body</label>
                        <textarea name="body" id="templateBody" rows="10" required style="width: 100%; background: #0b1220; color: #e8ecf1; border: 1px solid #334155; border-radius: 8px; padding: 8px;"></textarea>
                        <small style="color: #9ca3af;">Available variables: {username}, {tenant_name}, {server_ip}, {server_port}</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_default" id="templateDefault">
                            Set as default template
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 20px;">
                        <button type="submit" class="btn" style="background: #10B981; color: white;">Save Template</button>
                        <button type="button" onclick="closeModal()" class="btn">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function editTemplate(id) {
            // This would normally fetch template data via AJAX
            // For simplicity, we'll just show the modal
            document.getElementById('templateId').value = id;
            document.getElementById('modalTitle').textContent = 'Edit Template';
            document.getElementById('templateModal').style.display = 'block';
        }

        function newTemplate() {
            document.getElementById('templateId').value = '';
            document.getElementById('templateName').value = '';
            document.getElementById('templateSubject').value = '';
            document.getElementById('templateBody').value = '';
            document.getElementById('templateDefault').checked = false;
            document.getElementById('modalTitle').textContent = 'New Template';
            document.getElementById('templateModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('templateModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('templateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>

    <!-- Live Clock JavaScript -->
    <script>
    function updateLiveClock() {
        const now = new Date();
        const options = {
            timeZone: 'Europe/Bucharest',
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        };
        
        const formatter = new Intl.DateTimeFormat('en-US', options);
        const formattedTime = formatter.format(now);
        
        const clockElement = document.getElementById('live-clock');
        if (clockElement) {
            clockElement.textContent = formattedTime;
        }
    }

    // Update clock immediately and then every second
    updateLiveClock();
    setInterval(updateLiveClock, 1000);
    </script>
</body>
</html>
