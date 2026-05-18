import { test, expect } from '@playwright/test';

test('inspect payments on flexiclub dashboard', async ({ page }) => {
  // Login
  await page.goto('http://substest1.local/wp-admin/');
  await page.fill('#user_login', 'moodadmin');
  await page.fill('#user_pass', 'M00dpa55');
  await page.click('#wp-submit');
  
  // Go to FlexiClub dashboard - assuming it's under an admin menu or direct URL
  // Based on project name 'tpw-flexiclub', let's try to find the menu link or guess URL
  await page.goto('http://substest1.local/wp-admin/admin.php?page=flexiclub-dashboard');

  console.log('--- Payments Card Inspection ---');
  const paymentsCard = page.locator('.card', { hasText: 'Payments' }).first();
  if (await paymentsCard.isVisible()) {
    console.log('Payments card exists: Yes');
    const statusPill = paymentsCard.locator('.status-pill, .badge, [class*="status"]');
    if (await statusPill.isVisible()) {
      console.log('Status pill text:', await statusPill.innerText());
      console.log('Status pill classes:', await statusPill.getAttribute('class'));
    } else {
      console.log('Status pill: Not found');
    }
    console.log('Description text:', await paymentsCard.locator('p').first().innerText());
    const configBtn = paymentsCard.locator('a, button', { hasText: /Configure payments/i });
    console.log('Contains "Configure payments" button/link:', await configBtn.isVisible());
  } else {
    console.log('Payments card exists: No');
  }

  console.log('--- Quick Actions Inspection ---');
  const quickActions = page.locator('.card', { hasText: 'Quick Actions' });
  const qaConfig = quickActions.locator('a, button', { hasText: /Configure Payments/i });
  console.log('Quick Actions contains "Configure Payments":', await qaConfig.isVisible());

  console.log('--- System Status Inspection ---');
  const systemStatus = page.locator('.card', { hasText: 'System Status' });
  const paymentRow = systemStatus.locator('tr, .row', { hasText: /Payment methods/i });
  console.log('System Status contains "Payment methods" row:', await paymentRow.isVisible());
});
