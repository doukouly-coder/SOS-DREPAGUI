// Ouvre une session d'administration sur le WordPress local et renvoie { nav, page }.
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
export const WP = process.env.WP_LOCAL || 'http://127.0.0.1:8088';
export async function session(viewport = { width: 1440, height: 900 }) {
  const nav = await chromium.launch();
  const ctx = await nav.newContext({ viewport });
  const page = await ctx.newPage();
  await page.goto(WP + '/wp-login.php');
  await page.fill('#user_login', process.env.WP_USER || 'admin');
  await page.fill('#user_pass', process.env.WP_PASS || 'admin-local-mdp');
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
  return { nav, ctx, page };
}
export async function editeur(page, url) {
  await page.goto(WP + url);
  await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor') && wp.blocks.getBlockTypes().length > 50, null, { timeout: 90000 });
  await page.waitForTimeout(1500);
}
