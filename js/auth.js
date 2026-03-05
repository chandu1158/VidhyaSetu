// ================================================================
//  VIDHYASETU — Auth Logic (Firebase Removed)
//  Uses the db-stub.js stubs (auth, db, rtdb)
//  Replace stub API calls with your MongoDB backend when ready.
// ================================================================

// ---- Register Student ----
async function registerStudent(data) {
    try {
        const cred = await auth.createUserWithEmailAndPassword(data.email, data.password);
        const uid = cred.user.uid;

        // Save user profile
        const profile = {
            uid, role: 'student',
            name: data.name, email: data.email, phone: data.phone,
            college: data.college, subjects: data.subjects,
            mode: data.mode, location: data.location,
            gender: data.gender, dob: data.dob, age: data.age,
            status: data.status, profilePhoto: data.profilePhoto || '',
            createdAt: new Date().toISOString()
        };
        await db.collection('users').doc(uid).set(profile);

        // Also save by email for login lookup
        localStorage.setItem('vs_users_' + data.email, JSON.stringify({ ...profile, password: data.password }));
        localStorage.setItem('vs_userdata', JSON.stringify(profile));

        showToast('Account created! Redirecting…', 'success');
        setTimeout(() => window.location.href = '../student/dashboard.html', 1500);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ---- Register Tutor ----
async function registerTutor(data) {
    try {
        const cred = await auth.createUserWithEmailAndPassword(data.email, data.password);
        const uid = cred.user.uid;

        const profile = {
            uid, role: 'tutor',
            name: data.name, email: data.email, phone: data.phone,
            gender: data.gender, dob: data.dob, age: data.age,
            status: data.status, subjects: data.subjects,
            primarySubject: data.primarySubject || (data.subjects && data.subjects[0]) || '',
            location: data.location, occupation: data.occupation,
            experience: data.experience, availability: data.availability,
            teachingMode: data.teachingMode || data.availability,
            profilePhoto: data.profilePhoto || '',
            skillScore: null, skillLevel: null, skillTestPassed: false,
            createdAt: new Date().toISOString()
        };
        await db.collection('users').doc(uid).set(profile);

        localStorage.setItem('vs_users_' + data.email, JSON.stringify({ ...profile, password: data.password }));
        localStorage.setItem('vs_userdata', JSON.stringify(profile));

        // Save subject for skill test page
        sessionStorage.setItem('vs_primary_subject', profile.primarySubject);

        showToast('Account created! Please complete the skill test.', 'success');
        setTimeout(() => window.location.href = '../tutor/skill-test.html', 1500);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ---- Login Student ----
async function loginStudent(email, password) {
    try {
        const cred = await auth.signInWithEmailAndPassword(email, password);
        const snap = await db.collection('users').doc(cred.user.uid).get();
        const user = snap.data() || JSON.parse(localStorage.getItem('vs_users_' + email) || 'null');
        if (!user || user.role !== 'student') {
            await auth.signOut();
            showToast('This account is not a student account.', 'error'); return;
        }
        localStorage.setItem('vs_userdata', JSON.stringify(user));
        showToast('Login successful! Redirecting…', 'success');
        setTimeout(() => window.location.href = '../student/dashboard.html', 1200);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ---- Login Tutor ----
async function loginTutor(email, password) {
    try {
        const cred = await auth.signInWithEmailAndPassword(email, password);
        const snap = await db.collection('users').doc(cred.user.uid).get();
        const user = snap.data() || JSON.parse(localStorage.getItem('vs_users_' + email) || 'null');
        if (!user || user.role !== 'tutor') {
            await auth.signOut();
            showToast('This account is not a tutor account.', 'error'); return;
        }
        localStorage.setItem('vs_userdata', JSON.stringify(user));
        showToast('Login successful! Redirecting…', 'success');
        if (!user.skillTestPassed) {
            sessionStorage.setItem('vs_primary_subject', user.primarySubject || '');
            setTimeout(() => window.location.href = '../tutor/skill-test.html', 1200);
        } else {
            setTimeout(() => window.location.href = '../tutor/dashboard.html', 1200);
        }
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ---- Logout ----
async function logout() {
    await auth.signOut();
    localStorage.removeItem('vs_userdata');
    window.location.href = '../public/index.html';
}

// ---- Get Current User Data ----
async function getCurrentUserData() {
    return new Promise((resolve) => {
        auth.onAuthStateChanged(async (user) => {
            if (!user) { resolve(null); return; }
            const snap = await db.collection('users').doc(user.uid).get();
            const stored = JSON.parse(localStorage.getItem('vs_userdata') || 'null');
            resolve(snap.data() || stored);
        });
    });
}
