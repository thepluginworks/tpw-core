import { test, expect } from '@playwright/test';

test('inspect payments on flexiclub dashboard debug', async ({ page }) => {
  await page.goto('http://substest1.local/wp-admin/');
  await page.fill('#user_login', 'moodadmin');
  await page.fill('#user_pass', 'M00dpa55');
  await page.click('#wp-submit');
  
  // Try to find any FlexiClub related menu link
  const flexiLink = page.locator('a', { hasText: /FlexiClub/i }).first();
  if (await flexiLink.isVisible()) {
    await flexiLink.click();
  } else {
    // Fallback to direct navigation if menu not found
    await page.goto('http://substest1.local/wp-admin/admin.php?page=flexiclub-dashboard');
  }

  await page.waitForLoadState('networkidle');

  console.log('--- Page Content Debug ---');
  const headers = await page.locator('h1, h2, h3').allInnerTexts();
  console.log('Headers found:', headers);

  // Look for Payments card using broader selector
  const paymentsCard = page.locator('div, section, .card', { hasText: 'Payments' }).filter({ has: page.locator('h1, h2, h3, h4', { hasText: 'Payments' }) }).first();
  
  if (await paymentsCard.isVisible()) {
    console.log('Payments card exists: Yes');
    const statusPill = paymentsCard.locator('.status-pill, .badge, [class*="status"], .pill');
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
     // If not found by structured hierarchy, try just finding the text 'Payments' and its parent
     const paymentsHeader = page.locator('h1, h2, h3, h4, h5', { hasText: /^Payments$/ }).first();
     if (await paymentsHeader.isVisible()) {
        console.log('Payments header found');
        const cardParent = paymentsHeader.locator('xpath=./ancestor::div[contains(@class, "card") or contains(@class, "box")][1]');
        if (await cardParent.count() > 0) {
            console.log('Payments card exists: Yes (via header)');
            const statusPill = cardParent.locator('.status-pill, .badge, [class*="status"], .pill');
            if (await statusPill.isVisible()) {
                console.log('Status pill text:', await statusPill.innerText());
                console.log('Status pill classes:', await statusPill.getAttribute('class'));
            }
            console.log('Description text:', await cardParent.locator('p').first().innerText());
            const configBtn = cardParent.locator('a, button', { hasText: /Configure payments/i });
            console.log('Contains "Configure payments" button/link:', await configBtn.isVisible());
        }
     } else {
        console.log('Payments card exists: No');
     }
  }

  const quickActions = page.locator('div, section, .card', { hasText: /Quick Actions/i });
  const qaConfig = quickActions.locator('a, button', { hasText: /Configure Payments/i });
  console.log('Quick Actions contains "Configure Payments":', await qaConfig.isVisible());

  const systemStatus = page.locator('div, section, .card', { hasText: /System Status/i });
  const paymentRow = systemStatus.locator('tr, .row, li', { hasText: /Payment methods/i });
  console.log('System Status contains "Payment methods" row:', await paymentRow.isVisible());
});
