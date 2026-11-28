<?php

/* Texts of the public website pages other than the home page (English). */
return [
    'nav' => [
        'features' => 'Features', 'solutions' => 'Solutions', 'customers' => 'Customers', 'pricing' => 'Pricing',
        'docs' => 'Docs', 'contact' => 'Contact', 'resources' => 'Resources', 'company' => 'Company',
        'terms' => 'Terms of use', 'privacy' => 'Privacy policy', 'all_features' => 'All features',
    ],
    'common' => [
        'learn_more' => 'Learn more', 'back' => 'Back', 'included_in' => 'Included in plans', 'all_plans' => 'All plans',
        'highlights' => 'What it does', 'benefits' => 'Why it matters', 'related' => 'Related features', 'start_trial' => 'Start free trial',
        'talk_to_us' => 'Talk to us', 'updated' => 'Last updated', 'on_this_page' => 'On this page', 'home' => 'Home',
    ],
    'features' => [
        'title' => 'Everything your workshop needs in one system',
        'lead' => 'Over twenty connected modules from reception to accounting. Pick a feature to read the details.',
        'cta_title' => 'Try every feature for free',
        'cta_text' => 'A full trial without a credit card, and help setting up your data.',
    ],
    'solutions' => [
        'title' => 'Solutions for every kind of automotive service business',
        'lead' => 'Independent garage, multi-branch dealer or parts shop — the system adapts to the way you work.',
        'items' => [
            'independent' => ['icon' => 'bi-tools', 'title' => 'Independent garages', 'text' => 'Organise reception, job cards and invoicing, see the profit of every car and remind customers of their next service.',
                'points' => ['Reception, job cards and quotations', 'Tax invoices and POS', 'Service reminders and loyalty'],
                'features' => ['job-cards', 'digital-inspection', 'pos-sales', 'loyalty']],
            'dealers' => ['icon' => 'bi-building', 'title' => 'Dealers & multi-branch service centres', 'text' => 'Branches, warehouses and role-based access, capacity planning, quality management and consolidated reports.',
                'points' => ['Branches, warehouses and roles', 'Capacity planning and workshop screens', 'Quality management and consolidated reporting'],
                'features' => ['workshop-planning', 'quality-management', 'reports', 'accounting']],
            'parts' => ['icon' => 'bi-gear-wide-connected', 'title' => 'Parts shops & distributors', 'text' => 'Stock with cross references, a fast POS, purchasing with automatic reorder and dead-stock analytics.',
                'points' => ['OEM and alternative numbers', 'Barcode POS', 'Reorder and analytics'],
                'features' => ['spare-parts', 'inventory-analytics', 'purchasing', 'advanced-stock']],
            'fleets' => ['icon' => 'bi-truck-front', 'title' => 'Fleets & insurers', 'text' => 'Fleet contracts with limits, approvals and one monthly invoice; insurance claims with assessment and excess.',
                'points' => ['Contracts and spending limits', 'Fleet manager approvals', 'Insurance claims'],
                'features' => ['insurance-fleet', 'job-cards', 'reports']],
            'body' => ['icon' => 'bi-palette', 'title' => 'Body & paint shops', 'text' => 'Photo damage inspection, insurance claims, outside work and yard tracking.',
                'points' => ['Photo inspections and reports', 'Insurance claims', 'Yard tracking and outside work'],
                'features' => ['digital-inspection', 'insurance-fleet', 'subcontracting', 'vehicle-tracking']],
            'services' => ['icon' => 'bi-life-preserver', 'title' => 'Roadside & subscription services', 'text' => 'Yearly maintenance memberships with recurring income and roadside assistance priced by distance.',
                'points' => ['Yearly memberships', 'Tow and assistance calls', 'Customer portal'],
                'features' => ['memberships', 'roadside', 'customer-portal']],
        ],
    ],
    'customers' => [
        'title' => 'Our customers',
        'lead' => 'Workshops and service centres that run their day on our platform.',
        'empty_title' => 'Be one of our first customers',
        'empty_text' => 'We are onboarding workshops across Oman. Start your free trial and we will help you move your data and train your team.',
        'testimonials' => 'What they say',
        'sectors' => 'Sectors we serve',
        'join' => 'Join them',
    ],
    'contact' => [
        'title' => 'Contact us',
        'lead' => 'A question about the system or pricing, or would you like a demo? Write to us and we will get back to you as soon as possible.',
        'name' => 'Name', 'company' => 'Company / workshop', 'email' => 'Email', 'phone' => 'Phone',
        'topic' => 'Topic', 'message' => 'Your message', 'send' => 'Send',
        'topics' => ['sales' => 'Sales & pricing', 'demo' => 'Request a demo', 'support' => 'Technical support', 'partnership' => 'Partnership', 'other' => 'Other'],
        'sent_title' => 'Message received',
        'sent_text' => 'Thank you for contacting us. We will get back to you shortly.',
        'direct' => 'Reach us directly',
        'privacy_note' => 'We use your details only to answer your message. See our privacy policy.',
    ],
    'docs' => [
        'title' => 'Documentation',
        'lead' => 'Step-by-step guides to set up the system and run your workshop.',
        'search' => 'Search the docs…',
        'groups' => ['start' => 'Getting started', 'workshop' => 'Workshop', 'parts' => 'Stock & purchasing', 'sales' => 'Sales & finance', 'more' => 'More'],
        'not_found' => 'No results found.',
        'help' => 'Didn\'t find what you need?',
        'help_text' => 'Our team is ready to help.',
        'prev' => 'Previous', 'next' => 'Next',
    ],
    'legal' => [
        'terms_title' => 'Terms of use',
        'privacy_title' => 'Privacy policy',
        'terms' => <<<'MD'
## 1. Acceptance
By using the automotive workshop management platform (the "Service") you agree to these terms. If you use the Service on behalf of a company, you confirm that you are authorised to bind it.

## 2. Account & subscription
- Provide accurate information when registering and keep your login details confidential.
- Subscriptions are billed monthly or yearly according to the chosen plan and renew unless cancelled.
- The trial is free for a limited period; afterwards the Service is suspended until you subscribe.
- If payment is late, the Service may be limited to read-only after notice.

## 3. Your data
- Data you enter remains yours; we process it only to provide the Service.
- You can export your data at any time. After the subscription ends we keep it for a limited period and then delete it.
- You are responsible for the accuracy of your data, the invoices issued from your account and your tax compliance.

## 4. Acceptable use
You may not use the Service for unlawful activity, attempt to access others' data, disrupt the Service or resell it without a written agreement.

## 5. Availability & support
We aim for round-the-clock availability with regular backups. The Service may pause for scheduled maintenance, announced in advance where possible.

## 6. Limitation of liability
The Service is provided "as is". We are not liable for indirect damages or loss of profit, and our liability is in any case limited to the fees paid in the three months before the claim.

## 7. Changes
We may update these terms and will notify you of material changes before they take effect.

## 8. Governing law
These terms are governed by the laws of the Sultanate of Oman, whose courts have jurisdiction over any dispute.
MD,
        'privacy' => <<<'MD'
## 1. Who we are
We provide a cloud platform for automotive workshops and service centres. This policy explains what data we collect and how we use and protect it.

## 2. Data we collect
- **Account data:** name, email, phone and company name.
- **Operational data:** customers, vehicles, invoices and stock you enter — you control this data.
- **Technical data:** IP address, browser type and login records for security.
- **Website forms:** what you send through the trial or contact forms.

## 3. How we use it
To provide and operate the Service, support, billing, security and fraud prevention, product improvement and communication about your account. We never sell your data.

## 4. Sharing
Only with essential service providers (hosting, SMS, email, payment gateways) to the extent needed, or when required by a competent authority.

## 5. Protection
Encrypted connections, per-company data isolation, role-based access, two-factor authentication, an audit log and regular backups.

## 6. Retention
We keep data for the life of the subscription and a limited period after cancellation, except what the law requires us to keep.

## 7. Your rights
You can ask to access, correct, export or delete your data by contacting us.

## 8. Cookies
We use essential cookies for sign-in and security, and anonymous analytics if enabled.

## 9. Contact
For any privacy question, reach us through the contact page.
MD,
    ],
];
