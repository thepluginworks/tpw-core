import { test } from '@playwright/test';

test('inspect dashboard admin check', async ({ page }) => {
  await page.goto('http://substest1.local/wp-admin/');
  await page.fill('#user_login', 'moodadmin');
  await page.fill('#user_pass', 'M00dpa55');
  await page.click('#wp-submit');
  
  // Wait for admin bar or something that confirms login
  await page.waitForSelector('#wpadminbar', { timeout: 10000 });
  
  console.log('--- Admin Menu Check ---');
  const menuLabels = await page.locator('#adminmenu .wp-menu-name').allInnerTexts();
  console.log('Admin menu items:', menuLabels);

  const flexiMenu = page.locator('#adminmenu a').filter({ hasText: /FlexiClub/i });
  if (await flexiMenu.count() > 0) {
      console.log('FlexiClub menu found. Clicking first instance.');
      await flexiMenu.first().click();
      await page.waitForLoadState('networkidle');
  } else {
      console.log('FlexiClub menu NOT found in sidebar.');
      await page.goto('http://substest1.local/wp-admin/admin.php?page=flexiclub-dashboard');
  }

  await page.waitForTimeout(5000);
  console.log('URL after navigation:', page.url());
  const bodyText = await page.innerText('body');
  if (bodyText.includes('not allowed')) {
      console.log('Access Denied detected.');
  }

  const paymentsHeader = page.locator('h1, h2, h3, h4, h5').filter({ hasText: /^Payments$/ }).first();
  if (await paymentsHeader.isVisible()) {
      console.log('Payments card exists: Yes');
      const card = page.locator('div, section').filter({ has: paymentsHeader }).first();
      const pill = card.locator('[class*="pill"], [class*="status"], [class*="badge"]').first();
      if (await pill.isVisible()) {
          console.log('Status pill text:', await pill.innerText());
          console.log('Status pill classes:', await pill.getAttribute('class'));
      }
      console.log('Description text:', await card.locator('p').first().innerText());
      console.log('Contains "Configure payments" button/link:', await card.locator('a, button', { hasText: /Configure payments/i }).isVisible());
  } else {
      console.log('Payments card exists: No');
  }
});
