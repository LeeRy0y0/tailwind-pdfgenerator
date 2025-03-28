const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

(async () => {
    const args = process.argv.slice(2);
    let pdfPath = args[0];
    let footerTemplatePath = args[1];
    let options = {};

    const dir = path.dirname(pdfPath);
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }

    let html = '';
    process.stdin.setEncoding('utf8');
    for await (const chunk of process.stdin) {
        html += chunk;
    }

    if (args[2]) {
        try {
            options = JSON.parse(args[2]);
        } catch (e) {
            console.error("Error parsing options:", e);
        }
    }

    let footerTemplate = '';
    let showFooter = false;
    
    if (footerTemplatePath && fs.existsSync(footerTemplatePath)) {
        footerTemplate = fs.readFileSync(footerTemplatePath, 'utf8').trim();
        if (footerTemplate.length > 0) {
            showFooter = true;
        }
    }
    /*if (!footerTemplate) {
        footerTemplate = `
            <div style="font-size:10px; width:100%; text-align:center; border-top:1px solid #ddd; padding-top:5px;">
                Side <span class="pageNumber"></span> af <span class="totalPages"></span>
            </div>
        `;
    }*/

    const pdfFormat = options.format || "A4";
    const landscape = options.landscape || false;
    const margin = options.margin || {
        top: "10mm",
        bottom: "20mm",
        left: "10mm",
        right: "10mm"
    };

    const browser = await puppeteer.launch({
        headless: "new",
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--mute-audio',
            '--disable-audio-output'
        ]
    });
    const page = await browser.newPage();
    
    await page.setContent(html, { waitUntil: "networkidle0" });
    
    await page.pdf({
         path: pdfPath,
         format: pdfFormat,
         landscape: landscape,
         printBackground: true,
         displayHeaderFooter: showFooter,
         headerTemplate: `<div></div>`,
         footerTemplate: showFooter ? footerTemplate : undefined,
         margin: margin
    });

    await browser.close();
    process.exit(0);
})();