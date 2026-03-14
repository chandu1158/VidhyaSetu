// ================================================================
//  VidhyaSetu — Auth Logic (PHP Backend Connected)
//  js/auth.js
//  Calls register.php, login.php, check_uid.php directly.
//  Replaces the Firebase/stub-based approach.
// ================================================================

// ── Session Helpers ──────────────────────────────────────────

/**
 * Save user profile and session token to localStorage.
 * @param {Object} userData - Full user object from the server.
 * @param {string} token    - Session token from the server.
 */
function saveSession(userData, token) {
    localStorage.setItem('vs_token', token);
    localStorage.setItem('vs_userdata', JSON.stringify(userData));
    // Keep a lightweight key for quick auth checks
    localStorage.setItem('vs_user', JSON.stringify({ uid: userData.uid, email: userData.email, role: userData.role }));
}

/** Clear all session data (used on logout). */
function clearSession() {
    localStorage.removeItem('vs_token');
    localStorage.removeItem('vs_userdata');
    localStorage.removeItem('vs_user');
}

/** Return the Authorization header for authenticated requests. */
function authHeader() {
    const token = localStorage.getItem('vs_token') || '';
    return {
        'Authorization': `Bearer ${token}`,
        'X-Auth-Token': token,
        'Content-Type': 'application/json'
    };
}

/** Return cached user data from localStorage (no network call). */
function getCurrentUser() {
    return JSON.parse(localStorage.getItem('vs_userdata') || 'null');
}

/** Async wrapper kept for backward compat with pages using await getCurrentUserData(). */
async function getCurrentUserData() {
    return getCurrentUser();
}

// ── Toast Notifications ─────────────────────────────────────

function showToast(msg, type = 'info') {
    let t = document.getElementById('toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'toast';
        t.className = 'toast';
        document.body.appendChild(t);
    }
    const icon = { success: '✅', error: '❌', info: 'ℹ️' }[type] || 'ℹ️';
    t.innerHTML = `<span>${icon}</span> ${msg}`;
    t.className = `toast ${type} show`;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 3500);
}

// ── Registration ────────────────────────────────────────────

/**
 * Register a new student.
 * Sends form data to register.php and redirects on success.
 * @throws {Error} Throws on network error or non-success response.
 */
async function registerStudent(data) {
    const res = await fetch('api/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...data, role: 'student' })
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Registration failed.');

    saveSession(json, json.token);
    showToast('Account created! Redirecting…', 'success');
    setTimeout(() => { window.location.href = 'dashboard.html'; }, 1500);
    return json;
}

/**
 * Register a new tutor.
 * On success, redirects to the skill test.
 * @throws {Error} Throws on network error or non-success response.
 */
async function registerTutor(data) {
    const res = await fetch('api/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...data, role: 'tutor' })
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Registration failed.');

    saveSession(json, json.token);
    sessionStorage.setItem('vs_primary_subject', data.primarySubject || '');
    showToast('Account created! Please complete the skill test.', 'success');
    setTimeout(() => { window.location.href = 'skill-test.html'; }, 1500);
    return json;
}

// ── Login ────────────────────────────────────────────────────

/**
 * Login a student using email or user ID + password.
 * Redirects to student dashboard on success.
 * @throws {Error} Throws with server message so handleLogin() can display it.
 */
async function loginStudent(emailOrUid, password) {
    const res = await fetch('api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: emailOrUid, password, role: 'student' })
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Login failed.');

    saveSession(json, json.token);
    showToast('Login successful! Redirecting…', 'success');
    setTimeout(() => { window.location.href = 'dashboard.html'; }, 1200);
    return json;
}

/**
 * Login a tutor using email or user ID + password.
 * Redirects to skill-test if not yet passed, else to tutor dashboard.
 * @throws {Error} Throws with server message so handleLogin() can display it.
 */
async function loginTutor(emailOrUid, password) {
    const res = await fetch('api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: emailOrUid, password, role: 'tutor' })
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Login failed.');

    saveSession(json, json.token);
    showToast('Login successful! Redirecting…', 'success');

    if (!json.skillTestPassed) {
        // Store subject so the skill-test page knows what to load
        sessionStorage.setItem('vs_primary_subject', json.subjects?.[0] || '');
        setTimeout(() => { window.location.href = 'skill-test.html'; }, 1200);
    } else {
        setTimeout(() => { window.location.href = 'T_dashboard.html'; }, 1200);
    }
    return json;
}

// ── Logout ──────────────────────────────────────────────────

/** Clear session and redirect to home. */
async function logout() {
    try {
        await fetch('api/logout.php', {
            method: 'POST',
            headers: authHeader()
        });
    } catch (e) {
        console.warn('Backend logout failed:', e);
    }
    clearSession();
    window.location.href = 'index.html';
}

// ── UID Uniqueness Check ─────────────────────────────────────

/**
 * Check if a user-chosen UID is available using check_uid.php.
 * Updates the message element with a live result.
 * @param {string} role     - 'student' or 'tutor' (unused by PHP, kept for compat).
 * @param {string} uid      - The UID entered by the user.
 * @param {string} msgElId  - ID of the element to write the status into.
 */
async function checkUIDUniqueness(role, uid, msgElId) {
    const el = document.getElementById(msgElId);
    if (!el) return;

    if (!uid || uid.length < 3) {
        el.textContent = '';
        return;
    }

    el.innerHTML = '<span style="color:#888">Checking…</span>';
    try {
        const res = await fetch(`api/check_uid.php?uid=${encodeURIComponent(uid)}`);
        const json = await res.json();

        if (json.success) {
            if (json.available) {
                el.innerHTML = '<span style="color:green">✅ Available</span>';
            } else {
                el.innerHTML = '<span style="color:#c62828">❌ Already taken — choose another</span>';
            }
        } else {
            // Server error (like DB connection failure)
            el.innerHTML = `<span style="color:#e65100">⚠️ ${json.message || 'Error checking availability'}</span>`;
        }
    } catch (err) {
        el.innerHTML = '<span style="color:#888">Could not connect to server</span>';
    }
}

// ── Route Guard ─────────────────────────────────────────────

/**
 * Redirect unauthenticated users to the appropriate login page.
 * Call this at the top of any protected dashboard page.
 * @param {'student'|'tutor'} expectedRole
 */
function requireLogin(expectedRole) {
    const user = getCurrentUser();
    if (!user) {
        window.location.href = expectedRole === 'tutor' ? 'tutor-login.html' : 'student-login.html';
        return false;
    }
    if (user.role !== expectedRole) {
        showToast(`Access denied — this page is for ${expectedRole}s only.`, 'error');
        setTimeout(() => { window.location.href = 'index.html'; }, 2000);
        return false;
    }
    return true;
}

// ── Forgot Password Flow ─────────────────────────────────────

async function forgotPassword(email) {
    const res = await fetch('api/forgot_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Failed to send OTP');
    return json;
}

async function verifyOTP(email, otp) {
    const res = await fetch('api/verify_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, otp })
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Invalid OTP');
    return json;
}

async function resetPassword(email, otp, password) {
    const res = await fetch('api/reset_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, otp, password })
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Password reset failed');
    return json;
}
