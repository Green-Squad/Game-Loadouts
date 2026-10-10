// Optional real browser smoke tests. npm install --no-save playwright; npx playwright install chromium
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base=process.env.BASE_URL || 'http://127.0.0.1:8080';
assert(['localhost','127.0.0.1'].includes(new URL(base).hostname),'Use a loopback isolated fixture service');
const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_EXECUTABLE?{executablePath:process.env.CHROMIUM_EXECUTABLE}:{}),args:['--disable-dev-shm-usage',...(process.getuid?.()===0?['--no-sandbox']:[])]});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
const errors=[],failures=[];
page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400)failures.push([r.status(),r.url()]);});
const out=process.env.SCREENSHOT_DIR || '/tmp/game-loadouts-browser';await fs.mkdir(out,{recursive:true});
for(const [name,path] of [['home','/'],['games','/games'],['game','/Call+of+Duty+Modern+Warfare'],['weapon','/Call+of+Duty+Modern+Warfare/M4A1'],['loadout','/Call+of+Duty+Modern+Warfare/M4A1/1'],['stats','/stats']]){
 await page.goto(base+path,{waitUntil:'networkidle'});await page.screenshot({path:`${out}/${name}.png`,fullPage:true});
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`${name} horizontal overflow`);
 if(name==='home'){
  await page.locator('#autocomplete-ajax').clear();await page.locator('#autocomplete-ajax').pressSequentially('M4',{delay:50});
  await page.locator('.autocomplete-suggestion').first().waitFor({state:'visible'});
  assert((await page.locator('.autocomplete-suggestion').allTextContents()).includes('M4A1 / Call of Duty Modern Warfare'));
  // Existing collapse UI should expand/collapse; no fake voting handler.
  await page.locator('.panel-title a').nth(1).click();await page.waitForTimeout(400);
  assert.equal(await page.locator('#collapse2').evaluate(el=>el.classList.contains('in')),true);
 }
 if(name==='game'){await page.locator('#weaponSearch').fill('Empty');assert.equal(await page.locator('.searchable:not(.hide)').count(),1);}
 if(name==='weapon'){assert.equal(await page.locator('.loadout').count(),7);assert.equal(await page.locator('.loadoutButtons button:disabled').count(),7);}
}
await page.setViewportSize({width:390,height:844});await page.goto(base+'/');
await page.screenshot({path:`${out}/mobile-home.png`,fullPage:true});
assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'mobile horizontal overflow');
await page.locator('.navbar-toggle').click();assert.equal(await page.locator('.navbar-ex1-collapse').isVisible(),true);
assert.deepEqual(errors,[]);assert.deepEqual(failures,[]);await browser.close();console.log('PASS: browser pages, assets, autocomplete, accordion, filter, mobile navigation and disabled voting');
