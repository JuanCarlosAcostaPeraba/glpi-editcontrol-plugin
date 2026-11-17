async (page) => {
  const outcomes = [];
  for (const scenario of ['owner','auto','reader','race','html','recover']) {
    await page.goto('http://127.0.0.1:18769/tests/fixtures/bar.html?scenario=' + scenario);
    await page.waitForFunction(() => !document.querySelector('[role=status]').textContent.startsWith('Comprobando'));
    if (scenario === 'owner') {
      await page.getByRole('button', {name:'Solicitar control', exact:true}).click();
      await page.getByRole('button', {name:'Solicitud enviada', exact:true}).waitFor();
    }
    if (scenario === 'auto' || scenario === 'recover') {
      await page.evaluate(async () => { fixture.elapsed+=41000; await fixture.interval(); });
      if (scenario === 'auto') await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pagehide')));
    }
    const result = await page.evaluate(() => ({
      text:document.querySelector('[role=status]').textContent,
      buttons:[...document.querySelector('[aria-label="Actividad del ticket"]').querySelectorAll('button')].filter(b=>!b.hidden&&b.getClientRects().length).map(b=>b.textContent),
      actions:fixture.calls.map(c=>c.action),
      secure:fixture.calls.every(c=>c.headers['X-Glpi-Csrf-Token']==='test-csrf'),
      release:fixture.calls.some(c=>c.action==='release'&&c.keepalive),
      overflow:document.documentElement.scrollWidth>innerWidth
    }));
    const check = (condition) => { if (!condition) throw new Error(scenario + ': ' + JSON.stringify(result)); };
    check(result.secure && !result.overflow);
    check(!result.buttons.some(b=>['Editar','Terminar edición','Recargar ticket','Tomar control'].includes(b)));
    if (scenario==='owner') check(result.buttons[0]==='Solicitud enviada' && result.actions.includes('request'));
    if (scenario==='auto') check(result.text.includes('(tú)') && result.actions.includes('heartbeat') && result.release && !result.buttons.length);
    if (scenario==='reader') check(!result.actions.includes('acquire') && !result.buttons.length);
    if (scenario==='race') check(result.text.includes('Ana Pérez') && result.buttons[0]==='Solicitar control');
    if (scenario==='html') check(!result.text.includes('JSON') && !result.text.includes('<div') && result.text.includes('sesión'));
    if (scenario==='recover') check(result.text.includes('(tú)') && result.actions.includes('acquire'));
    outcomes.push({scenario, ...result});
  }
  return outcomes;
}
