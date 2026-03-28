// api calls
const API = {
  base: '',

  token() { return localStorage.getItem('jwt_token'); },
  user() { const u = localStorage.getItem('user'); return u ? JSON.parse(u) : null; },

  setAuth(token, user) {
    localStorage.setItem('jwt_token', token);
    localStorage.setItem('user', JSON.stringify(user));
  },

  clearAuth() {
    localStorage.removeItem('jwt_token');
    localStorage.removeItem('user');
    localStorage.removeItem('refresh_token');
  },

  isAdmin() {
    const u = this.user();
    return u && u.roles && u.roles.includes('ROLE_ADMIN');
  },

  async fetch(path, options = {}) {
    const token = this.token();
    const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
    if (token) headers['Authorization'] = `Bearer ${token}`;
    const res = await fetch(this.base + path, { ...options, headers });
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, status: res.status, data };
  },

  get(path) { return this.fetch(path); },
  post(path, body) { return this.fetch(path, { method: 'POST', body: JSON.stringify(body) }); },
  put(path, body) { return this.fetch(path, { method: 'PUT', body: JSON.stringify(body) }); },
  delete(path) { return this.fetch(path, { method: 'DELETE' }); },
};

// nav logic
function renderNav() {
  const user = API.user();
  const settingsLi = document.getElementById('nav-settings-li');
  const authLinks = document.getElementById('nav-auth-links');
  const adminLink = document.getElementById('nav-admin-link');

  if (settingsLi) settingsLi.style.display = '';
  if (authLinks) {
    if (user) {
      authLinks.innerHTML = `<li><a href="#" onclick="logout()">Logout</a></li>`;
    } else {
      authLinks.innerHTML = `<li><a href="/login">Login</a></li>`;
    }
  }
  if (adminLink) {
    adminLink.style.display = API.isAdmin() ? '' : 'none';
  }
}

function logout() {
  API.clearAuth();
  window.location.href = '/login';
}

// alerts
function showAlert(id, message, type = 'error') {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = message;
  el.className = `alert alert-${type} show`;
  setTimeout(() => el.classList.remove('show'), 5000);
}

// passkey helpers
function bufferToBase64Url(buffer) {
  const bytes = new Uint8Array(buffer);
  let str = '';
  for (const byte of bytes) str += String.fromCharCode(byte);
  return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

function base64UrlToBuffer(base64url) {
  if (!base64url) return new ArrayBuffer(0);
  let base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
  base64 += '='.repeat((4 - base64.length % 4) % 4);
  const binary = atob(base64);
  return Uint8Array.from(binary, c => c.charCodeAt(0)).buffer;
}

async function registerPasskey(email) {
  const optRes = await API.post('/api/passkey/register/options', { email });
  if (!optRes.ok) throw new Error(optRes.data.error || 'Failed to get options');

  const options = optRes.data;
  const challengeToken = options.challengeToken;
  delete options.challengeToken;

  options.challenge = base64UrlToBuffer(options.challenge);
  options.user.id = base64UrlToBuffer(options.user.id);
  if (options.excludeCredentials && Array.isArray(options.excludeCredentials)) {
    options.excludeCredentials = options.excludeCredentials
      .filter(c => c && c.id)
      .map(c => ({ ...c, id: base64UrlToBuffer(c.id) }));
  } else {
    delete options.excludeCredentials;
  }

  const credential = await navigator.credentials.create({ publicKey: options });

  const verifyRes = await API.post('/api/passkey/register/verify', {
    challengeToken,
    id: credential.id,
    rawId: bufferToBase64Url(credential.rawId),
    response: {
      clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
      attestationObject: bufferToBase64Url(credential.response.attestationObject),
    },
    type: credential.type,
  });

  if (!verifyRes.ok) throw new Error(verifyRes.data.error || 'Verification failed');
  return verifyRes.data;
}

async function loginPasskey() {
  const optRes = await API.post('/api/passkey/login/options', {});
  if (!optRes.ok) throw new Error(optRes.data.error || 'Failed to get options');

  const options = optRes.data;
  const challengeToken = options.challengeToken;
  delete options.challengeToken;

  if (!options || !options.challenge) throw new Error('Invalid options response');
  options.challenge = base64UrlToBuffer(options.challenge);
  if (options.allowCredentials && Array.isArray(options.allowCredentials)) {
    options.allowCredentials = options.allowCredentials
      .filter(c => c && c.id)
      .map(c => ({ ...c, id: base64UrlToBuffer(c.id) }));
  } else {
    delete options.allowCredentials;
  }

  const assertion = await navigator.credentials.get({ publicKey: options });

  const verifyRes = await API.post('/api/passkey/login/verify', {
    challengeToken,
    id: assertion.id,
    rawId: bufferToBase64Url(assertion.rawId),
    response: {
      clientDataJSON: bufferToBase64Url(assertion.response.clientDataJSON),
      authenticatorData: bufferToBase64Url(assertion.response.authenticatorData),
      signature: bufferToBase64Url(assertion.response.signature),
      userHandle: assertion.response.userHandle ? bufferToBase64Url(assertion.response.userHandle) : null,
    },
    type: assertion.type,
  });

  if (!verifyRes.ok) throw new Error(verifyRes.data.error || 'Login failed');
  return verifyRes.data;
}

// date format
function formatDate(dateStr) {
  const d = new Date(dateStr.replace(' ', 'T'));
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// theme logic
function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'dark';
  const next = current === 'light' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
}

const savedTheme = localStorage.getItem('theme') || 'dark';
document.documentElement.setAttribute('data-theme', savedTheme);

// shape logic
function initDynamicShapes() {
  const bg = document.querySelector('.m3-bg');
  if (!bg) return;
  const shapes = ['shape-circle', 'shape-square', 'shape-triangle'];
  for (let i = 0; i < 20; i++) {
    const el = document.createElement('div');
    const shape = shapes[Math.floor(Math.random() * shapes.length)];
    el.className = `shape ${shape}`;
    
    // random props
    const size = 20 + Math.random() * 40;
    if (shape === 'shape-triangle') {
      const half = size / 2;
      el.style.borderLeft = `${half}px solid transparent`;
      el.style.borderRight = `${half}px solid transparent`;
      el.style.borderBottom = `${size}px solid #3b82f6`;
      el.style.width = '0'; el.style.height = '0';
    } else {
      el.style.width = `${size}px`;
      el.style.height = `${size}px`;
    }

    el.style.top = `${-10 + Math.random() * 120}%`;
    el.style.left = `${-10 + Math.random() * 120}%`;
    el.style.animationDuration = `${25 + Math.random() * 45}s`;
    el.style.animationDelay = `-${Math.random() * 40}s`;
    
    // random spin/fade
    el.style.opacity = 0.05 + Math.random() * 0.1;
    if (Math.random() > 0.5) el.style.animationDirection = 'alternate-reverse';

    bg.appendChild(el);
  }
}

// settings modal
function openSettingsModal() {
  const user = API.user();
  
  const emailEl = document.getElementById('settings-user-email');
  const userGroup = document.getElementById('settings-user-group');
  const securityGroup = document.getElementById('settings-security-group');
  
  if (user) {
    if (emailEl) emailEl.textContent = user.email;
    if (userGroup) userGroup.style.display = '';
    if (securityGroup) securityGroup.style.display = '';
  } else {
    if (userGroup) userGroup.style.display = 'none';
    if (securityGroup) securityGroup.style.display = 'none';
  }
  
  const msgEl = document.getElementById('settings-passkey-msg');
  if (msgEl) {
    msgEl.textContent = '';
    msgEl.className = '';
  }
  
  document.getElementById('settings-modal-backdrop').classList.add('open');
}

async function doSettingsPasskeyRegister(event) {
  const user = API.user();
  if (!user) return;
  
  const btn = event.currentTarget || event.target;
  const originalHtml = btn.innerHTML;
  btn.innerHTML = '<span class="spinner" style="width:12px;height:12px;border-width:2px;border-top-color:#111"></span> Working...';
  btn.disabled = true;
  
  const msgEl = document.getElementById('settings-passkey-msg');
  msgEl.textContent = '';
  
  try {
    const result = await registerPasskey(user.email);
    API.setAuth(result.token, result.user);
    msgEl.textContent = 'Passkey added securely!';
    msgEl.style.color = 'var(--green)';
    setTimeout(() => {
      document.getElementById('settings-modal-backdrop').classList.remove('open');
    }, 1500);
  } catch (e) {
    msgEl.textContent = e.message;
    msgEl.style.color = 'var(--red)';
  } finally {
    btn.innerHTML = originalHtml;
    btn.disabled = false;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  renderNav();
  initDynamicShapes();
});
