async (page) => {
  let owner = '', token = '', serial = 0;
  const actions = [];
  await page.context().route('**/lease', async route => {
    const data = new URLSearchParams(route.request().postData());
    const action = data.get('action'); actions.push(action);
    if (action === 'acquire') { owner = 'Ana Pérez'; token = 'token-' + (++serial); }
    const r = {ok:true, owner, mine:!!owner && (data.get('token') === token || action === 'acquire'),
      available:!owner, changed:false, mode:'strict', can_edit:true, can_takeover:false,
      generation:serial, started:1794837600, heartbeat:1794837600, requester_name:'', csrf:'test-csrf'};
    if (action === 'acquire') r.token = token;
    await route.fulfill({status:200, contentType:'application/json', body:JSON.stringify(r)});
  });
  await page.goto('http://127.0.0.1:18769/tests/fixtures/bar.html?scenario=linked&actor=A');
  await page.getByRole('status').filter({hasText:'(tú)'}).waitFor();
  await page.locator('textarea').fill('Borrador conservado');
  owner = ''; token = '';
  await page.evaluate(async () => { fixture.elapsed += 41000; await fixture.interval(true); });
  await page.getByRole('button', {name:'Recuperar control', exact:true}).click();
  await page.getByRole('status').filter({hasText:'(tú)'}).waitFor();
  if (await page.locator('textarea').isDisabled() || await page.locator('textarea').inputValue() !== 'Borrador conservado') throw new Error('Free ticket recovery failed');
  if (actions.includes('takeover')) throw new Error('Recovery incorrectly requires takeover permission');
  await page.context().unroute('**/lease');
  return {freeTicketRecoverableWithoutTakeoverPermission:true,draftPreserved:true};
}
