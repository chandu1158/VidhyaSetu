// ============================================================
//  VIDHYASETU – Firebase Configuration
//  Project: vidhyasetu-87264
// ============================================================
//  ⚠️  Run via: python serve.py  (NOT file://)
//  Then open: http://localhost:8080/public/index.html
// ============================================================

const firebaseConfig = {
    apiKey: "AIzaSyBPHSpUPGY5FWsAvakB64fwJxPmFhbNo_Q",
    authDomain: "vidhyasetu-87264.firebaseapp.com",
    databaseURL: "https://vidhyasetu-87264-default-rtdb.firebaseio.com",
    projectId: "vidhyasetu-87264",
    storageBucket: "vidhyasetu-87264.firebasestorage.app",
    messagingSenderId: "822725244092",
    appId: "1:822725244092:web:75dd740f7d2948437dccb9",
    measurementId: "G-NYG91V45SG"
};

// Warn if running on file:// — Firebase WILL NOT work in this mode
if (window.location.protocol === 'file:') {
    console.warn(
        '%c⚠️  VidhyaSetu: Firebase needs a web server!\n' +
        'Run: python serve.py\n' +
        'Then visit: http://localhost:8080/public/index.html',
        'background:#ff6b00;color:white;font-size:13px;padding:8px;'
    );
}

// Initialize Firebase
firebase.initializeApp(firebaseConfig);

// ── Core service shortcuts (used by every page) ───────────────
const auth = firebase.auth();      // Firebase Authentication
const db = firebase.firestore(); // Firestore (users/bookings/payments)

// Realtime Database (chat) — safe fallback if not enabled yet
let rtdb;
try {
    rtdb = firebase.database();
    console.log('%c✅ Realtime Database connected', 'color:green;font-weight:bold;');
} catch (e) {
    console.warn('⚠️ Realtime Database not enabled. Go to Firebase Console → Realtime Database → Create Database.');
    // Safe stub so chat pages don't crash
    rtdb = {
        ref: () => ({
            push: () => Promise.resolve(),
            on: () => { },
            limitToLast: () => ({ on: () => { } })
        })
    };
}

// Firestore settings (improves reliability on local networks)
db.settings({ experimentalForceLongPolling: true });

// Analytics (optional — safe if not loaded)
try { if (typeof firebase.analytics === 'function') firebase.analytics(); } catch (e) { }

console.log('%c✅ VidhyaSetu → Firebase project: vidhyasetu-87264', 'color:#5C5470;font-weight:bold;');
