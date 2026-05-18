import { test } from '@playwright/test';

test('inspect dashboard', async ({ page }) => {
  await page.goto('http://substest1.local/wp-admin/');
  await page.fill('#user_login', 'moodadmin');
  await page.fill('#user_pass', 'M00dpa55');
  await page.click('#wp-submit');
  
  // Navigate to dashboard
  await page.goto('http://substest1.local/wp-admin/admin.php?page=flexiclub-dashboard');
  await page.waitForTimeout(2000); // Wait for potential React render

  // Extract all card titles or headings
  const cards = await page.evaluate(() => {
    const list = [];
    document.querySelectorAll('.card, [class*="card"], .postbox, section').forEach(el => {
      const h = el.querySelector('h1, h2, h3, h4, h5');
      if (h) list.push({ 
        title: h.innerText.trim(), 
        html: el.innerHTML.substring(0, 500),
        classes: el.className 
      });
    });
    return list;
  });
  console.log('--- Cards Found ---');
  console.log(JSON.stringify(cards, null, 2));

  const paymentsCard = page.locator('div, section, .card, .postbox').filter({ has: page.locator('h1, h2, h3, h4, h5', { hasText: /^Payments$/ }) }).first();
  
  if (await paymentsCard.isVisible()) {
    console.log('Payments card exists: Yes');
    const pill = paymentsCard.locator('.status-pill, .badge, [class*="status"], .pill').first();
    if (await pill.isVisible()) {
      console.log('Status pill text:', await pill.innerText());
      console.log('Status pill classes:', await pill.getAttribute('class'));
    } else {
      console.log('Status pill: Not found');
    }
    console.log('Description text:', await paymentsCard.locator('p').first().innerText());
    console.log('Contains "Configure payments" button/link:', await paymentsCard.locator('a, button', { hasText: /Configure payments/i }).isVisible());
  } else {
    console.log('Payments card exists: No');
  }

  const qa = page.locator('div, section, .card, .postbox', { hasText: /Quick Actions/i });
  console.log('Quick Actions contains "Configure Payments":', await qa.locator('a, button', { hasText: /Configure Payments/i }).isVisible());

  const ss = page.locator('div, section, .card, .postbox', { hasText: /System Status/i });
  console.log('System Status contains "Payment methods" row:', await ss.locator('tr, .row, li', { hasText: /Payment methods/i }).isVisible());
});
