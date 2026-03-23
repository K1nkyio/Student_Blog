<?php 
require_once '../shared/functions.php';
$page_title = 'Terms of Service - ' . SITE_NAME;
include '../shared/header.php'; 
?>

<style>
    .policy-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    .policy-header {
        text-align: center;
        margin-bottom: 3rem;
        padding: 2rem 0;
    }

    .policy-header h1 {
        font-size: 2.5rem;
        color: var(--gray-900);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
    }

    .policy-header p {
        color: var(--gray-600);
        font-size: 1.1rem;
    }

    .policy-content {
        background: white;
        padding: 2.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        line-height: 1.8;
    }

    .policy-content h2 {
        color: var(--primary);
        font-size: 1.75rem;
        margin-top: 2.5rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid var(--gray-200);
    }

    .policy-content h2:first-child {
        margin-top: 0;
    }

    .policy-content h3 {
        color: var(--gray-800);
        font-size: 1.25rem;
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
    }

    .policy-content p {
        color: var(--gray-700);
        margin-bottom: 1rem;
    }

    .policy-content ul, .policy-content ol {
        margin: 1rem 0;
        padding-left: 2rem;
        color: var(--gray-700);
    }

    .policy-content li {
        margin-bottom: 0.5rem;
    }

    .last-updated {
        text-align: center;
        color: var(--gray-500);
        font-size: 0.9rem;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid var(--gray-200);
    }

    @media (max-width: 768px) {
        .policy-header h1 {
            font-size: 2rem;
        }

        .policy-content {
            padding: 1.5rem;
        }
    }
</style>

<div class="policy-container">
    <div class="policy-header">
        <h1>
            <i class="fas fa-file-contract"></i>
            Terms of Service
        </h1>
        <p>Please read these terms carefully before using our website.</p>
    </div>

    <div class="policy-content">
        <h2>1. Acceptance of Terms</h2>
        <p>By accessing and using <?= SITE_NAME ?>, you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to these terms, please do not use our website.</p>

        <h2>2. Use License</h2>
        <p>Permission is granted to temporarily access the materials on <?= SITE_NAME ?> for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
        <ul>
            <li>Modify or copy the materials</li>
            <li>Use the materials for any commercial purpose or for any public display</li>
            <li>Attempt to reverse engineer any software contained on the website</li>
            <li>Remove any copyright or other proprietary notations from the materials</li>
        </ul>

        <h2>3. User Accounts</h2>
        <p>When you create an account with us, you must provide information that is accurate, complete, and current at all times. You are responsible for:</p>
        <ul>
            <li>Maintaining the security of your account and password</li>
            <li>All activities that occur under your account</li>
            <li>Notifying us immediately of any unauthorized use</li>
        </ul>

        <h2>4. User Content</h2>
        <p>You retain ownership of any content you post on our website. By posting content, you grant us a worldwide, non-exclusive, royalty-free license to use, reproduce, and distribute your content in connection with our services.</p>
        <p>You agree not to post content that:</p>
        <ul>
            <li>Is illegal, harmful, or violates any laws</li>
            <li>Infringes on intellectual property rights</li>
            <li>Contains spam, malware, or viruses</li>
            <li>Is defamatory, harassing, or abusive</li>
            <li>Violates the privacy of others</li>
        </ul>

        <h2>5. Prohibited Activities</h2>
        <p>You agree not to:</p>
        <ul>
            <li>Use the website in any way that could damage, disable, or impair the service</li>
            <li>Attempt to gain unauthorized access to any portion of the website</li>
            <li>Use automated systems to access the website without permission</li>
            <li>Interfere with or disrupt the website or servers</li>
            <li>Collect user information without consent</li>
        </ul>

        <h2>6. Intellectual Property</h2>
        <p>All content on this website, including text, graphics, logos, images, and software, is the property of <?= SITE_NAME ?> or its content suppliers and is protected by copyright and other intellectual property laws.</p>

        <h2>7. Disclaimer</h2>
        <p>The materials on <?= SITE_NAME ?> are provided on an 'as is' basis. We make no warranties, expressed or implied, and hereby disclaim and negate all other warranties including, without limitation, implied warranties or conditions of merchantability, fitness for a particular purpose, or non-infringement of intellectual property or other violation of rights.</p>

        <h2>8. Limitations</h2>
        <p>In no event shall <?= SITE_NAME ?> or its suppliers be liable for any damages (including, without limitation, damages for loss of data or profit, or due to business interruption) arising out of the use or inability to use the materials on our website.</p>

        <h2>9. Accuracy of Materials</h2>
        <p>The materials appearing on <?= SITE_NAME ?> could include technical, typographical, or photographic errors. We do not warrant that any of the materials on its website are accurate, complete, or current.</p>

        <h2>10. Modifications</h2>
        <p>We may revise these terms of service at any time without notice. By using this website, you are agreeing to be bound by the then current version of these terms of service.</p>

        <h2>11. Termination</h2>
        <p>We may terminate or suspend your account and access to the service immediately, without prior notice, for conduct that we believe violates these Terms of Service or is harmful to other users, us, or third parties.</p>

        <h2>12. Governing Law</h2>
        <p>These terms and conditions are governed by and construed in accordance with applicable laws. Any disputes relating to these terms shall be subject to the exclusive jurisdiction of the courts in which we operate.</p>

        <h2>13. Contact Information</h2>
        <p>If you have any questions about these Terms of Service, please contact us through our <a href="contact.php" style="color: var(--primary); text-decoration: underline;">contact page</a>.</p>

        <div class="last-updated">
            <p><strong>Last Updated:</strong> <?= date('F j, Y') ?></p>
        </div>
    </div>
</div>

<?php include '../shared/footer.php'; ?>

