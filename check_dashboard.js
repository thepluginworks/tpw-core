const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    try {
        await page.goto('http://substest1.local/wp-admin/');
        await page.fill('#user_login', 'moodadmin');
        await page.fill('#user_pass', 'M00dpa55');
        await page.click('#wp-submit');
        await page.waitForURL(/wp-admin\//);

        await page.goto('http://substest1.local/wp-admin/admin.php?page=tpw-flexiclub-dashboard');
        await page.waitForSelector('.tpw-flexiclub-dashboard__overview-card');

        const data = await page.evaluate(() => {
            const metrics = Array.from(document.querySelectorAll('.tpw-flexiclub-dashboard__overview-card')).map(card => {
                const title = card.querySelector('h3')?.innerText.trim();
                const statusEl = card.querySelector('.tpw-flexiclub-dashboard__status');
                const metric = card.querySelector('.tpw-flexiclub-dashboard__overview-metric')?.innerText.trim();
                const description = card.querySelector('.tpw-flexiclub-dashboard__overview-body p')?.innerText.trim();
                const actionText = card.querySelector('.tpw-flexiclub-dashboard__overview-action')?.innerText.trim();
                return {
                    title,
                    statusText: statusEl?.innerText.trim(),
                    statusClasses: statusEl?.className,
                    metric,
                    description,
                    actionText
                };
            });

            const systemStatus = Array.from(document.querySelectorAll('.tpw-flexiclub-dashboard__system-item')).map(item => {
                const labelEl = item.querySelector('.tpw-flexiclub-dashboard__system-label');
                const statusEl = item.querySelector('.tpw-flexiclub-dashboard__status');
                return {
                    label: labelEl?.innerText.trim(),
                    statusText: statusEl?.innerText.trim(),
                    statusClasses: statusEl?.className
                };
            });

            return { metrics, systemStatus };
        });

        console.log(JSON.stringify(data, null, 2));
    } catch (error) {
        console.error(error);
    } finally {
        await browser.close();
    }
})();
