const { test, expect } = require('@playwright/test');

test('Validate FlexiClub Dashboard Payments Visibility', async ({ page }) => {
    // Login
    await page.goto('http://substest1.local/wp-login.php');
    await page.fill('#user_login', 'moodadmin');
    await page.fill('#user_pass', 'M00dpa55');
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/);

    // Go to FlexiClub Dashboard - use the correct query param from common patterns
    await page.goto('http://substest1.local/wp-admin/admin.php?page=tpw-flexiclub-dashboard');
    
    // Debug: output page content if title not found
    const title = page.locator('h1');
    const titles = await title.allInnerTexts();
    console.log('Available H1s:', titles);

    // 1. Dashboard loads and does not go blank
    await expect(page.locator('.wrap')).toBeVisible();

    // 2. Payments card in Club Overview
    const paymentsCard = page.locator('.tpw-card').filter({ hasText: /Payments/i });
    const isPaymentsCardVisible = await paymentsCard.isVisible();
    console.log('Payments Card Visible: ' + isPaymentsCardVisible);

    if (isPaymentsCardVisible) {
        const statusPill = paymentsCard.locator('.tpw-pill');
        if (await statusPill.count() > 0) {
            const statusLabel = await statusPill.textContent();
            console.log('Payments Card Status Label: ' + statusLabel.trim());
            const pillClass = await statusPill.getAttribute('class');
            console.log('Payments Card pill class: ' + pillClass);
        } else {
            console.log('Payments Card Status Label: (no pill found)');
        }

        const description = await paymentsCard.locator('.tpw-card-description, p').first().textContent();
        console.log('Payments Card Description: ' + description.trim());

        const configureBtn = paymentsCard.locator('a:has-text("Configure payments")');
        console.log('Configure payments button exists: ' + await configureBtn.count());
    }

    // 3. Quick Actions
    const quickActions = page.locator('.tpw-quick-actions');
    const hasConfigurePaymentsAction = await quickActions.locator('a:has-text("Configure Payments")').count() > 0;
    console.log('Quick Actions includes Configure Payments: ' + hasConfigurePaymentsAction);

    // 4. System Status
    const systemStatus = page.locator('.tpw-system-status');
    const hasPaymentMethodsStatus = await systemStatus.locator('li:has-text("Payment methods")').count() > 0;
    console.log('System Status includes Payment methods: ' + hasPaymentMethodsStatus);

    // 5. Settings Tabs
    await page.goto('http://substest1.local/wp-admin/admin.php?page=tpw-flexiclub-settings');
    const settingsTabs = page.locator('.nav-tab-wrapper');
    const hasPaymentMethodsTab = await settingsTabs.locator('a:has-text("Payment Methods")').count() > 0;
    console.log('Settings Tabs include Payment Methods: ' + hasPaymentMethodsTab);
});
