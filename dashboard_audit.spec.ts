import { test } from '@playwright/test';

test('audit flexiclub dashboard', async ({ page }) => {
  await page.goto('http://substest1.local/wp-admin/');
  await page.fill('#user_login', 'moodadmin');
  await page.fill('#user_pass', 'M00dpa55');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar');

  await page.goto('http://substest1.local/wp-admin/admin.php?page=tpw-flexiclub-dashboard');
  await page.waitForSelector('.tpw-flexiclub-dashboard');

  console.log('--- Overview Cards ---');
  const cards = page.locator('.tpw-flexiclub-dashboard__overview-card');
  const cardCount = await cards.count();
  for (let i = 0; i < cardCount; i++) {
    const card = cards.nth(i);
    const title = await card.locator('.tpw-flexiclub-dashboard__overview-card-title').innerText().catch(() => '');
    const statusPill = card.locator('.tpw-flexiclub-dashboard__status-pill');
    const statusText = await statusPill.innerText().catch(() => '');
    const statusClasses = await statusPill.getAttribute('class').catch(() => '');
    const description = await card.locator('.tpw-flexiclub-dashboard__overview-card-description').innerText().catch(() => '');
    const metric = await card.locator('.tpw-flexiclub-dashboard__overview-card-metric').innerText().catch(() => '');
    const action = await card.locator('.tpw-flexiclub-dashboard__overview-card-action').innerText().catch(() => '');
    
    console.log(JSON.stringify({ title, statusText, statusClasses, description, metric, action }));
  }

  console.log('--- Quick Actions ---');
  const qa = page.locator('.tpw-flexiclub-dashboard__quick-actions');
  const qaConfig = qa.locator('a, button').filter({ hasText: /Configure Payments/i });
  console.log('Quick Actions contains "Configure Payments":', await qaConfig.isVisible());

  console.log('--- System Status ---');
  const ss = page.locator('.tpw-flexiclub-dashboard__system-status');
  const ssRow = ss.locator('.tpw-flexiclub-dashboard__system-status-item-label').filter({ hasText: /Payment methods/i });
  console.log('System Status contains "Payment methods" label:', await ssRow.isVisible());

  console.log('--- Settings Nav Tabs ---');
  await page.goto('http://substest1.local/wp-admin/admin.php?page=tpw-flexiclub-settings');
  const tabs = page.locator('.nav-tab-wrapper .nav-tab');
  const tabTexts = await tabs.allInnerTexts();
  console.log('Nav Tabs:', tabTexts);
  console.log('Settings contains "Payment Methods" tab:', tabTexts.some(t => /Payment Methods/i.test(t)));
});
