const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const args = process.argv.slice(2);
  let pdfPath = args[0];
  let footerTemplatePath = args[1];
  let options = {};

  if (args[2]) {
    try {
      options = JSON.parse(args[2]);
    } catch (e) {
      console.error("Error parsing options:", e);
    }
  }

  if (!pdfPath) {
    console.error("Usage: node generate-pdf-playwright.js <pdfPath> [footerTemplatePath] [options]");
    process.exit(1);
  }

  // Læs HTML fra stdin
  let html = '';
  process.stdin.setEncoding('utf8');
  for await (const chunk of process.stdin) {
    html += chunk;
  }

  // Læs footer-template, hvis angivet; ellers brug en standard
  let footerTemplate = '';
  if (footerTemplatePath && fs.existsSync(footerTemplatePath)) {
    footerTemplate = fs.readFileSync(footerTemplatePath, 'utf8');
  }
  if (!footerTemplate) {
    footerTemplate = `
      <div style="font-size:10px; width:100%; text-align:center; border-top:1px solid #ddd; padding-top:5px;">
        Side <span class="pageNumber"></span> af <span class="totalPages"></span>
      </div>
    `;
  }

  // Options med standardværdier
  const pdfFormat = options.format || "A4";
  const landscape = options.landscape || false;
  const margin = options.margin || { top: "10mm", bottom: "20mm", left: "10mm", right: "10mm" };

  // Launch Playwright Chromium
  const browser = await chromium.launch({
    headless: true,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--mute-audio',
      '--disable-audio-output',
      '--disable-dev-shm-usage'
    ]
  });
  const context = await browser.newContext();
  const page = await context.newPage();

  // Sæt HTML-indholdet – her skal du sørge for, at dine styles (inkl. Tailwind) er inlined eller tilgængelige
  await page.setContent(html, { waitUntil: 'networkidle' });

  // Generer PDF'en med header og footer
  await page.pdf({
    path: pdfPath,
    format: pdfFormat,
    landscape: landscape,
    printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: `<div></div>`,
    footerTemplate: footerTemplate,
    margin: margin
  });

  await browser.close();
  process.exit(0);
})();
