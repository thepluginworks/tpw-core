import { test } from '@playwright/test';

test('inspect dashboard and payments', async ({ page }) => {
  await page.goto('http://substest1.local/wp-admin/');
  await page.fill('#user_login', 'moodadmin');
  await page.fill('#user_pass', 'M00dpa55');
  await page.click('#wp-submit');
  
  // Try to find FlexiClub menu and click it
  const flexiMenu = page.locator('#toplevel_page_flexiclub-dashboard, a[href*="page=flexiclub-dashboard"]');
  if (await flexiMenu.isVisible()) {
    await flexiMenu.click();
  } else {
    await page.goto('http://substest1.local/wp-admin/admin.php?page=flexiclub-dashboard');
  }

  // Wait for the specific container that likely holds the React app
  await page.waitForSelector('.flexiclub-dashboard, #wpbody-content', { timeout: 15000 });
  // Brief pause for React to render
  await page.waitForTimeout(5000);

  console.log('--- Payments Card Inspection ---');
  // Look for the "Payments" card exactly. Dashboard cards usually have a title in h2 or h3.
  const paymentsCard = page.locator('.card, .postbox, section, div[class*="Card"]').filter({ has: page.locator('h1, h2, h3, h4, h5', { hasText: /^Payments$/ }) }).first();
  
  if (await paymentsCard.isVisible()) {
    console.log('Payments card exists: Yes');
    const pill = paymentsCard.locator('.status-pill, .badge, [class*="status"], .pill').first();
    if (await pill.isVisible()) {
      console.log('Status pill text:', await pill.innerText());
      console.log('Status pill classes:', await pill.getAttribute('class'));
    } else {
      console.log('Status pill: Not found');
    }
    const desc = paymentsCard.locator('p').first();
    if (await desc.isVisible()) {
      console.log('Description text:', await desc.innerText());
    } else {
      console.log('Description text: Not found');
    }
    const configBtn = paymentsCard.locator('a, button', { hasText: /Configure payments/i });
    console.log('Contains "Configure payments" button/link:', await configBtn.isVisible());
  } else {
    console.log('Payments card exists: No');
    // Debug what is on the page
    const headlines = await page.locator('h1, h2, h3, h2, h5').allInnerTexts();
    console.log('Found headlines:', headlines);
  }

  console.log('--- Quick Actions Inspection ---');
  const qa = page.locator('.card, .postbox, section, div', { hasText: /Quick Actions/i }).first();
  const qaLink = qa.locator('a, button', { hasText: /Configure Payments/i });
  console.log('Quick Actions contains "Configure Payments":', await qaLink.isVisible());

  console.log('--- System Status Inspection ---');
  const ss = page.locator('.card, .postbox, section, div', { hasText: /System Status/i }).first();
  const ssRow = ss.locator('tr, .row, li', { hasText: /Payment methods/i });
  console.log('System Status contains "Payment methods" row:', await ssRow.isVisible());
});
