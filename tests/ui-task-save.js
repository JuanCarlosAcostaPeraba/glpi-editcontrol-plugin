async (page) => {
  const check = (ok, label) => { if (!ok) throw new Error(label); };
  let mode='strict', owner='', token='', baseline='current', receiptFrom='', receiptTo='';
  const submitted=[];
  await page.context().route('**/lease', async route => {
    const data=new URLSearchParams(route.request().postData()), action=data.get('action');
    if (action==='acquire') { owner='Ana Pérez'; token='owned-tab'; }
    const mine=owner==='Ana Pérez' && (data.get('token')===token || action==='acquire');
    const accepted=mine && data.get('baseline')===receiptFrom && receiptTo===baseline ? baseline : data.get('baseline');
    const r={ok:true,mode,owner,mine,available:!owner,can_edit:true,can_takeover:true,generation:1,
      baseline:accepted,changed:accepted!==baseline,started:1794993600,heartbeat:1794993600,
      requester_name:'',csrf:'test-csrf'};
    if (action==='acquire') r.token=token;
    await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(r)});
  });
  await page.context().route('**/front/tickettask.form.php', async route => {
    const data=new URLSearchParams(route.request().postData());
    const valid=data.get('_editcontrol_token')===token && (data.get('_editcontrol_baseline')===baseline
      || data.get('_editcontrol_baseline')===receiptFrom && receiptTo===baseline);
    if (mode==='strict' && !valid) throw new Error('Own task save was rejected');
    submitted.push(data.get('_editcontrol_baseline'));
    receiptFrom=valid ? data.get('_editcontrol_baseline') : '';
    baseline='saved-'+submitted.length; receiptTo=valid ? baseline : '';
    await route.fulfill({status:200,contentType:'application/json',body:'{"ok":true}'});
  });
  const target='http://127.0.0.1:18769/tests/fixtures/rich-editor.html';
  await page.setViewportSize({width:1280,height:800});
  await page.goto(target);
  await page.getByRole('status').filter({hasText:'(tú)'}).waitFor();
  await page.evaluate(() => {
    fixture.saves=0;
    document.addEventListener('submit', async event => {
      event.preventDefault();
      tinymce.get().forEach(editor => editor.save());
      const res=await fetch(event.target.action,{method:'POST',body:new URLSearchParams(new FormData(event.target))});
      if (!res.ok) throw new Error('Task save failed');
      fixture.saves++;
    });
  });
  const first=await page.evaluate(() => createTask());
  await page.frameLocator('iframe#'+first+'_ifr').locator('body').fill('Primera tarea sin recargar');
  await page.locator('#'+first).locator('..').getByRole('button',{name:'Guardar tarea',exact:true}).click();
  await page.waitForFunction(() => fixture.saves===1);
  await page.evaluate(() => fixture.poll(true));
  check(await page.locator('input[name="_editcontrol_baseline"]').first().inputValue()==='saved-1','Own baseline was not updated');
  check(await page.evaluate(id=>tinymce.get(id).mode.get(),first)==='design','Own save locked the task editor');
  const second=await page.evaluate(() => createTask());
  await page.frameLocator('iframe#'+second+'_ifr').locator('body').fill('Segunda tarea en la misma página');
  await page.locator('#'+second).locator('..').getByRole('button',{name:'Guardar tarea',exact:true}).click();
  await page.waitForFunction(() => fixture.saves===2);
  await page.frameLocator('iframe#'+second+'_ifr').locator('body').fill('Otro guardado antes del sondeo');
  await page.locator('#'+second).locator('..').getByRole('button',{name:'Guardar tarea',exact:true}).click();
  await page.waitForFunction(() => fixture.saves===3);
  await page.evaluate(() => fixture.poll(true));
  check(submitted.join(',')==='current,saved-1,saved-1','Unexpected version chain');
  check(await page.locator('input[name="_editcontrol_baseline"]').first().inputValue()==='saved-3','Repeated own saves did not advance baseline');
  baseline='external-change';
  await page.evaluate(() => fixture.poll(true));
  check(await page.evaluate(id=>tinymce.get(id).mode.get(),second)==='readonly','External change was incorrectly acknowledged');
  check(await page.evaluate(id=>tinymce.get(id).getContent({format:'text'}),second)==='Otro guardado antes del sondeo','External conflict discarded draft');
  mode='warning'; owner='Otro técnico'; token='other-tab'; baseline='current'; receiptFrom=''; receiptTo='';
  await page.goto(target);
  await page.getByRole('status').filter({hasText:'Otro técnico'}).waitFor();
  const third=await page.evaluate(() => createTask());
  check(!await page.locator('#'+third).isDisabled(),'Warning mode disabled TinyMCE source');
  check(await page.evaluate(id=>tinymce.get(id).mode.get(),third)==='design','Warning mode blocked editor');
  await page.frameLocator('iframe#'+third+'_ifr').locator('body').fill('Modo aviso permite escribir');
  const nativeReadonly=await page.evaluate(() => createTask(true));
  check(await page.evaluate(id=>tinymce.get(id).mode.get(),nativeReadonly)==='readonly','Native readonly permissions were overwritten');
  const bar=page.getByRole('region',{name:'Actividad del ticket'});
  check(await bar.evaluate(el=>getComputedStyle(el).backgroundColor)==='rgb(192, 38, 166)','Warning-mode fuchsia is missing');
  await page.evaluate(() => scrollTo(0,document.body.scrollHeight));
  await page.setViewportSize({width:360,height:740});
  const pill=page.getByRole('button',{name:'En uso: Otro técnico',exact:true});
  await pill.waitFor();
  check(await pill.evaluate(el=>getComputedStyle(el).backgroundColor)==='rgb(192, 38, 166)','Floating warning is not fuchsia');
  await pill.click();
  check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Warning panel overflows mobile');
  await page.screenshot({path:'output/playwright/warning-fuchsia-editable.png'});
  await page.context().unroute('**/lease'); await page.context().unroute('**/front/tickettask.form.php');
  return {ownAjaxSaves:3,newTaskAfterSave:true,baselineAcknowledged:true,externalChangesBlocked:true,
    warningEditable:true,nativeReadonlyPreserved:true,warningFuchsia:true,floatingFuchsia:true};
}
