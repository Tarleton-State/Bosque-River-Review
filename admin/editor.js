/* This browser holds only an expiring CMS session, never a GitHub credential. */
(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  let config, token = '', current, sha = null, identifier = '', user = '', dirty = false, busy = false;
  const inputs = new Map();
  function status(message, error = false) { $('status').textContent = message; $('status').classList.toggle('error', error); }
  function lock(value) { busy = value; for (const id of ['save', 'logout', 'issue-picker', 'new-issue']) $(id).disabled = value; for (const input of $('editor').querySelectorAll('input, textarea, button')) input.disabled = value; }
  async function api(action, data, method = data ? 'POST' : 'GET') {
    const url = new URL(config.apiBase.replace(/\/$/, '') + '/api.php');
    url.searchParams.set('site', config.siteId); url.searchParams.set('action', action);
    if (method === 'GET' && data) for (const [key, value] of Object.entries(data)) url.searchParams.set(key, value);
    const headers = {Accept: 'application/json'};
    if (token) headers.Authorization = 'Bearer ' + token;
    const options = {method, headers, credentials: 'omit', cache: 'no-store'};
    if (method !== 'GET' && data) { headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(data); }
    const response = await fetch(url, options);
    let result;
    try { result = await response.json(); } catch { throw new Error('The editing service did not return JSON. Contact Web Operations.'); }
    if (!response.ok) {
      if (response.status === 401) { token = ''; $('login-panel').hidden = false; }
      throw new Error(result.error || 'The request could not be completed.');
    }
    return result;
  }
  const random = () => Array.from(crypto.getRandomValues(new Uint8Array(32)), x => x.toString(16).padStart(2, '0')).join('');
  async function login() {
    const resuming = dirty && current;
    const state = random(), verifier = random();
    const popup = window.open('about:blank', 'ntnet-cms-login', 'width=560,height=700');
    if (!popup) throw new Error('Allow the login popup, then try again.');
    const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier)));
    const challenge = btoa(String.fromCharCode(...digest)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const url = new URL(config.apiBase.replace(/\/$/, '') + '/login.php');
    const origin = url.origin;
    url.search = new URLSearchParams({site: config.siteId, state, challenge});
    await new Promise((resolve, reject) => {
      let pending = false;
      const cleanup = () => { window.removeEventListener('message', receive); clearInterval(poll); clearTimeout(timeout); };
      const receive = async event => {
        if (pending || event.origin !== origin || event.source !== popup || event.data?.type !== 'ntnet-cms-login' || event.data.state !== state || event.data.site !== config.siteId) return;
        pending = true;
        try {
          const result = await api('exchange', {code: event.data.code, verifier});
          token = result.token; user = result.user;
          cleanup(); popup.close(); resolve();
        } catch (error) { cleanup(); popup.close(); reject(error); }
      };
      const poll = setInterval(() => { if (!pending && popup.closed) { cleanup(); reject(new Error('Login was closed. You can try again.')); } }, 500);
      const timeout = setTimeout(() => { cleanup(); popup.close(); reject(new Error('Login timed out. Please try again.')); }, 300000);
      window.addEventListener('message', receive);
      popup.location = url.href;
    });
    $('login-panel').hidden = true; $('workspace').hidden = false; $('identity').textContent = 'Signed in as ' + user;
    $('sections').replaceChildren();
    for (const section of config.sections) {
      const button = document.createElement('button'); button.type = 'button'; button.textContent = section.label;
      button.addEventListener('click', () => choose(section).catch(error => status(error.message, true)));
      button.dataset.section = section.name; $('sections').append(button);
    }
    if (resuming) { for (const button of $('sections').children) button.setAttribute('aria-current', String(button.dataset.section === current.name)); status('Signed in again. Your unsaved changes are still here.'); }
    else { await choose(config.sections[0]); status('Signed in. Choose content to edit.'); }
  }
  function mayLeave() { return !dirty || confirm('Discard your unsaved changes?'); }
  async function choose(section) {
    if (busy || !mayLeave()) return;
    lock(true);
    try {
      let entries = [], id = '', result = {values: {}, sha: null};
      if (section.kind === 'collection') {
        entries = (await api('list', {section: section.name}, 'GET')).entries;
        id = entries[0]?.id || '';
      }
      if (section.kind !== 'collection' || id) result = await api('read', {section: section.name, id}, 'GET');
      current = section; identifier = id; sha = result.sha; dirty = false;
      for (const button of $('sections').children) button.setAttribute('aria-current', String(button.dataset.section === section.name));
      $('section-heading').textContent = section.label; $('collection').hidden = section.kind !== 'collection';
      $('issue-picker').replaceChildren();
      for (const entry of entries) { const option = document.createElement('option'); option.value = entry.id; option.textContent = entry.id; $('issue-picker').append(option); }
      render(result.values);
    } finally { lock(false); }
  }
  async function load(id) {
    lock(true);
    try {
      const result = await api('read', {section: current.name, id}, 'GET');
      identifier = id; sha = result.sha; render(result.values); dirty = false;
    } finally { $('issue-picker').value = identifier; lock(false); }
  }
  function create() { identifier = ''; sha = null; render({}); $('issue-picker').selectedIndex = -1; dirty = false; }
  function render(values) {
    $('fields').replaceChildren(); inputs.clear();
    $('slug-row').hidden = current.kind !== 'collection' || !!identifier; $('slug').value = identifier; $('slug').required = current.kind === 'collection' && !identifier;
    for (const field of current.fields) {
      const label = document.createElement('label'); label.htmlFor = 'field-' + field.name; label.textContent = field.label;
      const input = document.createElement(field.widget === 'text' ? 'textarea' : 'input');
      input.id = label.htmlFor; input.name = field.name;
      if (field.widget === 'boolean') { input.type = 'checkbox'; input.checked = values[field.name] ?? field.default ?? false; }
      else {
        input.type = field.widget === 'number' ? 'number' : 'text';
        input.value = values[field.name] ?? '';
        input.required = field.required !== false;
        if (field.widget === 'file' || field.widget === 'image') input.readOnly = true;
        if (input.type === 'number') { input.min = field.min ?? 1; input.step = field.step ?? 1; }
      }
      input.addEventListener('input', () => { dirty = true; });
      $('fields').append(label, input); inputs.set(field.name, input);
      if (field.hint) { const hint = document.createElement('small'); hint.id = input.id + '-hint'; hint.textContent = field.hint; input.setAttribute('aria-describedby', hint.id); $('fields').append(hint); }
      if (field.widget === 'file' || field.widget === 'image') {
        const uploader = document.createElement('input'); uploader.type = 'file'; uploader.accept = field.widget === 'file' ? '.pdf' : '.jpg,.jpeg,.png,.webp,.gif';
        uploader.setAttribute('aria-label', 'Upload ' + field.label);
        uploader.addEventListener('change', () => upload(uploader, input, field.widget === 'file' ? 'pdf' : 'image'));
        $('fields').append(uploader);
      }
    }
  }
  async function upload(uploader, input, kind) {
    const file = uploader.files[0]; if (!file || busy) return;
    if (file.size > 15 * 1024 * 1024) { status('Choose a file smaller than 15 MB.', true); uploader.value = ''; return; }
    lock(true); status('Uploading file…');
    try {
      const base64 = await new Promise((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(reader.result.split(',')[1]); reader.onerror = () => reject(new Error('Could not read the file.')); reader.readAsDataURL(file); });
      const result = await api('upload', {kind, name: file.name, content: base64});
      input.value = result.path; dirty = true; status('File uploaded. Save the entry to use it on the website.');
    } catch (error) { status(error.message, true); }
    finally { uploader.value = ''; lock(false); }
  }
  $('editor').addEventListener('submit', async event => {
    event.preventDefault(); if (!current || busy) return;
    const values = {};
    for (const field of current.fields) { const input = inputs.get(field.name); values[field.name] = field.widget === 'boolean' ? input.checked : field.widget === 'number' && input.value !== '' ? Number(input.value) : input.value; }
    const id = current.kind === 'collection' ? identifier || $('slug').value : '';
    lock(true); status('Saving…');
    try {
      const result = await api('save', {section: current.name, id, sha, values});
      sha = result.sha; identifier = id; dirty = false; render(values);
      if (current.kind === 'collection') { if (![...$('issue-picker').options].some(option => option.value === id)) { const option = document.createElement('option'); option.value = id; option.textContent = values.title || id; $('issue-picker').append(option); } $('issue-picker').value = id; }
      status('Saved. The website will update after its build finishes.');
    } catch (error) { status(error.message, true); }
    finally { lock(false); }
  });
  $('login').addEventListener('click', () => { $('login').disabled = true; login().catch(error => status(error.message, true)).finally(() => { $('login').disabled = false; }); });
  $('logout').addEventListener('click', async () => {
    if (busy || !mayLeave()) return;
    try { await api('logout', {}); } catch {}
    token = ''; user = ''; dirty = false; $('workspace').hidden = true; $('login-panel').hidden = false; $('fields').replaceChildren(); status('Signed out of this editor.');
  });
  $('issue-picker').addEventListener('change', () => { if (!busy && mayLeave()) load($('issue-picker').value).catch(error => status(error.message, true)); else $('issue-picker').value = identifier; });
  $('slug').addEventListener('input', () => { dirty = true; });
  $('new-issue').addEventListener('click', () => { if (!busy && mayLeave()) create(); });
  window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
  fetch('./editor-config.json', {cache: 'no-store'}).then(response => { if (!response.ok) throw new Error('Editor configuration is missing.'); return response.json(); }).then(value => { config = value; $('login').disabled = false; }).catch(error => { $('login').disabled = true; status(error.message, true); });
})();
