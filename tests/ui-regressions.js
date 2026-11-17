async (page) => {
  const check = (ok, label) => { if (!ok) throw new Error(label); };
  let owner = '', token = '', generation = 0, pending = false;
  let unblock;
  const acquisition = new Promise(resolve => { unblock = resolve; });
  const actions = [];
  const handleLease = async route => {
    const data = new URLSearchParams(route.request().postData());
    const actor = data.get('nonce'), action = data.get('action');
    actions.push(action);
    if (action === 'acquire' && generation === 0) await acquisition;
    if (action === 'acquire' || action === 'takeover') { owner = actor; token = 'token-' + (++generation); }
    if (action === 'release') { owner = ''; token = ''; }
    const mine = owner === actor && (data.get('token') === token || action === 'acquire' || action === 'takeover');
    const r = {ok:true, owner:owner === 'A' ? 'Ana Pérez' : owner ? 'Luis García' : '', mine, generation,
      available:!owner, changed:false, mode:'strict', can_edit:true, can_takeover:true,
      started:1794837600, heartbeat:1794837600, requester_name:pending ? 'Luis García' : '',
      request_key:pending && mine ? 'request-key' : '', requested_by_me:false, csrf:'test-csrf'};
    if (action === 'acquire' || action === 'takeover') r.token = token;
    await route.fulfill({status:200, contentType:'application/json', body:JSON.stringify(r)});
  };
  await page.context().route('**/lease', handleLease);
  const target = 'http://127.0.0.1:18769/tests/fixtures/rich-editor.html?actor=';
  await page.setViewportSize({width:1280,height:800});
  await page.goto(target + 'A');
  const editorId = await page.evaluate(() => createTask());
  const textarea = page.locator('#' + editorId);
  check(await textarea.isDisabled(), 'Slow initial acquisition did not protect the new task');
  unblock();
  await page.getByRole('status').filter({hasText:'Ana Pérez (tú)'}).waitFor();
  await page.waitForFunction(id => tinymce.get(id).mode.get() === 'design', editorId);
  const editorBody = page.frameLocator('iframe#' + editorId + '_ifr').locator('body');
  await editorBody.fill('Nueva tarea redactada tras cargar 300 tareas');
  check(!await textarea.isDisabled(), 'Owning technician cannot use the delayed task');
  await page.evaluate(async () => { fixture.elapsed += 86400000; await fixture.poll(true); });
  check(await page.getByRole('status').textContent().then(s => s.includes('(tú)')), 'Idle owner lost control');
  const readonlyId = await page.evaluate(() => createTask(true));
  check(await page.evaluate(id => tinymce.get(id).mode.get(), readonlyId) === 'readonly', 'Native readonly editor was enabled');
  await page.evaluate(() => scrollTo(0, document.body.scrollHeight));
  const summary = page.getByRole('button', {name:'Control: tú',exact:true});
  await summary.waitFor();
  await summary.hover();
  await page.getByRole('status').filter({hasText:'Ana Pérez'}).waitFor();
  await page.getByRole('button', {name:'Cerrar actividad del ticket'}).click();
  check(await summary.getAttribute('aria-expanded') === 'false', 'Close did not collapse the panel');
  await summary.press('Enter');
  check(await summary.getAttribute('aria-expanded') === 'true', 'Keyboard cannot open panel');
  await summary.press('Escape');
  check(await summary.getAttribute('aria-expanded') === 'false', 'Escape cannot close panel');
  pending = true;
  await page.evaluate(async () => { fixture.elapsed += 8000; await fixture.poll(true); });
  const notice = page.getByRole('button', {name:'Solicitud de control',exact:true});
  await notice.waitFor(); await notice.click();
  await page.getByRole('button', {name:'Ceder control a Luis García',exact:true}).waitFor();
  await page.screenshot({path:'output/playwright/long-ticket-owner.png'});
  pending = false; owner = 'B'; token = 'other-owner'; generation++;
  await page.evaluate(async () => { fixture.elapsed += 8000; await fixture.poll(true); });
  await page.waitForFunction(id => tinymce.get(id).mode.get() === 'readonly', editorId);
  check(await page.evaluate(id => tinymce.get(id).getContent({format:'text'}), editorId) === 'Nueva tarea redactada tras cargar 300 tareas', 'Takeover changed the draft');
  await page.getByRole('button', {name:'Tomar control',exact:true}).click();
  await page.waitForFunction(id => tinymce.get(id).mode.get() === 'design', editorId);
  await editorBody.fill('Tarea editable tras recuperar control');
  await page.getByRole('button', {name:'Cerrar actividad del ticket'}).click();
  await page.evaluate(() => scrollTo(0,0));
  await page.waitForFunction(() => document.querySelector('.editcontrol-dock').hidden);
  check(await page.getByRole('region', {name:'Actividad del ticket'}).isVisible(), 'Inline bar not restored after scrolling up');
  check(await page.evaluate(() => document.activeElement === document.querySelector('[aria-label="Actividad del ticket"]')), 'Focus was left in a hidden dock');
  const touchContext = await page.context().browser().newContext({hasTouch:true,isMobile:true,viewport:{width:360,height:740}});
  await touchContext.route('**/lease', handleLease);
  const other = await touchContext.newPage();
  await other.goto(target + 'B');
  const otherEditorId = await other.evaluate(() => createTask());
  await other.waitForFunction(id => tinymce.get(id).mode.get() === 'readonly', otherEditorId);
  await other.evaluate(() => scrollTo(0, document.body.scrollHeight));
  await other.getByRole('button', {name:'Bloqueado: Ana Pérez',exact:true}).waitFor();
  await other.setViewportSize({width:360,height:740});
  await other.getByRole('button', {name:'Bloqueado: Ana Pérez',exact:true}).tap();
  check(!await other.evaluate(() => document.documentElement.scrollWidth > innerWidth), 'Compact panel overflows mobile');
  await other.screenshot({path:'output/playwright/long-ticket-mobile.png'});
  await other.getByRole('button', {name:'Tomar control',exact:true}).click();
  await other.waitForFunction(id => tinymce.get(id).mode.get() === 'design', otherEditorId);
  await other.frameLocator('iframe#' + otherEditorId + '_ifr').locator('body').fill('Tarea del compañero');
  await page.context().unroute('**/lease');
  await touchContext.close();
  return {largeTimeline:300,realEditor:'TinyMCE 7.9.2',lateInitialization:true,idleDay:true,nativeReadonlyPreserved:true,hoverKeyboardAndClick:true,inlineRestored:true,draftsPreserved:true,twoTabs:true,mobile:true,actions};
}
