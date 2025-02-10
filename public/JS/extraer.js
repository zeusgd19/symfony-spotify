const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch(
{ headless: true,
  args: ['--no-sandbox'] }); // Cambia a true si no quieres ver la ventana del navegador
    const page = await browser.newPage();

    // Cargar el iframe de Spotify
    await page.goto(`https://open.spotify.com/embed/track/${process.argv[2]}`, { waitUntil: 'networkidle2' });

    // Extraer todos los scripts de tipo application/json
    const jsonData = await page.evaluate(() => {
        const scripts = Array.from(document.querySelectorAll('script[type="application/json"]'));
        return scripts.map(script => script.textContent);
    });

    jsonData.forEach(element => {
        console.log(element.substring(element.indexOf('http'),element.indexOf('hasVideo') - 4));
    })

    await browser.close();
})();
