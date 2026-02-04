/**
 * PDC Darts Simulator - Shared Module
 * Contains: Engine, Logic, AuthSystem, DataLoader
 * Used by both darts.html and career.html
 */

// ==================== API CONFIGURATION ====================
const API_BASE = '/api';
const API_BASE_URL = '/api';

// ==================== MATCH ENGINE ====================
window.Engine = {
    getScoringHit: (playerAvg, rhythmBonus, isFinishAttempt) => {
        let effAvg = playerAvg + (rhythmBonus * 2);
        if (isFinishAttempt) effAvg += 6;
        const t20Prob = 0.28 + ((effAvg - 85) * 0.011);
        const rand = Math.random();
        if (rand < t20Prob) return 60;
        const driftProb = 0.12 - ((effAvg - 90) * 0.004);
        if (rand < t20Prob + driftProb) {
            const neighbor = (Math.random() < 0.5) ? 1 : 5;
            return (Math.random() < 0.80) ? neighbor : neighbor * 3;
        }
        return 20;
    },
    getCheckoutHit: (target, playerCO, isPressure, isFav) => {
        let prob = (playerCO / 100) * 1.05;
        if (isFav) prob *= 1.15;
        if (isPressure) prob *= 0.95;
        if (Math.random() < prob) return { pts: target, double: true };
        if (Math.random() < 0.70) return { pts: target / 2, double: false };
        return { pts: 0, double: false };
    },
    getSetupHit: (target, playerAvg) => {
        if (target > 20 && target <= 60 && target % 3 === 0) {
            const prob = 0.35 + ((playerAvg - 85) * 0.01);
            if (Math.random() < prob) return target;
            return target / 3;
        }
        if (Math.random() < 0.96) return target;
        if (target === 20) return (Math.random() < 0.5 ? 1 : 5);
        return target;
    }
};

// ==================== GAME LOGIC ====================
window.Logic = {
    isValidTarget: (t) => (t >= 1 && t <= 20) || t === 25 || t === 50 || (t > 20 && t <= 60 && t % 3 === 0),
    
    // High checkouts that can be finished in 3 darts (for 9-darter attempts)
    HIGH_CHECKOUTS: {
        170: { first: 60, second: 60, double: 50 },  // T20, T20, Bull
        167: { first: 60, second: 57, double: 50 },  // T20, T19, Bull
        164: { first: 60, second: 54, double: 50 },  // T20, T18, Bull
        161: { first: 60, second: 51, double: 50 },  // T20, T17, Bull
        160: { first: 60, second: 60, double: 40 },  // T20, T20, D20
        158: { first: 60, second: 60, double: 38 },  // T20, T20, D19
        157: { first: 60, second: 57, double: 40 },  // T20, T19, D20
        156: { first: 60, second: 60, double: 36 },  // T20, T20, D18
        155: { first: 60, second: 57, double: 38 },  // T20, T19, D19
        154: { first: 60, second: 54, double: 40 },  // T20, T18, D20
        153: { first: 60, second: 57, double: 36 },  // T20, T19, D18
        152: { first: 60, second: 60, double: 32 },  // T20, T20, D16
        151: { first: 60, second: 57, double: 34 },  // T20, T19, D17
        150: { first: 60, second: 60, double: 30 },  // T20, T20, D15
        149: { first: 60, second: 57, double: 32 },  // T20, T19, D16
        148: { first: 60, second: 60, double: 28 },  // T20, T20, D14
        147: { first: 60, second: 57, double: 30 },  // T20, T19, D15
        146: { first: 60, second: 54, double: 32 },  // T20, T18, D16
        145: { first: 60, second: 57, double: 28 },  // T20, T19, D14
        144: { first: 60, second: 60, double: 24 },  // T20, T20, D12
        143: { first: 60, second: 57, double: 26 },  // T20, T19, D13
        142: { first: 60, second: 54, double: 28 },  // T20, T18, D14
        141: { first: 60, second: 57, double: 24 },  // T20, T19, D12
        140: { first: 60, second: 60, double: 20 },  // T20, T20, D10
        139: { first: 60, second: 57, double: 22 },  // T20, T19, D11
        138: { first: 60, second: 54, double: 24 },  // T20, T18, D12
        137: { first: 60, second: 57, double: 20 },  // T20, T19, D10
        136: { first: 60, second: 60, double: 16 },  // T20, T20, D8
        135: { first: 60, second: 57, double: 18 },  // T20, T19, D9
        134: { first: 60, second: 54, double: 20 },  // T20, T18, D10
        133: { first: 60, second: 57, double: 16 },  // T20, T19, D8
        132: { first: 60, second: 60, double: 12 },  // T20, T20, D6
        131: { first: 60, second: 57, double: 14 },  // T20, T19, D7
        130: { first: 60, second: 60, double: 10 },  // T20, T20, D5
        129: { first: 60, second: 57, double: 12 },  // T20, T19, D6
        128: { first: 60, second: 54, double: 14 },  // T20, T18, D7
        127: { first: 60, second: 57, double: 10 },  // T20, T19, D5
        126: { first: 60, second: 54, double: 12 },  // T20, T18, D6
        125: { first: 60, second: 57, double: 8 },   // T20, T19, D4
        124: { first: 60, second: 54, double: 10 },  // T20, T18, D5
        123: { first: 60, second: 57, double: 6 },   // T20, T19, D3
        122: { first: 60, second: 54, double: 8 },   // T20, T18, D4
        121: { first: 60, second: 57, double: 4 },   // T20, T19, D2
        120: { first: 60, second: 60, double: 0 },   // T20, 20, Bull (special)
        119: { first: 60, second: 57, double: 2 },   // T20, T19, D1
        118: { first: 60, second: 18, double: 40 },  // T20, 18, D20
        117: { first: 60, second: 17, double: 40 },  // T20, 17, D20
        116: { first: 60, second: 16, double: 40 },  // T20, 16, D20
        115: { first: 60, second: 15, double: 40 },  // T20, 15, D20
        114: { first: 60, second: 14, double: 40 },  // T20, 14, D20
        113: { first: 60, second: 13, double: 40 },  // T20, 13, D20
        112: { first: 60, second: 12, double: 40 },  // T20, 12, D20
        111: { first: 60, second: 11, double: 40 },  // T20, 11, D20
        110: { first: 60, second: 10, double: 40 },  // T20, 10, D20
        109: { first: 60, second: 9, double: 40 },   // T20, 9, D20
        108: { first: 60, second: 8, double: 40 },   // T20, 8, D20
        107: { first: 60, second: 7, double: 40 },   // T20, 7, D20
        106: { first: 60, second: 6, double: 40 },   // T20, 6, D20
        105: { first: 60, second: 5, double: 40 },   // T20, 5, D20
        104: { first: 60, second: 4, double: 40 },   // T20, 4, D20
        103: { first: 60, second: 3, double: 40 },   // T20, 3, D20
        102: { first: 60, second: 2, double: 40 },   // T20, 2, D20
        101: { first: 60, second: 1, double: 40 },   // T20, 1, D20
    },
    
    // Attempt a high checkout (100+) - returns { points, endLeg, dartsUsed }
    attemptHighCheckout: (player, score, isDecider) => {
        const route = Logic.HIGH_CHECKOUTS[score];
        if (!route) return null;
        
        let totalPts = 0;
        let dartsUsed = 0;
        
        // First dart (typically T20)
        dartsUsed++;
        const firstHitProb = 0.40 + ((player.avg - 85) * 0.012);
        if (Math.random() < firstHitProb) {
            totalPts += route.first;
        } else {
            const missScore = route.first === 60 ? (Math.random() < 0.7 ? 20 : (Math.random() < 0.5 ? 1 : 5)) : Math.floor(route.first / 3);
            return { points: missScore, endLeg: false, dartsUsed: 1 };
        }
        
        // Second dart
        dartsUsed++;
        let secondHitProb = route.second >= 51 ? (0.38 + ((player.avg - 85) * 0.01)) : 0.90;
        if (route.second === 50) secondHitProb = 0.25 + ((player.co - 35) * 0.008);
        
        if (Math.random() < secondHitProb) {
            totalPts += route.second;
        } else {
            let missScore;
            if (route.second >= 51) {
                missScore = Math.floor(route.second / 3);
            } else if (route.second === 50) {
                missScore = Math.random() < 0.6 ? 25 : (Math.random() < 0.5 ? 1 : 5);
            } else {
                missScore = route.second;
            }
            return { points: totalPts + missScore, endLeg: false, dartsUsed: 2 };
        }
        
        // Third dart (double)
        dartsUsed++;
        if (player.stats) player.stats.coAtt++;
        let doubleProb = (player.co / 100) * 1.10;
        if (isDecider) doubleProb *= 0.92;
        if (route.double === 50) doubleProb *= 0.65;
        
        if (Math.random() < doubleProb) {
            if (player.stats) player.stats.coHit++;
            return { points: totalPts + route.double, endLeg: true, dartsUsed: 3 };
        } else {
            const missScore = route.double === 50 ? (Math.random() < 0.5 ? 25 : 0) : (Math.random() < 0.65 ? route.double / 2 : 0);
            return { points: totalPts + missScore, endLeg: false, dartsUsed: 3 };
        }
    },
    
    processVisit: (player, isDecider) => {
        let score = player.score;
        let visitTotal = 0;
        let rhythm = 0;
        
        // Check if on a 9-darter attempt
        const isNineDarterAttempt = player.stats && player.stats.darts === 6 && score >= 100 && score <= 170 && 
            ![169, 168, 166, 165, 163, 162, 159].includes(score) && Logic.HIGH_CHECKOUTS[score];
        
        if (isNineDarterAttempt) {
            const result = Logic.attemptHighCheckout(player, score, isDecider);
            if (result) {
                return { points: result.points, endLeg: result.endLeg, dartsThrown: result.dartsUsed };
            }
        }
        
        for (let d = 1; d <= 3; d++) {
            if (score <= 1) return { points: 0, endLeg: false, dartsThrown: d };
            let pts = 0;
            if (score <= 170 && ![169, 168, 166, 165, 163, 162, 159].includes(score)) {
                if (d === 1 && score >= 100 && Logic.HIGH_CHECKOUTS[score]) {
                    const result = Logic.attemptHighCheckout(player, score, isDecider);
                    if (result) {
                        return { points: visitTotal + result.points, endLeg: result.endLeg, dartsThrown: result.dartsUsed };
                    }
                }
                
                if (score <= 40 && score % 2 === 0) {
                    if (player.stats) player.stats.coAtt++;
                    const isFav = (score / 2 === player.fav);
                    const res = Engine.getCheckoutHit(score, player.co, isDecider, isFav);
                    if (res.double) {
                        if (player.stats) player.stats.coHit++;
                        return { points: visitTotal + res.pts, endLeg: true, dartsThrown: d };
                    }
                    pts = res.pts;
                    if (score === 2 && pts === 1) return { points: 0, endLeg: false, dartsThrown: d };
                }
                else if (score === 50) {
                    if (player.stats) player.stats.coAtt++;
                    const res = Engine.getCheckoutHit(50, player.co, isDecider, false);
                    if (res.double) {
                        if (player.stats) player.stats.coHit++;
                        return { points: visitTotal + 50, endLeg: true, dartsThrown: d };
                    }
                    pts = res.pts;
                }
                else {
                    if (score > 70) pts = Engine.getScoringHit(player.avg, rhythm, true);
                    else {
                        let target = 0;
                        let idealLeave = (player.fav || 20) * 2;
                        let required = score - idealLeave;
                        if (Logic.isValidTarget(required)) target = required;
                        else {
                            const leaves = [40, 32, 24, 16, 8, 4, 2];
                            let found = false;
                            for (let l of leaves) {
                                let diff = score - l;
                                if (diff > 0 && Logic.isValidTarget(diff)) { target = diff; found = true; break; }
                            }
                            if (!found) {
                                if (score > 2) {
                                    let tryLeave2 = score - 2;
                                    target = Logic.isValidTarget(tryLeave2) ? tryLeave2 : 1;
                                } else target = 0;
                            }
                        }
                        if (target > 0) pts = Engine.getSetupHit(target, player.avg);
                        else pts = 0;
                    }
                }
            }
            else {
                pts = Engine.getScoringHit(player.avg, rhythm, false);
                if (pts >= 57) rhythm = 1; else rhythm = 0;
            }
            if (score - pts < 2 && score - pts !== 0) return { points: 0, endLeg: false, dartsThrown: 3 };
            score -= pts; visitTotal += pts;
            if (score === 0) return { points: visitTotal, endLeg: true, dartsThrown: d };
        }
        return { points: visitTotal, endLeg: false, dartsThrown: 3 };
    }
};

// ==================== DATA LOADER ====================
window.DataLoader = {
    async loadPlayers() {
        try {
            const response = await fetch(API_BASE + '/players');
            const data = await response.json();
            if (data.success && data.players && data.players.length > 0) {
                console.log('[DataLoader] Loaded ' + data.players.length + ' players from database');
                return data.players;
            }
        } catch (e) {
            console.log('[DataLoader] Could not load players from database, using defaults');
        }
        return null;
    },

    async loadTournaments() {
        try {
            const response = await fetch(API_BASE + '/tournaments');
            const data = await response.json();
            if (data.success && data.tournaments && data.tournaments.length > 0) {
                console.log('[DataLoader] Loaded ' + data.tournaments.length + ' tournaments from database');
                return data.tournaments.map(t => ({
                    ...t,
                    cardRequired: Boolean(t.cardRequired),
                    eligibleRegions: typeof t.eligibleRegions === 'string' ? JSON.parse(t.eligibleRegions) : t.eligibleRegions
                }));
            }
        } catch (e) {
            console.log('[DataLoader] Could not load tournaments from database, using defaults');
        }
        return null;
    }
};

// ==================== AUTH SYSTEM ====================
// Shared authentication - same account works for both season and career mode
window.AuthSystem = {
    currentUser: null,
    isInitialized: false,

    serialize: (obj) => {
        return JSON.stringify(obj, (key, value) => {
            if (value instanceof Set) return { __set: [...value] };
            return value;
        });
    },

    deserialize: (str) => {
        return JSON.parse(str, (key, value) => {
            if (value && value.__set) return new Set(value.__set);
            return value;
        });
    },

    apiCall: async (endpoint, method = 'GET', data = null) => {
        const options = {
            method: method,
            headers: { 'Content-Type': 'application/json' }
        };
        if (data) options.body = JSON.stringify(data);
        if (AuthSystem.currentUser && AuthSystem.currentUser.token) {
            options.headers['Authorization'] = 'Bearer ' + AuthSystem.currentUser.token;
        }
        const response = await fetch(API_BASE_URL + endpoint, options);
        return await response.json();
    },

    init: async () => {
        try {
            const savedUser = localStorage.getItem('pdc_auth_user');
            if (savedUser) {
                AuthSystem.currentUser = JSON.parse(savedUser);
                AuthSystem.updateUIStatus();
                try {
                    const result = await AuthSystem.apiCall('/verify', 'POST');
                    if (!result.success) {
                        AuthSystem.logout();
                    }
                } catch (e) {
                    console.log('Server not available, using local data');
                }
            }
            AuthSystem.isInitialized = true;
        } catch (e) {
            console.log('Auth init error:', e.message);
        }
    },

    showModal: () => {
        const modal = document.getElementById('auth-modal');
        if (modal) {
            modal.style.display = 'flex';
            AuthSystem.updateModalView();
        }
    },

    closeModal: () => {
        const modal = document.getElementById('auth-modal');
        if (modal) modal.style.display = 'none';
    },

    updateModalView: () => {
        const loginView = document.getElementById('auth-login-view');
        const accountView = document.getElementById('auth-account-view');
        if (!loginView || !accountView) return;

        if (AuthSystem.currentUser) {
            loginView.style.display = 'none';
            accountView.style.display = 'block';
            const usernameEl = document.getElementById('auth-username-display');
            const syncEl = document.getElementById('auth-last-sync');
            if (usernameEl) usernameEl.innerText = AuthSystem.currentUser.username;
            if (syncEl) syncEl.innerText = AuthSystem.currentUser.lastSync || 'Never';
        } else {
            loginView.style.display = 'block';
            accountView.style.display = 'none';
        }
    },

    updateUIStatus: () => {
        const accountBtn = document.querySelector('[onclick="AuthSystem.showModal()"]');
        if (accountBtn && AuthSystem.currentUser) {
            accountBtn.innerHTML = '👤 ' + AuthSystem.currentUser.username.substring(0, 8);
        } else if (accountBtn) {
            accountBtn.innerHTML = '👤 ACCOUNT';
        }
    },

    switchToRegister: () => {
        const loginForm = document.getElementById('auth-login-form');
        const regForm = document.getElementById('auth-register-form');
        if (loginForm) loginForm.style.display = 'none';
        if (regForm) regForm.style.display = 'block';
    },

    switchToLogin: () => {
        const loginForm = document.getElementById('auth-login-form');
        const regForm = document.getElementById('auth-register-form');
        if (loginForm) loginForm.style.display = 'block';
        if (regForm) regForm.style.display = 'none';
    },

    register: async () => {
        const username = document.getElementById('reg-username').value.trim();
        const password = document.getElementById('reg-password').value;
        const confirmPassword = document.getElementById('reg-confirm-password').value;
        const errorEl = document.getElementById('auth-error');
        if (errorEl) errorEl.innerText = '';

        if (!username || username.length < 3) {
            if (errorEl) errorEl.innerText = 'Username must be at least 3 characters';
            return;
        }
        if (!password || password.length < 6) {
            if (errorEl) errorEl.innerText = 'Password must be at least 6 characters';
            return;
        }
        if (password !== confirmPassword) {
            if (errorEl) errorEl.innerText = 'Passwords do not match';
            return;
        }

        try {
            const result = await AuthSystem.apiCall('/register', 'POST', { username, password });
            if (!result.success) {
                if (errorEl) errorEl.innerText = result.error || 'Registration failed';
                return;
            }
            AuthSystem.currentUser = {
                username: result.username,
                userId: result.userId,
                token: result.token,
                lastSync: null
            };
            localStorage.setItem('pdc_auth_user', JSON.stringify(AuthSystem.currentUser));
            AuthSystem.updateUIStatus();
            AuthSystem.updateModalView();
        } catch (e) {
            console.error('Register error:', e);
            if (errorEl) errorEl.innerText = 'Server unavailable. Try again later.';
        }
    },

    login: async () => {
        const username = document.getElementById('login-username').value.trim();
        const password = document.getElementById('login-password').value;
        const errorEl = document.getElementById('auth-error');
        if (errorEl) errorEl.innerText = '';

        if (!username || !password) {
            if (errorEl) errorEl.innerText = 'Please enter username and password';
            return;
        }

        try {
            const result = await AuthSystem.apiCall('/login', 'POST', { username, password });
            if (!result.success) {
                if (errorEl) errorEl.innerText = result.error || 'Login failed';
                return;
            }
            AuthSystem.currentUser = {
                username: result.username,
                userId: result.userId,
                token: result.token,
                lastSync: result.lastSync || null
            };
            localStorage.setItem('pdc_auth_user', JSON.stringify(AuthSystem.currentUser));
            AuthSystem.updateUIStatus();
            AuthSystem.updateModalView();
        } catch (e) {
            console.error('Login error:', e);
            if (errorEl) errorEl.innerText = 'Server unavailable. Try again later.';
        }
    },

    logout: () => {
        AuthSystem.currentUser = null;
        localStorage.removeItem('pdc_auth_user');
        AuthSystem.updateUIStatus();
        AuthSystem.updateModalView();
    },

    // Career-specific save/load
    saveCareerToCloud: async (careerState) => {
        if (!AuthSystem.currentUser) {
            return { success: false, error: 'Not logged in' };
        }
        try {
            const serialized = AuthSystem.serialize(careerState);
            const result = await AuthSystem.apiCall('/save-career', 'POST', { data: serialized });
            if (result.success) {
                const now = new Date().toLocaleString();
                AuthSystem.currentUser.lastSync = now;
                localStorage.setItem('pdc_auth_user', JSON.stringify(AuthSystem.currentUser));
            }
            return result;
        } catch (e) {
            console.error('Save career error:', e);
            return { success: false, error: e.message };
        }
    },

    loadCareerFromCloud: async () => {
        if (!AuthSystem.currentUser) {
            return { success: false, error: 'Not logged in' };
        }
        try {
            const result = await AuthSystem.apiCall('/load-career', 'GET');
            if (result.success && result.data) {
                return { success: true, data: AuthSystem.deserialize(result.data) };
            }
            return { success: true, data: null };
        } catch (e) {
            console.error('Load career error:', e);
            return { success: false, error: e.message };
        }
    }
};

// ==================== MATCH SIMULATOR ====================
// Simulate a full match between two players
window.MatchSimulator = {
    // Create a player state object for match simulation
    createPlayerState: (player, startScore = 501) => {
        return {
            name: player.name,
            avg: player.avg || 85,
            co: player.co || 35,
            fav: player.fav || 20,
            score: startScore,
            stats: {
                darts: 0,
                points: 0,
                coAtt: 0,
                coHit: 0,
                tons: 0,
                ton40: 0,
                ton80: 0,
                highCO: 0
            }
        };
    },

    // Simulate a single leg
    simulateLeg: (p1State, p2State, startScore, isDecider) => {
        p1State.score = startScore;
        p2State.score = startScore;
        p1State.stats.darts = 0;
        p2State.stats.darts = 0;
        
        let turn = 0; // 0 = p1, 1 = p2
        let maxDarts = 100; // Safety limit
        
        while (maxDarts-- > 0) {
            const current = turn === 0 ? p1State : p2State;
            const startScore = current.score;
            
            const result = Logic.processVisit(current, isDecider);
            current.score -= result.points;
            current.stats.darts += result.dartsThrown;
            current.stats.points += result.points;
            
            // Track 180s, ton40, ton80
            if (result.points === 180) current.stats.tons++;
            else if (result.points >= 140) current.stats.ton40++;
            else if (result.points >= 100) current.stats.ton80++;
            
            // Track high checkout
            if (result.endLeg && startScore > current.stats.highCO) {
                current.stats.highCO = startScore;
            }
            
            if (result.endLeg) {
                return turn === 0 ? 'p1' : 'p2';
            }
            
            turn = 1 - turn;
        }
        
        return 'draw'; // Should never happen
    },

    // Simulate a full match (best of X legs or first to Y sets)
    simulateMatch: (player1, player2, format = { legs: 6, sets: 0 }) => {
        const p1State = MatchSimulator.createPlayerState(player1);
        const p2State = MatchSimulator.createPlayerState(player2);
        
        let p1Legs = 0;
        let p2Legs = 0;
        let p1Sets = 0;
        let p2Sets = 0;
        
        const targetLegs = format.sets > 0 ? 3 : Math.ceil(format.legs / 2);
        const targetSets = format.sets > 0 ? Math.ceil(format.sets / 2) : 0;
        
        let firstThrow = 0; // Alternates each leg
        
        while (true) {
            // Check if match is over
            if (format.sets > 0) {
                if (p1Sets >= targetSets || p2Sets >= targetSets) break;
            } else {
                if (p1Legs >= targetLegs || p2Legs >= targetLegs) break;
            }
            
            const isDecider = format.sets > 0 
                ? (p1Sets === targetSets - 1 && p2Sets === targetSets - 1 && p1Legs === targetLegs - 1 && p2Legs === targetLegs - 1)
                : (p1Legs === targetLegs - 1 && p2Legs === targetLegs - 1);
            
            // Simulate leg with correct throw order
            const result = firstThrow === 0 
                ? MatchSimulator.simulateLeg(p1State, p2State, 501, isDecider)
                : MatchSimulator.simulateLeg(p2State, p1State, 501, isDecider);
            
            // Adjust result if p2 threw first
            const winner = firstThrow === 0 ? result : (result === 'p1' ? 'p2' : 'p1');
            
            if (winner === 'p1') p1Legs++;
            else p2Legs++;
            
            // Check for set win
            if (format.sets > 0 && (p1Legs >= targetLegs || p2Legs >= targetLegs)) {
                if (p1Legs > p2Legs) p1Sets++;
                else p2Sets++;
                p1Legs = 0;
                p2Legs = 0;
            }
            
            firstThrow = 1 - firstThrow;
        }
        
        // Calculate averages
        const p1Avg = p1State.stats.darts > 0 ? (p1State.stats.points / p1State.stats.darts) * 3 : 0;
        const p2Avg = p2State.stats.darts > 0 ? (p2State.stats.points / p2State.stats.darts) * 3 : 0;
        
        return {
            winner: format.sets > 0 ? (p1Sets > p2Sets ? player1 : player2) : (p1Legs > p2Legs ? player1 : player2),
            p1Score: format.sets > 0 ? p1Sets : p1Legs,
            p2Score: format.sets > 0 ? p2Sets : p2Legs,
            p1Stats: {
                ...p1State.stats,
                avg: p1Avg,
                co: p1State.stats.coAtt > 0 ? (p1State.stats.coHit / p1State.stats.coAtt * 100) : 0
            },
            p2Stats: {
                ...p2State.stats,
                avg: p2Avg,
                co: p2State.stats.coAtt > 0 ? (p2State.stats.coHit / p2State.stats.coAtt * 100) : 0
            }
        };
    }
};

// ==================== TOURNAMENT SIMULATOR ====================
window.TournamentSimulator = {
    // Simulate a knockout tournament
    simulateKnockout: (players, format = { legs: 6, sets: 0 }) => {
        let bracket = [...players];
        const results = [];
        
        while (bracket.length > 1) {
            const round = [];
            const nextRound = [];
            
            for (let i = 0; i < bracket.length; i += 2) {
                const p1 = bracket[i];
                const p2 = bracket[i + 1] || { name: 'BYE', avg: 0 };
                
                if (p2.name === 'BYE') {
                    nextRound.push(p1);
                    round.push({ p1, p2: null, winner: p1 });
                } else {
                    const match = MatchSimulator.simulateMatch(p1, p2, format);
                    nextRound.push(match.winner);
                    round.push({
                        p1, p2,
                        winner: match.winner,
                        score: `${match.p1Score}-${match.p2Score}`,
                        p1Stats: match.p1Stats,
                        p2Stats: match.p2Stats
                    });
                }
            }
            
            results.push(round);
            bracket = nextRound;
        }
        
        return {
            winner: bracket[0],
            rounds: results
        };
    }
};

console.log('[Shared] PDC Darts shared module loaded');
