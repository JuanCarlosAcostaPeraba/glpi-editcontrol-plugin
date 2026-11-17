async (page) => {
  const root='http://127.0.0.1:18769/tests/fixtures/bar.html?scenario=';
  const check=(value,label)=>{if(!value)throw new Error(label);};
  const luminance=rgb=>{const values=rgb.match(/[\d.]+/g).slice(0,3).map(Number).map(x=>{x/=255;return x<=.04045?x/12.92:((x+.055)/1.055)**2.4;});return .2126*values[0]+.7152*values[1]+.0722*values[2];};
  const results=[];
  for(const scenario of ['strict','takeover-denied','takeover-race','mode-switch']) {
    await page.goto(root+scenario);
    await page.getByRole('status').filter({hasText:'Ticket bloqueado'}).waitFor();
    check(await page.locator('textarea').isDisabled(),'Non-owner can edit textarea');
    check(await page.getByRole('button',{name:'Guardar',exact:true}).isDisabled(),'Non-owner can submit');
    const colors=await page.getByRole('region',{name:'Actividad del ticket'}).evaluate(el=>({bg:getComputedStyle(el).backgroundColor,fg:getComputedStyle(el).color}));
    check(colors.bg==='rgb(192, 38, 166)','Fuchsia state missing');
    const levels=[luminance(colors.bg),luminance(colors.fg)].sort((a,b)=>b-a);
    const contrast=(levels[0]+.05)/(levels[1]+.05);
    check(contrast>=4.5,'Insufficient fuchsia contrast');
    if(scenario==='takeover-denied') {
      check(await page.getByRole('button',{name:'Tomar control',exact:true}).count()===0,'Unauthorized takeover visible');
    } else {
      await page.setViewportSize({width:360,height:740});
      check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Fuchsia controls overflow');
      if(scenario==='strict') await page.getByRole('region',{name:'Actividad del ticket'}).screenshot({path:'output/playwright/blocked-fuchsia.png'});
      await page.getByRole('button',{name:'Tomar control',exact:true}).click();
      if(scenario==='takeover-race') {
        await page.getByRole('status').filter({hasText:'ha cambiado'}).waitFor();
        check(await page.locator('textarea').isDisabled(),'Failed takeover enabled editing');
        check(await page.evaluate(()=>fixture.calls.filter(c=>c.action==='takeover').length)===1,'Takeover retried without user action');
      } else {
        await page.getByRole('status').filter({hasText:'Luis García (tú)'}).waitFor();
        check(!await page.locator('textarea').isDisabled(),'New owner remains readonly');
        check(await page.evaluate(()=>fixture.editorMode)==='design','Rich editor remains readonly');
      }
    }
    results.push({scenario,contrast,passed:true});
  }
  let owner='',token='',generation=0;
  const calls=[];
  await page.context().route('**/lease',async route=>{
    const data=new URLSearchParams(route.request().postData()); const actor=data.get('nonce'), action=data.get('action');
    calls.push(action);
    if(action==='acquire'||action==='takeover') {
      if(action==='takeover')check(Number(data.get('expected_generation'))===generation,'Wrong takeover generation');
      owner=actor;token='credential-'+(++generation);
    }
    const r={ok:true,owner:owner==='A'?'Ana Pérez':owner==='B'?'Luis García':'',mine:owner===actor&&(data.get('token')===token||action==='acquire'||action==='takeover'),generation,
      mode:'strict',can_edit:true,can_takeover:true,available:!owner,changed:false,started:1794837600,heartbeat:1794837600,requester_name:'',request_key:'',requested_by_me:false,csrf:'test-csrf'};
    if(action==='acquire'||action==='takeover')r.token=token;
    await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(r)});
  });
  await page.context().route('**/context-check',async route=>{
    const headers=route.request().headers();
    check(headers['x-editcontrol-token']===token&&headers['x-editcontrol-baseline']==='current'&&headers['x-editcontrol-ticket']==='1','Fetch update lacks credentials');
    await route.fulfill({status:200,body:'OK'});
  });
  const target=root+'linked-takeover&actor=';
  await page.goto(target+'A');await page.getByRole('status').filter({hasText:'Ana Pérez (tú)'}).waitFor();
  await page.locator('textarea').fill('Borrador del propietario anterior');
  const other=await page.context().newPage();await other.goto(target+'B');
  await other.getByRole('button',{name:'Tomar control',exact:true}).click();
  await other.getByRole('status').filter({hasText:'Luis García (tú)'}).waitFor();
  await other.evaluate(()=>fetch('/context-check',{method:'POST',body:'fixture'}));
  await page.bringToFront();await page.evaluate(async()=>{fixture.elapsed+=41000;await fixture.interval();});
  await page.getByRole('status').filter({hasText:'Ya no tienes el control'}).waitFor();
  check(await page.locator('textarea').isDisabled(),'Previous owner remains editable');
  check(await page.locator('textarea').inputValue()==='Borrador del propietario anterior','Takeover lost a draft');
  check(!calls.includes('request'),'Takeover required a request');
  check(page.url()===target+'A'&&other.url()===target+'B','Takeover navigated the page');
  await page.context().unroute('**/lease');await page.context().unroute('**/context-check');await other.close();
  results.push({scenario:'two-tabs-takeover',noRequest:true,draftPreserved:true,formerOwnerReadonly:true,fetchCredentials:true,passed:true});
  return results;
}
