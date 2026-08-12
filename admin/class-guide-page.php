<?php
/**
 * Guide Page - Setup guide for the plugin.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GuidePage Class.
 */
class GuidePage {

    /**
     * Render the guide page.
     *
     * @return void
     */
    public function render(): void {
        ?>
        <div class="fm-page-wrap">
            <div class="fm-page-header">
                <h1 class="fm-page-title">
                    <span class="fm-icon"><?php echo \Fanaloka\Maintenance\Icons::get( 'guide' ); ?></span>
                    <?php esc_html_e( 'Setup Guide', 'fanaloka-maintenance' ); ?>
                </h1>
            </div>

            <div class="fm-guide-content">
                <!-- Step 1 -->
                <div class="fm-guide-card">
                    <div class="fm-guide-number">1</div>
                    <div class="fm-guide-body">
                        <h2><?php esc_html_e( 'IMAP Connection', 'fanaloka-maintenance' ); ?></h2>
                        <p><?php esc_html_e( 'Connect to your email server to receive tickets.', 'fanaloka-maintenance' ); ?></p>
                        <div class="fm-guide-steps">
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Go to', 'fanaloka-maintenance' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-settings&tab=general' ) ); ?>"><?php esc_html_e( 'Settings → IMAP', 'fanaloka-maintenance' ); ?></a></strong>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Fill in these values:', 'fanaloka-maintenance' ); ?></strong>
                                <table class="fm-guide-table">
                                    <tr><td><?php esc_html_e( 'Host', 'fanaloka-maintenance' ); ?></td><td><code>imap.gmail.com</code></td></tr>
                                    <tr><td><?php esc_html_e( 'Port', 'fanaloka-maintenance' ); ?></td><td><code>993</code></td></tr>
                                    <tr><td><?php esc_html_e( 'Encryption', 'fanaloka-maintenance' ); ?></td><td><code>SSL</code></td></tr>
                                    <tr><td><?php esc_html_e( 'Username', 'fanaloka-maintenance' ); ?></td><td><?php esc_html_e( 'Your full email address', 'fanaloka-maintenance' ); ?></td></tr>
                                    <tr><td><?php esc_html_e( 'Password', 'fanaloka-maintenance' ); ?></td><td><?php esc_html_e( 'App Password (not your regular password)', 'fanaloka-maintenance' ); ?></td></tr>
                                    <tr><td><?php esc_html_e( 'Folder', 'fanaloka-maintenance' ); ?></td><td><code>INBOX</code></td></tr>
                                </table>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'For Gmail — Step by step:', 'fanaloka-maintenance' ); ?></strong>
                                <ol>
                                    <li>
                                        <strong><?php esc_html_e( 'Enable 2-Step Verification first', 'fanaloka-maintenance' ); ?></strong><br>
                                        <span style="color:#646970;"><?php esc_html_e( 'Go to', 'fanaloka-maintenance' ); ?> <a href="https://myaccount.google.com/signinoptions/two-step-verification" target="_blank"><?php esc_html_e( 'Google 2-Step Verification', 'fanaloka-maintenance' ); ?></a><br>
                                        <?php esc_html_e( 'App Passwords will NOT work without 2-Step Verification enabled.', 'fanaloka-maintenance' ); ?></span>
                                    </li>
                                    <li>
                                        <strong><?php esc_html_e( 'Create App Password', 'fanaloka-maintenance' ); ?></strong><br>
                                        <span style="color:#646970;"><?php esc_html_e( 'After 2FA is on, go to', 'fanaloka-maintenance' ); ?> <a href="https://myaccount.google.com/apppasswords" target="_blank"><?php esc_html_e( 'Google App Passwords', 'fanaloka-maintenance' ); ?></a><br>
                                        <?php esc_html_e( 'If you don\'t see this page, 2-Step Verification is not enabled.', 'fanaloka-maintenance' ); ?></span>
                                    </li>
                                    <li>
                                        <strong><?php esc_html_e( 'Create a new app password', 'fanaloka-maintenance' ); ?></strong><br>
                                        <span style="color:#646970;"><?php esc_html_e( 'Select app: "Mail" | Select device: "Other (Custom name)" → type "Fanaloka" → click Generate.', 'fanaloka-maintenance' ); ?></span>
                                    </li>
                                    <li>
                                        <strong><?php esc_html_e( 'Copy the 16-character password', 'fanaloka-maintenance' ); ?></strong><br>
                                        <span style="color:#646970;"><?php esc_html_e( 'It looks like: abcd efgh ijkl mnop — paste it in the Password field below. This password is shown ONCE, save it somewhere safe.', 'fanaloka-maintenance' ); ?></span>
                                    </li>
                                </ol>
                            </div>
                            <div class="fm-guide-step">
                                <button type="button" class="button fm-btn-test-connection" id="fm-test-connection"><?php esc_html_e( 'Test Connection', 'fanaloka-maintenance' ); ?></button>
                                <span id="fm-test-result" style="margin-left:10px;font-size:13px;font-weight:600;"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="fm-guide-card">
                    <div class="fm-guide-number">2</div>
                    <div class="fm-guide-body">
                        <h2><?php esc_html_e( 'Sync Settings', 'fanaloka-maintenance' ); ?></h2>
                        <p><?php esc_html_e( 'Configure how often the plugin checks for new emails.', 'fanaloka-maintenance' ); ?></p>
                        <div class="fm-guide-steps">
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Go to', 'fanaloka-maintenance' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-settings&tab=sync' ) ); ?>"><?php esc_html_e( 'Settings → Sync', 'fanaloka-maintenance' ); ?></a></strong>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Recommended values:', 'fanaloka-maintenance' ); ?></strong>
                                <table class="fm-guide-table">
                                    <tr><td><?php esc_html_e( 'Sync Interval', 'fanaloka-maintenance' ); ?></td><td><code>5</code> <?php esc_html_e( 'minutes', 'fanaloka-maintenance' ); ?></td></tr>
                                    <tr><td><?php esc_html_e( 'Auto Sync', 'fanaloka-maintenance' ); ?></td><td><code><?php esc_html_e( 'Enabled', 'fanaloka-maintenance' ); ?></code></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="fm-guide-card">
                    <div class="fm-guide-number">3</div>
                    <div class="fm-guide-body">
                        <h2><?php esc_html_e( 'Ticket Settings', 'fanaloka-maintenance' ); ?></h2>
                        <p><?php esc_html_e( 'Configure how tickets are created and numbered.', 'fanaloka-maintenance' ); ?></p>
                        <div class="fm-guide-steps">
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Go to', 'fanaloka-maintenance' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-settings&tab=ticket' ) ); ?>"><?php esc_html_e( 'Settings → Ticket', 'fanaloka-maintenance' ); ?></a></strong>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Recommended values:', 'fanaloka-maintenance' ); ?></strong>
                                <table class="fm-guide-table">
                                    <tr><td><?php esc_html_e( 'Ticket Prefix', 'fanaloka-maintenance' ); ?></td><td><code>REQ</code></td></tr>
                                    <tr><td><?php esc_html_e( 'Default Status', 'fanaloka-maintenance' ); ?></td><td><code><?php esc_html_e( 'New', 'fanaloka-maintenance' ); ?></code></td></tr>
                                    <tr><td><?php esc_html_e( 'Default Priority', 'fanaloka-maintenance' ); ?></td><td><code><?php esc_html_e( 'Medium', 'fanaloka-maintenance' ); ?></code></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="fm-guide-card">
                    <div class="fm-guide-number">4</div>
                    <div class="fm-guide-body">
                        <h2><?php esc_html_e( 'Email Notifications', 'fanaloka-maintenance' ); ?></h2>
                        <p><?php esc_html_e( 'Set up email notifications for ticket events.', 'fanaloka-maintenance' ); ?></p>
                        <div class="fm-guide-steps">
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Go to', 'fanaloka-maintenance' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-settings&tab=notification' ) ); ?>"><?php esc_html_e( 'Settings → Notification', 'fanaloka-maintenance' ); ?></a></strong>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Fill in:', 'fanaloka-maintenance' ); ?></strong>
                                <table class="fm-guide-table">
                                    <tr><td><?php esc_html_e( 'Admin Email', 'fanaloka-maintenance' ); ?></td><td><?php esc_html_e( 'Email to receive notifications', 'fanaloka-maintenance' ); ?></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 5 -->
                <div class="fm-guide-card">
                    <div class="fm-guide-number">5</div>
                    <div class="fm-guide-body">
                        <h2><?php esc_html_e( 'SMTP Outgoing Mail', 'fanaloka-maintenance' ); ?></h2>
                        <p><?php esc_html_e( 'Send replies and notifications through your email provider instead of PHP mail.', 'fanaloka-maintenance' ); ?></p>
                        <div class="fm-guide-steps">
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Go to', 'fanaloka-maintenance' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-settings&tab=smtp' ) ); ?>"><?php esc_html_e( 'Settings → SMTP', 'fanaloka-maintenance' ); ?></a></strong>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Fill in these values:', 'fanaloka-maintenance' ); ?></strong>
                                <table class="fm-guide-table">
                                    <tr><td><?php esc_html_e( 'Enable SMTP', 'fanaloka-maintenance' ); ?></td><td><code><?php esc_html_e( 'Enabled', 'fanaloka-maintenance' ); ?></code></td></tr>
                                    <tr><td><?php esc_html_e( 'Host', 'fanaloka-maintenance' ); ?></td><td><code>smtp.gmail.com</code></td></tr>
                                    <tr><td><?php esc_html_e( 'Port', 'fanaloka-maintenance' ); ?></td><td><code>587</code> <?php esc_html_e( '(TLS) or', 'fanaloka-maintenance' ); ?> <code>465</code> <?php esc_html_e( '(SSL)', 'fanaloka-maintenance' ); ?></td></tr>
                                    <tr><td><?php esc_html_e( 'Encryption', 'fanaloka-maintenance' ); ?></td><td><code>TLS</code></td></tr>
                                    <tr><td><?php esc_html_e( 'Username', 'fanaloka-maintenance' ); ?></td><td><?php esc_html_e( 'Your full email address', 'fanaloka-maintenance' ); ?></td></tr>
                                    <tr><td><?php esc_html_e( 'Password', 'fanaloka-maintenance' ); ?></td><td><?php esc_html_e( 'App Password (same one used for IMAP)', 'fanaloka-maintenance' ); ?></td></tr>
                                </table>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Optional:', 'fanaloka-maintenance' ); ?></strong>
                                <ul>
                                    <li><?php esc_html_e( 'From Name — e.g., "Fanaloka Support" (defaults to site name)', 'fanaloka-maintenance' ); ?></li>
                                    <li><?php esc_html_e( 'From Email — must be your Workspace address (defaults to SMTP username)', 'fanaloka-maintenance' ); ?></li>
                                </ul>
                            </div>
                            <div class="fm-guide-step">
                                <button type="button" class="button fm-btn-test-smtp" id="fm-test-smtp"><?php esc_html_e( 'Send Test Email', 'fanaloka-maintenance' ); ?></button>
                                <span id="fm-smtp-test-result" style="margin-left:10px;font-size:13px;font-weight:600;"></span>
                                <p style="margin:8px 0 0;color:#646970;"><?php esc_html_e( 'Save the settings first, then click this button to verify SMTP works.', 'fanaloka-maintenance' ); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 6 -->
                <div class="fm-guide-card">
                    <div class="fm-guide-number">6</div>
                    <div class="fm-guide-body">
                        <h2><?php esc_html_e( 'First Sync', 'fanaloka-maintenance' ); ?></h2>
                        <p><?php esc_html_e( 'Run your first sync to create tickets from existing emails.', 'fanaloka-maintenance' ); ?></p>
                        <div class="fm-guide-steps">
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Go to', 'fanaloka-maintenance' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-dashboard' ) ); ?>"><?php esc_html_e( 'Dashboard', 'fanaloka-maintenance' ); ?></a></strong>
                            </div>
                            <div class="fm-guide-step">
                                <?php esc_html_e( 'Click the Sync Now button to process all unseen emails.', 'fanaloka-maintenance' ); ?>
                            </div>
                            <div class="fm-guide-step">
                                <?php esc_html_e( 'After sync, tickets will appear in the Requests page.', 'fanaloka-maintenance' ); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 7 -->
                <div class="fm-guide-card">
                    <div class="fm-guide-number">7</div>
                    <div class="fm-guide-body">
                        <h2><?php esc_html_e( 'Email Log (Sent)', 'fanaloka-maintenance' ); ?></h2>
                        <p><?php esc_html_e( 'Track every email the plugin sends: replies, notifications, and test emails.', 'fanaloka-maintenance' ); ?></p>
                        <div class="fm-guide-steps">
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Open', 'fanaloka-maintenance' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fm-email-log' ) ); ?>"><?php esc_html_e( 'Menu → Email Log', 'fanaloka-maintenance' ); ?></a></strong>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'What you can do:', 'fanaloka-maintenance' ); ?></strong>
                                <ul>
                                    <li><?php esc_html_e( 'See time, recipient (To/CC/BCC), subject, context, and status of each sent email', 'fanaloka-maintenance' ); ?></li>
                                    <li><?php esc_html_e( 'Filter by status (Sent / Failed) and context (Notification / Reply / Test)', 'fanaloka-maintenance' ); ?></li>
                                    <li><?php esc_html_e( 'Click a subject to view the full email body and headers', 'fanaloka-maintenance' ); ?></li>
                                    <li><?php esc_html_e( 'Delete individual entries or clear the whole log', 'fanaloka-maintenance' ); ?></li>
                                </ul>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Failed emails:', 'fanaloka-maintenance' ); ?></strong>
                                <p style="margin:0;"><?php esc_html_e( 'Shown in red with the SMTP error message. Usually the fix is checking the SMTP credentials or port.', 'fanaloka-maintenance' ); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Troubleshooting -->
                <div class="fm-guide-card fm-guide-troubleshoot">
                    <div class="fm-guide-number">!</div>
                    <div class="fm-guide-body">
                        <h2><?php esc_html_e( 'Troubleshooting', 'fanaloka-maintenance' ); ?></h2>
                        <div class="fm-guide-steps">
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Connection failed?', 'fanaloka-maintenance' ); ?></strong>
                                <ul>
                                    <li><?php esc_html_e( 'Check IMAP is enabled on your email server', 'fanaloka-maintenance' ); ?></li>
                                    <li><?php esc_html_e( 'For Gmail: use App Password, not regular password', 'fanaloka-maintenance' ); ?></li>
                                    <li><?php esc_html_e( 'Check port number (993 for SSL, 587 for TLS)', 'fanaloka-maintenance' ); ?></li>
                                </ul>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Email not sent / not received?', 'fanaloka-maintenance' ); ?></strong>
                                <ul>
                                    <li><?php esc_html_e( 'Check Menu → Email Log → status Failed shows the SMTP error', 'fanaloka-maintenance' ); ?></li>
                                    <li><?php esc_html_e( 'Verify SMTP host, port, and App Password are correct', 'fanaloka-maintenance' ); ?></li>
                                    <li><?php esc_html_e( 'Check the From Email matches your Workspace address', 'fanaloka-maintenance' ); ?></li>
                                </ul>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'No tickets created?', 'fanaloka-maintenance' ); ?></strong>
                                <ul>
                                    <li><?php esc_html_e( 'Check if cron job is running (Dashboard → Sync Status)', 'fanaloka-maintenance' ); ?></li>
                                    <li><?php esc_html_e( 'Try manual sync from Dashboard', 'fanaloka-maintenance' ); ?></li>
                                    <li><?php esc_html_e( 'Check email filter rules are not ignoring your emails', 'fanaloka-maintenance' ); ?></li>
                                </ul>
                            </div>
                            <div class="fm-guide-step">
                                <strong><?php esc_html_e( 'Check logs:', 'fanaloka-maintenance' ); ?></strong>
                                <p><code>wp option get fm_logs --format=json</code></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .fm-page-wrap { max-width: 1400px; margin: 0 auto; padding: 0 24px 40px; }
        .fm-page-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-page-title { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .fm-guide-content { display: flex; flex-direction: column; gap: 16px; }
        .fm-guide-card { display: flex; gap: 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .fm-guide-number { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; background: #2271b1; color: #fff; font-size: 20px; font-weight: 700; flex-shrink: 0; }
        .fm-guide-troubleshoot .fm-guide-number { background: #dba617; }
        .fm-guide-body { flex: 1; }
        .fm-guide-body h2 { margin: 0 0 8px; font-size: 18px; color: #1d2327; }
        .fm-guide-body > p { margin: 0 0 16px; color: #646970; font-size: 14px; }
        .fm-guide-steps { display: flex; flex-direction: column; gap: 12px; }
        .fm-guide-step { padding: 12px; background: #f9f9f9; border-radius: 6px; font-size: 14px; }
        .fm-guide-step strong { display: block; margin-bottom: 6px; color: #1d2327; }
        .fm-guide-step a { color: #2271b1; }
        .fm-guide-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .fm-guide-table td { padding: 4px 8px; border-bottom: 1px solid #e2e4e7; font-size: 13px; }
        .fm-guide-table td:first-child { font-weight: 600; color: #1d2327; width: 120px; }
        .fm-guide-table td:last-child { color: #646970; }
        .fm-guide-step code { background: #e2e4e7; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
        .fm-guide-step ul, .fm-guide-step ol { margin: 6px 0 0; padding-left: 20px; }
        .fm-guide-step li { margin-bottom: 4px; }
        .fm-test-success { color: #00a32a; }
        .fm-test-error { color: #d63638; }
        </style>

        <script>
        var fmGuideAjax = {
            url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
            nonce: '<?php echo esc_js( wp_create_nonce( 'fm_admin_nonce' ) ); ?>',
        };
        (function($) {
            $(document).on('click', '#fm-test-connection', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $result = $('#fm-test-result');

                $btn.prop('disabled', true).text('Testing...');
                $result.text('').removeClass('fm-test-success fm-test-error');

                $.post(fmGuideAjax.url, {
                    action: 'fm_test_connection',
                    nonce: fmGuideAjax.nonce,
                }, function(response) {
                    if (response.success) {
                        $result.text(response.data.message || 'Connected!').addClass('fm-test-success');
                    } else {
                        $result.text(response.data.message || 'Failed').addClass('fm-test-error');
                    }
                    $btn.prop('disabled', false).text('Test Connection');
                }).fail(function() {
                    $result.text('Request failed.').addClass('fm-test-error');
                    $btn.prop('disabled', false).text('Test Connection');
                });
            });

            $(document).on('click', '#fm-test-smtp', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $result = $('#fm-smtp-test-result');

                $btn.prop('disabled', true).text('Sending...');
                $result.text('').removeClass('fm-test-success fm-test-error');

                $.post(fmGuideAjax.url, {
                    action: 'fm_test_smtp',
                    nonce: fmGuideAjax.nonce,
                }, function(response) {
                    if (response.success) {
                        $result.text(response.data.message || 'Test email sent!').addClass('fm-test-success');
                    } else {
                        $result.text(response.data.message || 'Failed').addClass('fm-test-error');
                    }
                    $btn.prop('disabled', false).text('Send Test Email');
                }).fail(function() {
                    $result.text('Request failed.').addClass('fm-test-error');
                    $btn.prop('disabled', false).text('Send Test Email');
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}
