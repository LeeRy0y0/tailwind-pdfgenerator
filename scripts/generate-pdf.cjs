const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    const args = process.argv.slice(2);
    let pdfPath = args[0];
    let footerTemplatePath = args[1];
    let options = {};

    // If a third argument is passed, parse it as JSON
    if (args[2]) {
        try {
            options = JSON.parse(args[2]);
        } catch (e) {
            console.error("Error parsing options:", e);
        }
    }

    if (!pdfPath) {
        console.error("Usage: node generate-pdf.cjs <pdfPath> [footerTemplatePath] [options]");
        process.exit(1);
    }

    let html = '';
    process.stdin.setEncoding('utf8');
    for await (const chunk of process.stdin) {
        html += chunk;
    }

    let footerTemplate = '';
    if (footerTemplatePath && fs.existsSync(footerTemplatePath)) {
        footerTemplate = fs.readFileSync(footerTemplatePath, 'utf8');
    }
    /*if (!footerTemplate) {
        footerTemplate = `
            <div style="font-size:10px; width:100%; text-align:center; border-top:1px solid #ddd; padding-top:5px;">
                Side <span class="pageNumber"></span> af <span class="totalPages"></span>
            </div>
        `;
    }*/

    // Use provided options or fallback defaults
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
         displayHeaderFooter: true,
         headerTemplate: `<div></div>`,
         footerTemplate: footerTemplate,
         margin: margin
    });

    await browser.close();
    process.exit(0);
})();