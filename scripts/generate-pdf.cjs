const { execSync } = require('child_process');

let puppeteer;
try {
  puppeteer = require('puppeteer');
  console.log("Puppeteer er allerede installeret.");
} catch (err) {
  console.log("Puppeteer er ikke installeret. Installerer nu...");
  try {
    // Installer puppeteer uden at gemme det i package.json
    execSync('npm install puppeteer --no-save', { cwd: __dirname, stdio: 'inherit' });
    // Prøv at kræve puppeteer igen
    puppeteer = require('puppeteer');
    console.log("Puppeteer er installeret.");
  } catch (installErr) {
    console.error("Kunne ikke installere Puppeteer automatisk:", installErr);
    process.exit(1);
  }
}

(async () => {
    const args = process.argv.slice(2);
    let htmlPath = args[0];
    let pdfPath = args[1];
    let footerTemplatePath = args[2];

    if (!htmlPath || !pdfPath) {
        console.error("Usage: node generate-pdf.cjs <htmlPath> <pdfPath> [footerTemplate]");
        process.exit(1);
    }

    // Læs HTML og footer direkte fra fil
    const fs = require("fs");
    const html = fs.readFileSync(htmlPath, "utf8");
    
    let footerTemplate = '';
    if (footerTemplatePath && fs.existsSync(footerTemplatePath)) {
        footerTemplate = fs.readFileSync(footerTemplatePath, "utf8");
    }
    if (!footerTemplate) {
        footerTemplate = `
            <div style="font-size:10px; width:100%; text-align:center; border-top:1px solid #ddd; padding-top:5px;">
                Side <span class="pageNumber"></span> af <span class="totalPages"></span>
            </div>
        `;
    }

    // Start Puppeteer med optimerede argumenter
    const browser = await puppeteer.launch({
        headless: "new",
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();
    
    // Sæt HTML direkte (uden at skrive til disk)
    await page.setContent(html, { waitUntil: "networkidle0" });
    
    // Generer PDF direkte
    await page.pdf({
         path: pdfPath,
         format: "A4",
         printBackground: true,
         displayHeaderFooter: true,
         headerTemplate: `<div></div>`,
         footerTemplate: footerTemplate,
         margin: {
             top: "10mm",
             bottom: "20mm",
             left: "10mm",
             right: "10mm"
         }
    });

    await browser.close();
})();
