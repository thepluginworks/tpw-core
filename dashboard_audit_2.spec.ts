import { test } from '@playwright/test';

test('audit flexiclub dashboard simple', async ({ page }) => {
  test.setTimeout(60000);
  await page.goto('http://substest1.local/wp-admin/');
  await page.fill('#user_login', 'moodadmin');
  await page.fill('#user_pass', 'M00dpa55');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar');

  await page.goto('http://substest1.local/wp-admin/admin.php?page=tpw-flexiclub-dashboard');
  
  // Wait for the main container
  await page.waitForSelector('.tpw-flexiclub-dashboard', { timeout: 10000 });

  console.log('--- Overview Cards ---');
  // Use a more generic selector for cards if the specific classes didn't work as expected
  const cards = page.locator('.tpw-flexiclub-dashboard__overview-card');
  const cardCount = await cards.count();
  for (let i = 0; i < cardCount; i++) {
    const card = cards.nth(i);
    const text = await card.innerText();
    const classes = await card.getAttribute('class');
    console.log(`Card ${i} classes: ${classes}`);
    console.log(`Card ${i} text: ${text.replace(/\n/g, ' ')}`);
  }

  console.log('--- Quick Actions ---');
  const qa = page.getByText(/Quick Actions/i);
  if (await qa.isVisible()) {
      const qaContainer = page.locator('div, section').filter({ has: qa }).first();
      const qaConfig = qaContainer.locator('a, button').filter({ hasText: /Configure Payments/i });
      console.log('Quick Actions contains "Configure Payments":', await qaConfig.isVisible());
  } else {
      console.log('Quick Actions section not found by text');
  }

  console.log('--- System Status ---');
  const ss = page.getByText(/System Status/i);
  if (await ss.isVisible()) {
      const ssContainer = page.locator('div, section').filter({ has: ss }).first();
      const ssRow = ssContainer.locator('tr, li, .item, div').filter({ hasText: /Payment methods/i });
      console.log('System Status contains "Payment methods" row:', await ssRow.isVisible());
  } else {
      console.log('System Status section not found by text');
  }

  console.log('--- Settings Nav Tabs ---');
  await page.goto('http://substest1.local/wp-admin/admin.php?page=tpw-flexiclub-settings');
  await page.waitForSelector('.nav-tab-wrapper');
  const tabs = page.locator('.nav-tab-wrapper .nav-tab');
  const tabTexts = await tabs.allInnerTexts();
  console.log('Nav Tabs:', tabTexts);
});
