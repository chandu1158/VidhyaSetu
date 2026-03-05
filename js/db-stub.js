// ================================================================
//  VIDHYASETU — Database Stub (Firebase Removed)
//  Replace these stubs with your MongoDB REST API calls.
//
//  HOW TO CONNECT MONGODB:
//  1. Build a backend API (Node.js + Express + Mongoose recommended)
//  2. Replace the TODO sections below with fetch() calls to your API
//  3. Update API_BASE to point to your backend URL
// ================================================================

const API_BASE = 'http://localhost:5000/api'; // ← Change to your MongoDB backend URL

// ─── Session helpers (localStorage) ─────────────────────────────
function _saveSession(user) { localStorage.setItem('vs_user', JSON.stringify(user)); }
function _getSession() { return JSON.parse(localStorage.getItem('vs_user') || 'null'); }
function _clearSession() { localStorage.removeItem('vs_user'); localStorage.removeItem('vs_userdata'); }

// ─── AUTH STUB ────────────────────────────────────────────────────
// Mimics firebase.auth() API so all pages work without change
const auth = {
    currentUser: _getSession(),

    onAuthStateChanged(callback) {
        // Simulate async Firebase behaviour
        setTimeout(() => callback(_getSession()), 0);
        return () => { }; // unsubscribe noop
    },

    async createUserWithEmailAndPassword(email, password) {
        // TODO: POST to your MongoDB register endpoint
        // Example:
        // const res = await fetch(`${API_BASE}/auth/register`, {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify({ email, password })
        // });
        // const data = await res.json();
        // if (!res.ok) throw new Error(data.message);
        // const user = { uid: data._id || data.uid, email };
        // _saveSession(user);
        // return { user };

        // Temporary local stub (works without backend)
        const uid = 'local_' + Date.now();
        const user = { uid, email };
        _saveSession(user);
        return { user };
    },

    async signInWithEmailAndPassword(email, password) {
        // TODO: POST to your MongoDB login endpoint
        // Example:
        // const res = await fetch(`${API_BASE}/auth/login`, {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify({ email, password })
        // });
        // const data = await res.json();
        // if (!res.ok) throw new Error(data.message || 'Invalid credentials');
        // const user = { uid: data._id || data.uid, email };
        // _saveSession(user);
        // return { user };

        // Temporary local stub
        const stored = JSON.parse(localStorage.getItem('vs_users_' + email) || 'null');
        if (!stored) throw new Error('No account found with this email. Please register first.');
        if (stored.password !== password) throw new Error('Incorrect password.');
        const user = { uid: stored.uid, email };
        _saveSession(user);
        return { user };
    },

    async signOut() {
        _clearSession();
    }
};

// ─── FIRESTORE STUB ───────────────────────────────────────────────
// Mimics firebase.firestore() API using localStorage
// Replace with fetch() calls to your MongoDB API endpoints
const db = {
    collection(collName) {
        return {
            doc(docId) {
                const key = `vs_${collName}_${docId}`;
                return {
                    async get() {
                        const raw = localStorage.getItem(key);
                        const val = raw ? JSON.parse(raw) : null;
                        return {
                            exists: !!val,
                            id: docId,
                            data: () => val
                        };
                    },
                    async set(data) {
                        localStorage.setItem(key, JSON.stringify({ ...data, _id: docId }));
                    },
                    async update(data) {
                        const existing = JSON.parse(localStorage.getItem(key) || '{}');
                        localStorage.setItem(key, JSON.stringify({ ...existing, ...data }));
                    },
                    async delete() {
                        localStorage.removeItem(key);
                    }
                };
            },
            async add(data) {
                const id = 'doc_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
                const key = `vs_${collName}_${id}`;
                localStorage.setItem(key, JSON.stringify({ ...data, _id: id }));
                return { id };
            },
            where(field, op, value) {
                // Return chainable stub
                const self = this;
                const stub = {
                    where() { return stub; },
                    orderBy() { return stub; },
                    limitToLast() { return stub; },
                    async get() {
                        // Get all docs in collection that match
                        const docs = [];
                        for (let i = 0; i < localStorage.length; i++) {
                            const k = localStorage.key(i);
                            if (!k.startsWith(`vs_${collName}_`)) continue;
                            try {
                                const d = JSON.parse(localStorage.getItem(k));
                                if (d && (op === '==' ? d[field] == value : true)) {
                                    docs.push({ id: d._id || k, data: () => d });
                                }
                            } catch (e) { }
                        }
                        return { empty: docs.length === 0, docs };
                    }
                };
                return stub;
            },
            orderBy() { return this.where(); },
            async get() {
                const docs = [];
                for (let i = 0; i < localStorage.length; i++) {
                    const k = localStorage.key(i);
                    if (!k.startsWith(`vs_${collName}_`)) continue;
                    try {
                        const d = JSON.parse(localStorage.getItem(k));
                        if (d) docs.push({ id: d._id || k, data: () => d });
                    } catch (e) { }
                }
                return { empty: docs.length === 0, docs };
            }
        };
    },
    settings() { } // noop
};

// ─── REALTIME DB STUB ─────────────────────────────────────────────
// Mimics firebase.database() for chat — replace with Socket.io or MongoDB Atlas when ready
const rtdb = {
    ref(path) {
        return {
            push: async (data) => {
                const msgs = JSON.parse(localStorage.getItem('vs_rtdb_' + path) || '[]');
                msgs.push({ ...data, timestamp: Date.now() });
                localStorage.setItem('vs_rtdb_' + path, JSON.stringify(msgs));
            },
            on: (event, cb) => {
                const msgs = JSON.parse(localStorage.getItem('vs_rtdb_' + path) || '[]');
                if (event === 'value' && msgs.length) {
                    const snap = { val: () => msgs.reduce((a, m, i) => { a[i] = m; return a; }, {}) };
                    cb(snap);
                }
            },
            limitToLast: (n) => ({
                on(event, cb) {
                    const msgs = JSON.parse(localStorage.getItem('vs_rtdb_' + path) || '[]');
                    const slice = msgs.slice(-n);
                    if (event === 'value' && slice.length) {
                        const snap = { val: () => slice.reduce((a, m, i) => { a[i] = m; return a; }, {}) };
                        cb(snap);
                    }
                }
            })
        };
    }
};

// ─── FIREBASE NAMESPACE SHIM ─────────────────────────────────────
// Keeps firebase.firestore.FieldValue.serverTimestamp() working
const firebase = {
    firestore: {
        FieldValue: {
            serverTimestamp: () => new Date().toISOString(),
            increment: (n) => n
        }
    },
    database: {
        ServerValue: { TIMESTAMP: Date.now() }
    }
};

// ─── UI UTILITIES ─────────────────────────────────────────────────
function showToast(msg, type = 'info') {
    let t = document.getElementById('toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'toast';
        t.className = 'toast';
        document.body.appendChild(t);
    }
    const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
    t.innerHTML = `<span>${icon}</span> ${msg}`;
    t.className = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 3500);
}

async function logout() {
    await auth.signOut();
    window.location.href = '../public/index.html';
}

console.log('%c🔧 VidhyaSetu — Running in LOCAL STUB mode. Connect MongoDB to go live.', 'color:#5C5470;font-weight:bold;font-size:13px;');
