import { test, expect } from '@playwright/test';

test('detailed flexiclub dashboard audit', async ({ page }) => {
  test.setTimeout(90000);
  await page.goto('http://substest1.local/wp-login.php');
  await page.fill('#user_login', 'moodadmin');
  await page.fill('#user_pass', 'M00dpa55');
  await page.click('#wp-submit');
  await page.waitForSelector('#wpadminbar');

  await page.goto('http://substest1.local/wp-admin/admin.php?page=tpw-flexiclub-dashboard');
  await page.waitForSelector('.tpw-flexiclub-dashboard', { timeout: 15000 });

  console.log('--- HTML DUMP ---');
  const dashboardHtml = await page.content();
  console.log(dashboardHtml.substring(0, 5000)); // Print start of HTML for debugging if needed

  console.log('--- Club Overview Cards ---');
  // Re-verify the selectors based on standard TPW dashboard layout
  const cards = page.locator('.tpw-flexiclub-dashboard__overview-card');
  const cardCount = await cards.count();
  console.log(`Found ${cardCount} cards`);
  
  for (let i = 0; i < cardCount; i++) {
    const card = cards.nth(i);
    const title = await card.locator('h3').innerText().then(t => t.trim()).catch(() => 'N/A');
    const statusEl = card.locator('.tpw-flexiclub-dashboard__overview-card-status');
    const statusText = await statusEl.innerText().then(t => t.trim()).catch(() => 'N/A');
    const statusClasses = await statusEl.getAttribute('class').catch(() => 'N/A');
    const metricText = await card.locator('.tpw-flexiclub-dashboard__overview-card-metric').innerText().then(t => t.trim()).catch(() => 'N/A');
    const descText = await card.locator('p').first().innerText().then(t => t.trim()).catch(() => 'N/A');
    
    console.log(`Title: ${title}`);
    console.log(`Status Text: ${statusText}`);
    console.log(`Status Classes: ${statusClasses}`);
    console.log(`Metric: ${metricText}`);
    console.log(`Description: ${descText}`);
    console.log('---');
  }

  console.log('--- System Status Rows ---');
  const rows = page.locator('.tpw-flexiclub-dashboard__system-status-table tr');
  const rowCount = await rows.count();
  for (let i = 0; i < rowCount; i++) {
    const row = rows.nth(i);
    const label = await row.locator('th').innerText().then(t => t.trim()).catch(() => 'N/A');
    const valueEl = row.locator('td');
    const valueText = await valueEl.innerText().then(t => t.trim()).catch(() => 'N/A');
    const statusEl = valueEl.locator('span.status');
    let statusClasses = 'N/A';
    if (await statusEl.count() > 0) {
        statusClasses = await statusEl.getAttribute('class');
    }
    
    console.log(`Label: ${label}`);
    console.log(`Value: ${valueText}`);
    console.log(`Status Classes: ${statusClasses}`);
    console.log('---');
  }
});
