<?php 
require_once '../shared/functions.php';
$page_title = 'Cookie Policy - ' . SITE_NAME;
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

    .cookie-table {
        width: 100%;
        border-collapse: collapse;
        margin: 1.5rem 0;
    }

    .cookie-table th,
    .cookie-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid var(--gray-200);
    }

    .cookie-table th {
        background: var(--gray-100);
        font-weight: 600;
        color: var(--gray-800);
    }

    .cookie-table td {
        color: var(--gray-700);
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

        .cookie-table {
            font-size: 0.9rem;
        }

        .cookie-table th,
        .cookie-table td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>

<div class="policy-container">
    <div class="policy-header">
        <h1>
            <i class="fas fa-cookie-bite"></i>
            Cookie Policy
        </h1>
        <p>Learn how we use cookies to enhance your browsing experience.</p>
    </div>

    <div class="policy-content">
        <h2>1. What Are Cookies?</h2>
        <p>Cookies are small text files that are placed on your computer or mobile device when you visit a website. They are widely used to make websites work more efficiently and provide information to the website owners.</p>
        <p>Cookies allow a website to recognize your device and store some information about your preferences or past actions.</p>

        <h2>2. How We Use Cookies</h2>
        <p>We use cookies on <?= SITE_NAME ?> to:</p>
        <ul>
            <li>Remember your login status and preferences</li>
            <li>Improve website functionality and user experience</li>
            <li>Analyze how our website is used</li>
            <li>Provide personalized content and features</li>
            <li>Ensure website security</li>
        </ul>

        <h2>3. Types of Cookies We Use</h2>
        
        <h3>Essential Cookies</h3>
        <p>These cookies are necessary for the website to function properly. They enable core functionality such as security, network management, and accessibility.</p>
        <table class="cookie-table">
            <thead>
                <tr>
                    <th>Cookie Name</th>
                    <th>Purpose</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PHPSESSID</td>
                    <td>Maintains your session and login status</td>
                    <td>Session</td>
                </tr>
                <tr>
                    <td>csrf_token</td>
                    <td>Security token to prevent cross-site request forgery</td>
                    <td>Session</td>
                </tr>
            </tbody>
        </table>

        <h3>Functional Cookies</h3>
        <p>These cookies allow the website to remember choices you make and provide enhanced, personalized features.</p>
        <table class="cookie-table">
            <thead>
                <tr>
                    <th>Cookie Name</th>
                    <th>Purpose</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>user_preferences</td>
                    <td>Stores your display preferences and settings</td>
                    <td>1 year</td>
                </tr>
                <tr>
                    <td>bookmark_data</td>
                    <td>Remembers your bookmarked posts</td>
                    <td>1 year</td>
                </tr>
            </tbody>
        </table>

        <h3>Analytics Cookies</h3>
        <p>These cookies help us understand how visitors interact with our website by collecting and reporting information anonymously.</p>
        <p>We may use analytics services that set cookies to track website usage patterns. This information helps us improve our website and user experience.</p>

        <h2>4. Third-Party Cookies</h2>
        <p>Some cookies are placed by third-party services that appear on our pages. We may use third-party services for:</p>
        <ul>
            <li>Analytics and website performance monitoring</li>
            <li>Social media integration</li>
            <li>Content delivery networks</li>
        </ul>
        <p>These third parties may use cookies to collect information about your online activities across different websites. We do not control these cookies.</p>

        <h2>5. Managing Cookies</h2>
        <p>You have the right to accept or reject cookies. Most web browsers automatically accept cookies, but you can usually modify your browser settings to decline cookies if you prefer.</p>
        <p><strong>How to manage cookies in popular browsers:</strong></p>
        <ul>
            <li><strong>Chrome:</strong> Settings → Privacy and Security → Cookies and other site data</li>
            <li><strong>Firefox:</strong> Options → Privacy & Security → Cookies and Site Data</li>
            <li><strong>Safari:</strong> Preferences → Privacy → Cookies and website data</li>
            <li><strong>Edge:</strong> Settings → Privacy, search, and services → Cookies and site permissions</li>
        </ul>
        <p><strong>Note:</strong> Blocking or deleting cookies may impact your ability to use certain features of our website.</p>

        <h2>6. Cookie Consent</h2>
        <p>By continuing to use our website, you consent to our use of cookies in accordance with this Cookie Policy. If you do not agree to our use of cookies, you should set your browser settings accordingly or discontinue use of our website.</p>

        <h2>7. Updates to This Policy</h2>
        <p>We may update this Cookie Policy from time to time to reflect changes in our practices or for other operational, legal, or regulatory reasons. Please review this page periodically for the latest information on our use of cookies.</p>

        <h2>8. More Information</h2>
        <p>For more information about cookies and how they work, you can visit:</p>
        <ul>
            <li><a href="https://www.allaboutcookies.org" target="_blank" rel="noopener" style="color: var(--primary); text-decoration: underline;">All About Cookies</a></li>
            <li><a href="https://www.youronlinechoices.com" target="_blank" rel="noopener" style="color: var(--primary); text-decoration: underline;">Your Online Choices</a></li>
        </ul>

        <h2>9. Contact Us</h2>
        <p>If you have any questions about our use of cookies, please contact us through our <a href="contact.php" style="color: var(--primary); text-decoration: underline;">contact page</a>.</p>

        <div class="last-updated">
            <p><strong>Last Updated:</strong> <?= date('F j, Y') ?></p>
        </div>
    </div>
</div>

<?php include '../shared/footer.php'; ?>

