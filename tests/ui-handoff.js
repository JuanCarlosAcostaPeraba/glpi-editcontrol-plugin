async (page) => {
  let owner='', leaseToken='', pending=null, serial=0;
  const calls=[];
  await page.context().route('**/lease',async route=>{
    const body=new URLSearchParams(route.request().postData());
    const action=body.get('action'), actor=body.get('nonce');
    calls.push({actor,action});
    if(route.request().headers()['x-glpi-csrf-token']!=='test-csrf') throw new Error('CSRF header missing');
    if(action==='acquire') { owner=actor;leaseToken='lease-'+(++serial); }
    if(action==='request') pending={actor,token:body.get('request_token'),key:'request-'+(++serial)};
    if(action==='handoff') {
      if(actor!==owner||body.get('token')!==leaseToken||body.get('request_key')!==pending.key) throw new Error('Invalid handoff');
      owner=pending.actor;leaseToken=pending.token;pending=null;
    }
    const mine=actor===owner&&body.get('token')===leaseToken;
    const response={ok:true,owner:owner==='A'?'Ana Pérez':owner==='B'?'Luis García':'',mine:action==='acquire'||mine,
      available:!owner,changed:false,started:1794837600,heartbeat:1794837600,same_user:false,
      requester_name:pending?'Luis García':'',request_key:mine&&pending?pending.key:'',requested_by_me:pending?.actor===actor,csrf:'test-csrf'};
    if(action==='acquire')response.token=leaseToken;
    await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(response)});
  });
  const target='http://127.0.0.1:18769/tests/fixtures/bar.html?scenario=linked&actor=';
  await page.goto(target+'A');
  await page.getByRole('status').filter({hasText:'(tú)'}).waitFor();
  await page.locator('textarea').fill('Borrador de Ana sin guardar');
  const other=await page.context().newPage();
  await other.goto(target+'B');
  await other.getByRole('button',{name:'Solicitar control',exact:true}).click();
  await other.getByRole('button',{name:'Solicitud enviada',exact:true}).waitFor();
  await other.locator('textarea').fill('Borrador de Luis sin guardar');
  await page.bringToFront();
  await page.evaluate(async()=>{fixture.elapsed+=8000;await fixture.interval();});
  await page.getByRole('alert').filter({hasText:'Luis García solicita'}).waitFor();
  await page.getByRole('button',{name:'Ceder control a Luis García',exact:true}).waitFor();
  await page.setViewportSize({width:360,height:740});
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)) throw new Error('Mobile overflow');
  await page.getByRole('region',{name:'Actividad del ticket'}).screenshot({path:'output/playwright/handoff-notice.png'});
  await page.getByRole('button',{name:'Ceder control a Luis García',exact:true}).click();
  await page.getByRole('status').filter({hasText:'Has cedido el control'}).waitFor();
  await other.bringToFront();
  await other.evaluate(async()=>{fixture.elapsed+=8000;await fixture.interval();});
  await other.getByRole('status').filter({hasText:'Luis García (tú)'}).waitFor();
  if(page.url()!==target+'A'||other.url()!==target+'B') throw new Error('Unexpected navigation');
  if(await page.locator('textarea').inputValue()!=='Borrador de Ana sin guardar'||await other.locator('textarea').inputValue()!=='Borrador de Luis sin guardar') throw new Error('Draft was changed');
  if(!await page.getByRole('button',{name:'Guardar',exact:true}).isDisabled()) throw new Error('Former owner can submit');
  if(await other.getByRole('button',{name:'Guardar',exact:true}).isDisabled()) throw new Error('Recipient cannot submit');
  const incoming=await other.locator('input[name="_editcontrol_token"]').inputValue();
  if(incoming!==leaseToken||calls.filter(c=>c.action==='handoff').length!==1) throw new Error('Incorrect credential transfer');
  await page.context().unroute('**/lease');
  await other.close();
  return {notice:true,transferred:true,noNavigation:true,draftsPreserved:true,formerOwnerBlocked:true,recipientCanSubmit:true,pollSeconds:8};
}
