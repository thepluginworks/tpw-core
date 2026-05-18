import { test } from '@playwright/test';

test('inspect dashboard via iframe', async ({ page }) => {
  await page.goto('http://substest1.local/wp-admin/');
  await page.fill('#user_login', 'moodadmin');
  await page.fill('#user_pass', 'M00dpa55');
  await page.click('#wp-submit');
  
  await page.goto('http://substest1.local/wp-admin/admin.php?page=flexiclub-dashboard');
  
  // Wait for React app to potentially load (even if container empty initially)
  const root = page.locator('#flexiclub-dashboard-root, #wpbody-content');
  await root.waitFor({ state: 'visible', timeout: 10000 });
  await page.waitForTimeout(3000); 

  console.log('--- HTML Snapshot ---');
  const bodyHtml = await page.content();
  // Filter for relevant substrings to keep log clean but verify structure
  if (bodyHtml.includes('Payments')) console.log('Found "Payments" in body HTML');
  if (bodyHtml.includes('Quick Actions')) console.log('Found "Quick Actions" in body HTML');
  if (bodyHtml.includes('System Status')) console.log('Found "System Status" in body HTML');

  // Universal find for the title
  const paymentsTitle = page.locator('h1, h2, h3, h4, h5, span, div').filter({ hasText: /^Payments$/ }).first();
  if (await paymentsTitle.isVisible()) {
      console.log('Payments title found');
      // Look for parent card-like container
      const card = page.locator('div, section').filter({ has: paymentsTitle }).filter({ has: page.locator('p') }).first();
      if (await card.isVisible()) {
          console.log('Payments card exists: Yes');
          const pill = card.locator('.status-pill, .badge, [class*="status"], .pill').first();
          if (await pill.isVisible()) {
            console.log('Status pill text:', await pill.innerText());
            console.log('Status pill classes:', await pill.getAttribute('class'));
          } else {
            console.log('Status pill: Not found');
          }
          console.log('Description text:', await card.locator('p').first().innerText());
          console.log('Contains "Configure payments" button/link:', await card.locator('a, button', { hasText: /Configure payments/i }).isVisible());
      }
  } else {
      console.log('Payments card exists: No (Title not found)');
  }

  const qa = page.locator('div, section', { hasText: /Quick Actions/i }).first();
  console.log('Quick Actions contains "Configure Payments":', await qa.locator('a, button', { hasText: /Configure Payments/i }).isVisible());

  const ss = page.locator('div, section', { hasText: /System Status/i }).first();
  console.log('System Status contains "Payment methods" row:', await ss.locator('tr, .row, li', { hasText: /Payment methods/i }).isVisible());
});
