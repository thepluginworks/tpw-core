import { test } from '@playwright/test';

test('inspect dashboard final attempt', async ({ page }) => {
  await page.goto('http://substest1.local/wp-admin/');
  await page.fill('#user_login', 'moodadmin');
  await page.fill('#user_pass', 'M00dpa55');
  await page.click('#wp-submit');
  
  await page.goto('http://substest1.local/wp-admin/admin.php?page=flexiclub-dashboard', { waitUntil: 'domcontentloaded' });
  
  // Wait longer for any content to appear in the main area
  await page.waitForTimeout(10000);

  console.log('--- Payments Card Inspection ---');
  // Be extremely permissive with selectors
  const pHeading = page.getByRole('heading', { name: 'Payments', exact: true }).or(page.locator('span, div, h1, h2, h3, h4').filter({ hasText: /^Payments$/ })).first();
  
  if (await pHeading.isVisible()) {
    console.log('Payments title found');
    const card = page.locator('div, section').filter({ has: pHeading }).filter({ has: page.locator('p, a, button') }).first();
    if (await card.isVisible()) {
        console.log('Payments card exists: Yes');
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
    }
  } else {
    // If not found, let's see if we are even on the right page
    console.log('Payments card exists: No');
    console.log('Current URL:', page.url());
    const textOnPage = await page.evaluate(() => document.body.innerText.substring(0, 1000));
    console.log('Snippet of page text:', textOnPage.replace(/\n/g, ' '));
  }

  const qa = page.locator('div, section', { hasText: /Quick Actions/i }).first();
  if (await qa.isVisible()) {
    console.log('Quick Actions contains "Configure Payments":', await qa.locator('a, button', { hasText: /Configure Payments/i }).isVisible());
  } else {
    console.log('Quick Actions section not found');
  }

  const ss = page.locator('div, section', { hasText: /System Status/i }).first();
  if (await ss.isVisible()) {
    console.log('System Status contains "Payment methods" row:', await ss.locator('tr, .row, li', { hasText: /Payment methods/i }).isVisible());
  } else {
    console.log('System Status section not found');
  }
});
