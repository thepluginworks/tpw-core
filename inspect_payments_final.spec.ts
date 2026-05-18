import { test } from '@playwright/test';

test('final focused inspection', async ({ page }) => {
  await page.goto('http://substest1.local/wp-admin/');
  await page.fill('#user_login', 'moodadmin');
  await page.fill('#user_pass', 'M00dpa55');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar');
  
  // Navigate to FlexiClub Dashboard using the correct slug found in last run
  await page.goto('http://substest1.local/wp-admin/admin.php?page=tpw-flexiclub-dashboard');
  await page.waitForTimeout(5000);

  console.log('--- Payments Card ---');
  // Specifically look for the card with title 'Payments'
  const paymentsCardTitle = page.locator('h1, h2, h3, h4, h5, span').filter({ hasText: /^Payments$/ }).first();
  if (await paymentsCardTitle.isVisible()) {
    console.log('Payments card exists: Yes');
    const card = page.locator('div, section').filter({ has: paymentsCardTitle }).first();
    const pill = card.locator('[class*="pill"], [class*="status"], [class*="badge"]').first();
    if (await pill.isVisible()) {
      console.log('Status pill text:', await pill.innerText());
      console.log('Status pill classes:', await pill.getAttribute('class'));
    } else {
      console.log('Status pill: Not found');
    }
    const descText = await card.locator('p').first().innerText();
    console.log('Description text:', descText);
    const configBtn = card.locator('a, button').filter({ hasText: /Configure payments/i });
    console.log('Contains "Configure payments" button/link:', await configBtn.isVisible());
  } else {
    console.log('Payments card exists: No');
  }

  console.log('--- Quick Actions ---');
  const qa = page.locator('div, section').filter({ has: page.locator('h1, h2, h3, h4, h5, span').filter({ hasText: /Quick Actions/i }) }).first();
  if (await qa.isVisible()) {
    const qaConfig = qa.locator('a, button').filter({ hasText: /Configure Payments/i });
    console.log('Quick Actions contains "Configure Payments":', await qaConfig.isVisible());
  } else {
    console.log('Quick Actions section not found');
  }

  console.log('--- System Status ---');
  const ss = page.locator('div, section').filter({ has: page.locator('h1, h2, h3, h4, h5, span').filter({ hasText: /System Status/i }) }).first();
  if (await ss.isVisible()) {
    const ssRow = ss.locator('tr, .row, li').filter({ hasText: /Payment methods/i });
    console.log('System Status contains "Payment methods" row:', await ssRow.isVisible());
  } else {
    console.log('System Status section not found');
  }
});
