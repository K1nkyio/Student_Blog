<?php 
require_once '../shared/functions.php';
$page_title = 'Privacy Policy - ' . SITE_NAME;
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
            <i class="fas fa-shield-alt"></i>
            Privacy Policy
        </h1>
        <p>Your privacy is important to us. This policy explains how we collect, use, and protect your information.</p>
    </div>

    <div class="policy-content">
        <h2>1. Information We Collect</h2>
        <p>We collect information that you provide directly to us when you:</p>
        <ul>
            <li>Register for an account (username, email address, password)</li>
            <li>Post comments or interact with content</li>
            <li>Subscribe to our newsletter</li>
            <li>Contact us through our contact form</li>
        </ul>
        <p>We also automatically collect certain information when you visit our website, including:</p>
        <ul>
            <li>IP address and browser type</li>
            <li>Pages visited and time spent on pages</li>
            <li>Referring website addresses</li>
        </ul>

        <h2>2. How We Use Your Information</h2>
        <p>We use the information we collect to:</p>
        <ul>
            <li>Provide, maintain, and improve our services</li>
            <li>Process your comments and interactions</li>
            <li>Send you newsletters and updates (with your consent)</li>
            <li>Respond to your inquiries and support requests</li>
            <li>Detect and prevent fraud or abuse</li>
            <li>Comply with legal obligations</li>
        </ul>

        <h2>3. Information Sharing</h2>
        <p>We do not sell, trade, or rent your personal information to third parties. We may share your information only in the following circumstances:</p>
        <ul>
            <li>With your explicit consent</li>
            <li>To comply with legal obligations or court orders</li>
            <li>To protect our rights, property, or safety</li>
            <li>With service providers who assist us in operating our website (under strict confidentiality agreements)</li>
        </ul>

        <h2>4. Data Security</h2>
        <p>We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. However, no method of transmission over the Internet is 100% secure.</p>

        <h2>5. Your Rights</h2>
        <p>You have the right to:</p>
        <ul>
            <li>Access your personal information</li>
            <li>Correct inaccurate or incomplete information</li>
            <li>Request deletion of your personal information</li>
            <li>Opt-out of marketing communications</li>
            <li>Request a copy of your data</li>
        </ul>
        <p>To exercise these rights, please contact us through our contact form or email.</p>

        <h2>6. Cookies</h2>
        <p>We use cookies to enhance your browsing experience. For more information about how we use cookies, please see our <a href="cookies.php" style="color: var(--primary); text-decoration: underline;">Cookie Policy</a>.</p>

        <h2>7. Third-Party Links</h2>
        <p>Our website may contain links to third-party websites. We are not responsible for the privacy practices of these external sites. We encourage you to review their privacy policies.</p>

        <h2>8. Children's Privacy</h2>
        <p>Our services are not directed to individuals under the age of 13. We do not knowingly collect personal information from children. If you believe we have collected information from a child, please contact us immediately.</p>

        <h2>9. Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new policy on this page and updating the "Last Updated" date.</p>

        <h2>10. Contact Us</h2>
        <p>If you have any questions about this Privacy Policy, please contact us through our <a href="contact.php" style="color: var(--primary); text-decoration: underline;">contact page</a>.</p>

        <div class="last-updated">
            <p><strong>Last Updated:</strong> <?= date('F j, Y') ?></p>
        </div>
    </div>
</div>

<?php include '../shared/footer.php'; ?>

