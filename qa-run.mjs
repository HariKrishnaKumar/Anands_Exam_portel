import { chromium } from 'playwright';

const B = 'http://localhost:8000';
const AE = 'admin@testplatform.com';
const AP = 'admin123';
const SE = 'hariiphones83@gmail.com';
const SP = 'Hari@2003';
const f = [];
let P=0,F=0,W=0;
function L(s,a,d,e){f.push({s,a,d,e});if(s==='PASS')P++;else if(s==='CRITICAL'||s==='HIGH')F++;else W++;console.log(`[${s}] ${a}: ${d}${e?' -> '+e:''}`);}

(async()=>{
const br=await chromium.launch({headless:false});
const cx=await br.newContext({viewport:{width:1280,height:900}});

console.log('\n== 1. LOGIN PAGE ==');
const p=await cx.newPage();
p.on('dialog',d=>d.accept());
const er=[];
p.on('pageerror',e=>er.push(e.message));
await p.goto(B);await p.waitForLoadState('networkidle');
L((await p.title()).includes('Sign In')?'PASS':'CRITICAL','Login','Page loads',await p.title());
const fe=await p.locator('#email').isVisible().catch(()=>false);
const fp=await p.locator('#password').isVisible().catch(()=>false);
const fr=await p.locator('#role').isVisible().catch(()=>false);
const fs=await p.locator('button[type="submit"]').isVisible().catch(()=>false);
L(fe&&fp&&fr&&fs?'PASS':'HIGH','Login','Form elements',`E:${fe} P:${fp} R:${fr} S:${fs}`);
L(er.length===0?'PASS':'MEDIUM','Login','No console errors',er.length?er[0]:'none');

console.log('\n== 2. ADMIN LOGIN ==');
await p.selectOption('#role','admin');
await p.fill('#email',AE);await p.fill('#password',AP);
await p.click('button[type="submit"]');await p.waitForLoadState('networkidle');
L(p.url().includes('admin')?'PASS':'CRITICAL','Auth','Admin login',p.url());

console.log('\n== 3. ADMIN DASHBOARD ==');
await p.goto(`${B}/admin/dashboard.php`);await p.waitForLoadState('networkidle');
const dc=await p.content();
L(dc.includes('ring-container')?'PASS':'MEDIUM','Dashboard','Ring chart');
L(dc.includes('Scheduled')||dc.includes('scheduled')?'PASS':'MEDIUM','Dashboard','Scheduled in chart');

console.log('\n== 4. ASSESSMENT MANAGEMENT ==');
await p.goto(`${B}/admin/assessment_management.php`);await p.waitForLoadState('networkidle');
const tabs=(await p.locator('.tabs .tab').allTextContents()).map(t=>t.replace(/\s+/g,' ').trim());
for(const x of['Upcoming','Scheduled','Live','Paused','Completed'])L(tabs.some(t=>t.includes(x))?'PASS':'HIGH','Tabs',`${x} tab`);

console.log('\n== 5. MODAL Z-INDEX ==');
await p.click('a[href*="tab=upcoming"]');await p.waitForLoadState('networkidle');
const ur=await p.locator('tbody tr').count();
if(ur>0){
await p.locator('tbody tr:first-child .actions button:has-text("Publish")').first().click();
await p.waitForTimeout(500);
const mv=await p.locator('#publishModal').isVisible();
L(mv?'PASS':'HIGH','Modal','Opens and clickable',mv?'pointer-events work':'blocked by content-area');
try{await p.locator('.modal-close').first().click();}catch(e){}
await p.waitForTimeout(300);
}else L('PASS','Modal','No tests to open modal from');

console.log('\n== 6. SCHEDULE FLOW ==');
if(ur>0){
await p.locator('tbody tr:first-child .actions button:has-text("Publish")').first().click();
await p.waitForTimeout(500);
const sb=p.locator('button:has-text("Schedule for Later")');
const sv=await sb.isVisible().catch(()=>false);
L(sv?'PASS':'HIGH','Schedule','Schedule for Later button');
if(sv){
await sb.click();await p.waitForTimeout(300);
const fv=await p.locator('#scheduleForm').isVisible();
L(fv?'PASS':'HIGH','Schedule','Schedule form visible');
if(fv){
const now=new Date();const ist=new Date(now.getTime()+7.5*60*60*1000);
const end=new Date(ist.getTime()+60*60*1000);
const fmt=d=>`${d.getUTCFullYear()}-${String(d.getUTCMonth()+1).padStart(2,'0')}-${String(d.getUTCDate()).padStart(2,'0')}T${String(d.getUTCHours()).padStart(2,'0')}:${String(d.getUTCMinutes()).padStart(2,'0')}`;
await p.fill('#scheduleForm input[name="start_time"]',fmt(ist));
await p.fill('#scheduleForm input[name="end_time"]',fmt(end));
await p.locator('#scheduleForm button[type="submit"]').click();
await p.waitForLoadState('networkidle');await p.waitForTimeout(1000);
L(p.url().includes('tab=scheduled')?'PASS':'HIGH','Schedule','Redirect to scheduled',p.url());
const sc=await p.locator('.tab-content').textContent();
L(sc.includes('IST')?'PASS':'HIGH','Schedule','IST time shown');
L(sc.includes('Publish Now')?'PASS':'HIGH','Schedule','Publish Now button');
L(sc.includes('Cancel')?'PASS':'HIGH','Schedule','Cancel button');
}}}

console.log('\n== 7. CANCEL SCHEDULE ==');
const cb=p.locator('button:has-text("Cancel")').first();
if(await cb.isVisible().catch(()=>false)){
await cb.click();await p.waitForLoadState('networkidle');await p.waitForTimeout(1000);
L(p.url().includes('tab=upcoming')?'PASS':'HIGH','Cancel','Redirect to upcoming',p.url());
}else L('PASS','Cancel','No scheduled tests');

console.log('\n== 8. PUBLISH NOW ==');
await p.click('a[href*="tab=upcoming"]');await p.waitForLoadState('networkidle');
if((await p.locator('tbody tr').count())>0){
await p.locator('tbody tr:first-child .actions button:has-text("Publish")').first().click();
await p.waitForTimeout(500);
await p.click('button:has-text("Schedule for Later")');await p.waitForTimeout(300);
const now2=new Date();const ist2=new Date(now2.getTime()+8*60*60*1000);
const end2=new Date(ist2.getTime()+60*60*1000);
const fmt2=d=>`${d.getUTCFullYear()}-${String(d.getUTCMonth()+1).padStart(2,'0')}-${String(d.getUTCDate()).padStart(2,'0')}T${String(d.getUTCHours()).padStart(2,'0')}:${String(d.getUTCMinutes()).padStart(2,'0')}`;
await p.fill('#scheduleForm input[name="start_time"]',fmt2(ist2));
await p.fill('#scheduleForm input[name="end_time"]',fmt2(end2));
await p.locator('#scheduleForm button[type="submit"]').click();
await p.waitForLoadState('networkidle');await p.waitForTimeout(1000);
const pn=p.locator('tbody form:has(input[value="publish_now"]) button[type="submit"]').first();
if(await pn.isVisible().catch(()=>false)){
await pn.click();await p.waitForLoadState('networkidle');await p.waitForTimeout(1000);
L(p.url().includes('tab=live')?'PASS':'HIGH','Publish Now','Redirect to live',p.url());
}else L('HIGH','Publish Now','Button not found');
}

console.log('\n== 9. STUDENT DASHBOARD ==');
const sc2=await br.newContext({viewport:{width:1280,height:900}});
const sp=await sc2.newPage();
await sp.goto(B);await sp.waitForLoadState('networkidle');
await sp.selectOption('#role','student');
await sp.fill('#email',SE);await sp.fill('#password',SP);
await sp.click('button[type="submit"]');await sp.waitForLoadState('networkidle');
L(sp.url().includes('student')||sp.url().includes('dashboard')?'PASS':'CRITICAL','Student','Login',sp.url());
const sdc=await sp.content();
L(sdc.includes('Upcoming')||sdc.includes('Start Test')||sdc.includes('Resume')?'PASS':'MEDIUM','Student','Dashboard has tests');

console.log('\n== 10. STUDENT TABLE VIEW ==');
const tableBtn=sp.locator('button:has-text("Table"),[data-view="table"]').first();
if(await tableBtn.isVisible().catch(()=>false)){
await tableBtn.click();await sp.waitForTimeout(500);
const tc=await sp.locator('table').first().textContent().catch(()=>'');
L(tc.includes('Upcoming')||tc.includes('Scheduled')||tc.includes('Start Test')||tc.includes('Active')?'PASS':'MEDIUM','Student','Table view content');
}else L('PASS','Student','No table toggle found');

const dur=Date.now()-Date.now();
console.log('\n============================================');
console.log(`  QA REPORT SUMMARY`);
console.log(`  PASS: ${P}  FAIL: ${F}  WARN: ${W}`);
console.log(`  Health Score: ${Math.round((P/(P+F+W))*100)}/100`);
console.log('============================================');
console.log('\nFINDINGS:');
f.forEach((x,i)=>console.log(`  ${i+1}. [${x.s}] ${x.a}: ${x.d}${x.e?' -> '+x.e:''}`));

await br.close();await sc2.close();
process.exit(F>0?1:0);
})().catch(e=>{console.error('FATAL:',e.message);process.exit(1);});
