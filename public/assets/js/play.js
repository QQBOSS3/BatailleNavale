// play.js — Logique de jeu extraite de play.php
(function(){
    // ---- Config injectée depuis PHP via GAME_CONFIG ----
    const gameId        = GAME_CONFIG.gameId;
    const myId          = GAME_CONFIG.myId;
    const GRID_SIZE     = GAME_CONFIG.gridSize;
    const opponents     = GAME_CONFIG.opponents;
    const totalLife     = GAME_CONFIG.totalLife;
    const myPseudo      = GAME_CONFIG.myPseudo;
    const TURN_DURATION = GAME_CONFIG.turnDuration;

    // ---- Palette & noms joueurs ----
    const palette      = ['#00bcd4','#ff9800','#e91e63','#4caf50','#9c27b0','#03a9f4','#cddc39','#f44336'];
    const playerNames  = {};
    const playerColors = {};
    playerNames[myId]  = myPseudo;
    playerColors[myId] = palette[0];
    opponents.forEach((opp, i) => {
        playerNames[opp.id_player]  = opp.Pseudo;
        playerColors[opp.id_player] = palette[(i + 1) % palette.length];
    });

    // ---- Journal de combat ----
    const logBody = document.getElementById('combat-log-body');
    const logSeen = new Set();

    function addLog(html, type) {
        const now = new Date();
        const time = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
        const entry = document.createElement('div');
        entry.className = 'log-entry';
        entry.innerHTML = `<span class="log-time">${time}</span>${html}`;
        logBody.appendChild(entry);
        logBody.scrollTop = logBody.scrollHeight;
    }

    function pName(id) {
        const name = playerNames[id] || ('Joueur ' + id);
        const color = playerColors[id] || '#b0b8c8';
        return `<span style="color:${color};font-weight:bold">${name}</span>`;
    }

    function logShot(shooterId, targetId, x, y, result) {
        const key = `${shooterId}-${targetId}-${x}-${y}-${result}`;
        if (logSeen.has(key)) return;
        logSeen.add(key);
        const coord = String.fromCharCode(65 + x) + (y + 1);
        if (result === 'hit') {
            addLog(`${pName(shooterId)} touche ${pName(targetId)} en <b>${coord}</b> — <span class="log-hit">Touche !</span>`);
        } else if (result === 'miss') {
            addLog(`${pName(shooterId)} tire sur ${pName(targetId)} en ${coord} — <span class="log-miss">Manque</span>`);
        }
    }

    function logSunk(shooterId, targetId, shipSize) {
        const key = `sunk-${targetId}-${shipSize}-${logSeen.size}`;
        if (logSeen.has(key)) return;
        logSeen.add(key);
        addLog(`${pName(shooterId)} <span class="log-sunk">Coule !</span> Un navire de ${pName(targetId)} (${shipSize} cases) a sombre`);
    }

    function logDead(playerId) {
        const key = `dead-${playerId}`;
        if (logSeen.has(key)) return;
        logSeen.add(key);
        addLog(`${pName(playerId)} <span class="log-dead">Elimine !</span> Toute sa flotte est detruite.`);
    }

    // Toggle log
    document.getElementById('btn-toggle-log').addEventListener('click', () => {
        const log = document.getElementById('combat-log');
        log.classList.toggle('minimized');
        document.getElementById('btn-toggle-log').textContent = log.classList.contains('minimized') ? '+' : '−';
    });

    // ---- Variables d'état ----
    let countdown   = GAME_CONFIG.timeLeft;
    let timer       = null;
    let shotFiredAt = {};
    let stopAll     = false;
    let currentHp   = totalLife;

    const timerEl   = document.getElementById("timer");
    const statusEl  = document.getElementById("status");
    const hpEl      = document.getElementById("hp-count");
    const minimap   = document.getElementById("minimap");

    // Boutons minimap : toggle visibilité + zoom
    document.getElementById("btn-toggle").addEventListener("click", (e) => {
        e.stopPropagation();
        minimap.classList.toggle("hidden-map");
    });
    minimap.addEventListener("click", () => {
        if (minimap.classList.contains("hidden-map")) minimap.classList.remove("hidden-map");
    });
    document.getElementById("btn-zoom").addEventListener("click", () => {
        const grid = document.getElementById("minimap-grid");
        grid.classList.toggle("zoomed");
        const btn = document.getElementById("btn-zoom");
        btn.classList.toggle("active");
    });

    // Bouton quitter → modal naval
    document.getElementById("btn-quit").addEventListener("click", () => {
        navalConfirm(
            "⚓ Abandonner la mission ?",
            "Votre flotte sera sabordée. Cette action compte comme une défaite.",
            "Abandonner",
            () => {
                fetch("quit_game.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ game_id: gameId }),
                    credentials: "include"
                })
                .then(r => r.json())
                .then(data => { if (data.success) endGame(data.recap || null); })
                .catch(console.error);
            },
            "danger"
        );
    });

    // ---- Génération des grilles ennemies ----
    opponents.forEach(opp => {
        const grid = document.getElementById("grid-" + opp.id_player);
        if (!grid) return;
        for (let y = 0; y < GRID_SIZE; y++) {
            for (let x = 0; x < GRID_SIZE; x++) {
                let c = document.createElement("div");
                c.className        = "cell";
                c.dataset.x        = x;
                c.dataset.y        = y;
                c.dataset.targetId = opp.id_player;
                if (!opp.is_ally && opp.player_status !== 'dead') {
                    c.addEventListener("click", () => onShoot(x, y, c, opp.id_player));
                }
                grid.appendChild(c);
            }
        }
    });

    // ---- Ripple ----
    function triggerRipple(cell, type) {
        if (type === 'miss') triggerCannonball(cell, false);
        const ripple = document.createElement("div");
        ripple.className = "ripple " + (type === 'hit' ? 'fire' : 'water');
        cell.appendChild(ripple);
        setTimeout(() => ripple.remove(), 1000);
    }

    // ---- Boulet de canon ----
    function triggerCannonball(cell, isHit) {
        const ball = document.createElement('div');
        ball.className = 'cannonball';
        cell.appendChild(ball);
        setTimeout(() => {
            ball.remove();
            const impact = document.createElement('div');
            impact.className = 'cannon-impact ' + (isHit ? 'fire' : 'water');
            cell.appendChild(impact);
            setTimeout(() => impact.remove(), 450);
        }, 420);
    }

    // ---- Explosion particules (hit) ----
    function triggerHitExplosion(cell) {
        triggerCannonball(cell, true);
        setTimeout(() => {
            cell.classList.add('cell-flash');
            setTimeout(() => cell.classList.remove('cell-flash'), 300);
            const colors = ['#ff6030','#ffaa30','#ffe060','#ff4020'];
            for (let i = 0; i < 8; i++) {
                const p = document.createElement('div');
                p.className = 'explosion-particle';
                const angle = (Math.PI * 2 / 8) * i + (Math.random() - 0.5) * 0.5;
                const dist = 15 + Math.random() * 20;
                const size = 3 + Math.random() * 4;
                p.style.width = size + 'px';
                p.style.height = size + 'px';
                p.style.background = colors[Math.floor(Math.random() * colors.length)];
                p.style.top = '50%';
                p.style.left = '50%';
                p.style.setProperty('--tx', Math.cos(angle) * dist + 'px');
                p.style.setProperty('--ty', Math.sin(angle) * dist + 'px');
                p.style.animation = `explodeParticle ${0.3 + Math.random() * 0.3}s ease-out forwards`;
                cell.appendChild(p);
                setTimeout(() => p.remove(), 700);
            }
            playHitSound();
        }, 400);
    }

    // ---- Grosse explosion (sunk) ----
    function triggerSunkExplosion(cells) {
        cells.forEach((cell, idx) => {
            setTimeout(() => {
                cell.classList.add('cell-flash');
                setTimeout(() => cell.classList.remove('cell-flash'), 300);

                const boom = document.createElement('div');
                boom.className = 'sunk-boom';
                cell.appendChild(boom);
                setTimeout(() => boom.remove(), 700);

                const colors = ['#ff4020','#ff8020','#ffe050','#fff','#ff6030'];
                for (let i = 0; i < 12; i++) {
                    const p = document.createElement('div');
                    p.className = 'explosion-particle';
                    const angle = (Math.PI * 2 / 12) * i + (Math.random() - 0.5);
                    const dist = 20 + Math.random() * 35;
                    const size = 3 + Math.random() * 5;
                    p.style.width = size + 'px';
                    p.style.height = size + 'px';
                    p.style.background = colors[Math.floor(Math.random() * colors.length)];
                    p.style.top = '50%';
                    p.style.left = '50%';
                    p.style.setProperty('--tx', Math.cos(angle) * dist + 'px');
                    p.style.setProperty('--ty', Math.sin(angle) * dist + 'px');
                    p.style.animation = `explodeParticle ${0.4 + Math.random() * 0.4}s ease-out forwards`;
                    cell.appendChild(p);
                    setTimeout(() => p.remove(), 900);
                }
            }, idx * 80);
        });

        const wave = document.createElement('div');
        wave.className = 'sunk-shockwave';
        cells[0].appendChild(wave);
        setTimeout(() => wave.remove(), 800);

        const grid = cells[0].closest('.enemy-grid');
        if (grid) {
            grid.classList.add('grid-shake');
            setTimeout(() => grid.classList.remove('grid-shake'), 500);
        }

        playSunkSound();
    }

    // ---- Sons d'impact ----
    function playHitSound() {
        if (!audioCtx) return;
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const now = audioCtx.currentTime;
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.type = 'square';
        osc.frequency.value = 300;
        osc.frequency.exponentialRampToValueAtTime(80, now + 0.15);
        gain.gain.setValueAtTime(0.35, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.2);
        osc.start(now);
        osc.stop(now + 0.2);
    }

    function playSunkSound() {
        if (!audioCtx) return;
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const now = audioCtx.currentTime;
        const osc1 = audioCtx.createOscillator();
        const g1 = audioCtx.createGain();
        osc1.connect(g1); g1.connect(audioCtx.destination);
        osc1.type = 'sawtooth';
        osc1.frequency.value = 200;
        osc1.frequency.exponentialRampToValueAtTime(40, now + 0.5);
        g1.gain.setValueAtTime(0.5, now);
        g1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
        osc1.start(now);
        osc1.stop(now + 0.6);
        const osc2 = audioCtx.createOscillator();
        const g2 = audioCtx.createGain();
        osc2.connect(g2); g2.connect(audioCtx.destination);
        osc2.type = 'square';
        osc2.frequency.value = 600;
        osc2.frequency.exponentialRampToValueAtTime(100, now + 0.3);
        g2.gain.setValueAtTime(0.3, now);
        g2.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
        osc2.start(now);
        osc2.stop(now + 0.35);
        const osc3 = audioCtx.createOscillator();
        const g3 = audioCtx.createGain();
        osc3.connect(g3); g3.connect(audioCtx.destination);
        osc3.type = 'sine';
        osc3.frequency.value = 60;
        g3.gain.setValueAtTime(0.4, now + 0.1);
        g3.gain.exponentialRampToValueAtTime(0.001, now + 0.8);
        osc3.start(now + 0.1);
        osc3.stop(now + 0.8);
    }

    // ---- Flash container ciblé ----
    function flashTargeted(targetId) {
        const container = document.getElementById("container-" + targetId);
        if (!container) return;
        container.classList.add("targeted");
        setTimeout(() => container.classList.remove("targeted"), 1000);
    }

    // ---- Fin de partie ----
    function endGame(recap) {
        stopAll = true;
        clearInterval(timer);
        if (heartbeatInterval) { clearInterval(heartbeatInterval); heartbeatInterval = null; }
        minimap.classList.remove('stress-1', 'stress-2', 'stress-3');
        document.getElementById('stress-vignette').classList.remove('active', 'critical');

        if (!recap) {
            const es = document.getElementById("end-screen");
            const em = document.getElementById("end-msg");
            em.textContent = "FIN DE MISSION";
            em.style.color = "var(--brass-light)";
            em.style.transform = "scale(1)"; em.style.opacity = "1";
            es.style.opacity = "1"; es.style.pointerEvents = "all";
            const btn = document.getElementById("btn-return");
            btn.style.opacity = "1"; btn.style.transform = "translateY(0)";
            return;
        }

        const endScreen = document.getElementById("end-screen");
        const endMsg    = document.getElementById("end-msg");

        if (recap.is_winner) {
            endMsg.textContent = "VICTOIRE !";
            endMsg.style.color = "#4caf50";
            endMsg.style.textShadow = "0 0 30px rgba(76,175,80,0.5)";
            document.getElementById("recap-result").textContent = "Victoire";
            document.getElementById("recap-result").style.color = "#4caf50";
        } else {
            endMsg.textContent = "DÉFAITE...";
            endMsg.style.color = "#f44336";
            endMsg.style.textShadow = "0 0 30px rgba(244,67,54,0.5)";
            document.getElementById("recap-result").textContent = "Défaite";
            document.getElementById("recap-result").style.color = "#f44336";
        }

        endScreen.style.opacity = "1";
        endScreen.style.pointerEvents = "all";

        setTimeout(() => {
            endMsg.style.animation = "bannerReveal 0.8s cubic-bezier(0.34,1.56,0.64,1) forwards";
        }, 200);

        const panel = document.getElementById("recap-panel");
        setTimeout(() => {
            panel.style.transition = "opacity 0.6s ease, transform 0.6s ease";
            panel.style.opacity = "1";
            panel.style.transform = "translateY(0)";
        }, 800);

        const rows = panel.querySelectorAll(".recap-row");
        rows.forEach((row, i) => {
            setTimeout(() => {
                row.style.transition = "opacity 0.4s ease, transform 0.4s ease";
                row.style.opacity = "1";
                row.style.transform = "translateX(0)";
            }, 1200 + i * 250);
        });

        setTimeout(() => animateCounter("recap-xp", recap.xp_earned, "+", " XP"), 1450);
        setTimeout(() => animateCounter("recap-gold", recap.gold_earned, "+", " 💰"), 1700);

        if (recap.leveled_up && recap.gold_level_bonus > 0) {
            const bonusRow = document.getElementById("row-level-bonus");
            bonusRow.style.display = "flex";
            setTimeout(() => {
                bonusRow.style.transition = "opacity 0.4s ease, transform 0.4s ease";
                bonusRow.style.opacity = "1";
                bonusRow.style.transform = "translateX(0)";
                animateCounter("recap-level-bonus", recap.gold_level_bonus, "+", " 💰");
            }, 1950);
        }

        const visibleRows = recap.leveled_up ? 4 : 3;
        const barDelay = 1200 + visibleRows * 250 + 400;

        setTimeout(() => {
            const section = document.getElementById("xp-bar-section");
            section.style.transition = "opacity 0.5s ease";
            section.style.opacity = "1";

            document.getElementById("xp-level-label").textContent = "Niveau " + recap.current_level;
            document.getElementById("xp-progress-label").textContent = recap.current_xp + " / " + recap.xp_required + " XP";

            const bar = document.getElementById("xp-bar-fill");

            if (recap.leveled_up) {
                bar.style.transition = "none";
                bar.style.width = recap.start_pct + "%";

                setTimeout(() => {
                    bar.style.transition = "width 1s cubic-bezier(0.4,0,0.2,1)";
                    bar.classList.add("full");
                    bar.style.width = "100%";
                }, 100);

                setTimeout(() => {
                    bar.classList.remove("full");
                    bar.style.transition = "none";
                    bar.style.width = "0%";
                    setTimeout(() => {
                        bar.style.transition = "width 0.8s cubic-bezier(0.4,0,0.2,1)";
                        bar.style.width = recap.end_pct + "%";
                    }, 100);
                }, 1300);

                setTimeout(() => {
                    document.getElementById("level-up-banner").style.animation =
                        "levelUpPop 0.6s cubic-bezier(0.34,1.56,0.64,1) forwards";
                }, 1800);
            } else {
                bar.style.transition = "none";
                bar.style.width = recap.start_pct + "%";
                setTimeout(() => {
                    bar.style.transition = "width 1.2s cubic-bezier(0.4,0,0.2,1)";
                    bar.style.width = recap.end_pct + "%";
                }, 150);
            }
        }, barDelay);

        const btnDelay = barDelay + (recap.leveled_up ? 2200 : 1000);
        setTimeout(() => {
            const btn = document.getElementById("btn-return");
            btn.style.transition = "opacity 0.5s ease, transform 0.5s ease";
            btn.style.opacity = "1";
            btn.style.transform = "translateY(0)";
        }, btnDelay);
    }

    function animateCounter(elId, target, prefix, suffix) {
        const el = document.getElementById(elId);
        let current = 0;
        const step = Math.max(1, Math.floor(target / 20));
        const interval = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(interval); }
            el.textContent = prefix + current + suffix;
        }, 40);
    }

    // ---- Tir ----
    function onShoot(x, y, cell, targetId) {
        if (stopAll || shotFiredAt[targetId]) return;

        if (!audioCtx) {
            try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); }
            catch(e) {}
        }

        shotFiredAt[targetId] = true;
        cell.classList.add("aiming");
        flashTargeted(targetId);
        statusEl.innerText   = "Cible verrouillée. En attente de résolution...";
        statusEl.style.color = "#FFD700";

        fetch("shoot.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `game_id=${gameId}&x=${x}&y=${y}&target_id=${targetId}`,
            credentials: "include"
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                shotFiredAt[targetId] = false;
                cell.classList.remove("aiming");
                statusEl.innerText   = data.error || "Erreur système.";
                statusEl.style.color = "red";
            }
        })
        .catch(err => {
            console.error(err);
            shotFiredAt[targetId] = false;
            cell.classList.remove("aiming");
        });
    }

    // ---- Timer ----
    function startTimer() {
        clearInterval(timer);
        updateTimerDisplay();
        timer = setInterval(() => {
            if (stopAll) { clearInterval(timer); return; }
            countdown--;
            updateTimerDisplay();
            if (countdown <= 0) {
                clearInterval(timer);
                resolveTurn();
            }
        }, 1000);
    }

    function updateTimerDisplay() {
        timerEl.innerText   = countdown + "s";
        timerEl.style.color = countdown > 3 ? "#00bcd4" : "#ff4444";
    }

    // ---- Résolution ----
    function resolveTurn() {
        statusEl.innerText   = "Résolution...";
        statusEl.style.color = "#aaa";

        fetch("resolve_turn.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ game_id: gameId })
        })
        .then(r => r.json())
        .then(data => {
            refreshGrids();
            if (!data.finished) {
                shotFiredAt = {};
                countdown   = TURN_DURATION;
                startTimer();
                statusEl.innerText   = "À vous de jouer !";
                statusEl.style.color = "white";
            }
        })
        .catch(console.error);
    }

    // ---- Mise à jour minimap ----
    function updateMinimap(shotsOnMe) {
        let hits = 0;
        shotsOnMe.forEach(s => {
            if (s.state !== 'resolved') return;
            const mini = document.getElementById(`mini-${s.target_x}-${s.target_y}`);
            if (!mini) return;
            if (s.result === 'sunk') {
                if (!mini.classList.contains('sunk')) {
                    mini.classList.remove('ship', 'hit');
                    mini.classList.add('sunk');
                    minimap.classList.add('under-attack');
                    setTimeout(() => minimap.classList.remove('under-attack'), 1500);
                }
                hits++;
            } else if (s.result === 'hit') {
                if (!mini.classList.contains('hit') && !mini.classList.contains('sunk')) {
                    mini.classList.add('hit');
                    mini.classList.remove('ship');
                    minimap.classList.add('under-attack');
                    setTimeout(() => minimap.classList.remove('under-attack'), 1500);
                }
                hits++;
            } else if (s.result === 'miss') {
                mini.classList.add('miss');
            }
        });
        currentHp = totalLife - hits;
        if (hpEl) {
            hpEl.textContent = currentHp;
            hpEl.style.color = currentHp > totalLife * 0.5 ? '#4caf50'
                             : currentHp > totalLife * 0.25 ? '#ff9800'
                             : '#f44336';
        }
        updateStress(currentHp, totalLife);
    }

    // ---- Système de stress ----
    let currentStressLevel = 0;
    let heartbeatInterval = null;
    let audioCtx = null;

    function getStressLevel(hp, total) {
        if (total === 0) return 0;
        const pct = hp / total;
        if (pct <= 0.25) return 3;
        if (pct <= 0.50) return 2;
        if (pct <= 0.75) return 1;
        return 0;
    }

    function playHeartbeat(bpm) {
        if (!audioCtx) {
            try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); }
            catch(e) { return; }
        }
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const now = audioCtx.currentTime;

        [0, 0.12].forEach((offset, i) => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.type = 'triangle';
            osc.frequency.value = i === 0 ? 250 : 180;
            gain.gain.setValueAtTime(0, now + offset);
            gain.gain.linearRampToValueAtTime(i === 0 ? 0.7 : 0.65, now + offset + 0.03);
            gain.gain.exponentialRampToValueAtTime(0.001, now + offset + 0.2);
            osc.start(now + offset);
            osc.stop(now + offset + 0.25);
        });
    }

    function updateStress(hp, total) {
        const level = getStressLevel(hp, total);
        const pct = total > 0 ? (hp / total) * 100 : 0;
        const vignette = document.getElementById('stress-vignette');
        const hpFill = document.getElementById('minimap-hp-fill');

        if (hpFill) {
            hpFill.style.width = pct + '%';
            hpFill.style.backgroundColor = pct > 75 ? '#4caf50' : pct > 50 ? '#ff9800' : pct > 25 ? '#f44336' : '#d32f2f';
        }

        if (level === currentStressLevel) return;
        currentStressLevel = level;

        minimap.classList.remove('stress-1', 'stress-2', 'stress-3');
        vignette.classList.remove('active', 'critical');
        if (heartbeatInterval) { clearInterval(heartbeatInterval); heartbeatInterval = null; }

        if (level >= 1) minimap.classList.add('stress-' + level);

        if (level === 2) {
            vignette.classList.add('active');
            playHeartbeat();
            heartbeatInterval = setInterval(() => playHeartbeat(), 1200);
        }

        if (level === 3) {
            vignette.classList.add('critical');
            playHeartbeat();
            heartbeatInterval = setInterval(() => playHeartbeat(), 800);
        }
    }

    // ---- Images bateaux coulés ----
    const sunkShipImagesPlaced = new Set();
    const myShipFolder = GAME_CONFIG.activeShipFolder;
    const myShipPrefix = GAME_CONFIG.activeShipPrefix;
    let playerShipSkins = {};

    function getShipImgSrc(size, ori, sunkCountForSize, ownerId) {
        let sz = size;
        if (size === 3) sz = sunkCountForSize === 0 ? '3.1' : '3.2';
        const oriStr = ori === 'H' ? 'horizontal' : 'vertical';
        let folder = null, prefix = null;
        if (ownerId && playerShipSkins[ownerId]) {
            folder = playerShipSkins[ownerId].folder;
            prefix = playerShipSkins[ownerId].prefix;
        } else if (ownerId === undefined || ownerId === null) {
            folder = myShipFolder;
            prefix = myShipPrefix;
        }
        if (folder) {
            return `assets/img/ship/${folder}/${sz}_${oriStr}_${prefix}.png`;
        }
        return `assets/img/ship/defaut/${sz}_${oriStr}.png`;
    }

    function placeSunkShipImage(gridSelector, ship, ownerId) {
        const key = `${gridSelector}-${ship.start[0]}-${ship.start[1]}`;
        if (sunkShipImagesPlaced.has(key)) return;
        sunkShipImagesPlaced.add(key);

        const startCell = document.querySelector(`${gridSelector} .cell[data-x='${ship.start[0]}'][data-y='${ship.start[1]}']`);
        if (!startCell) return;

        let count3 = 0;
        sunkShipImagesPlaced.forEach(k => {
            if (k.startsWith(gridSelector) && k !== key) count3++;
        });
        let sunkCount3 = 0;
        if (ship.size === 3) {
            sunkCount3 = document.querySelectorAll(`${gridSelector} .sunk-ship-img[data-size='3']`).length;
        }

        const img = document.createElement('img');
        img.className = 'sunk-ship-img';
        img.src = getShipImgSrc(ship.size, ship.ori, sunkCount3, ownerId);
        img.dataset.size = ship.size;
        const cellEl = startCell;
        const cellW = cellEl.offsetWidth || 34;
        const cellH = cellEl.offsetHeight || 34;
        const gap = 2;
        if (ship.ori === 'H') {
            img.style.width = (cellW * ship.size + (ship.size - 1) * gap) + 'px';
            img.style.height = cellH + 'px';
        } else {
            img.style.width = cellW + 'px';
            img.style.height = (cellH * ship.size + (ship.size - 1) * gap) + 'px';
        }
        img.style.left = '0'; img.style.top = '0';
        startCell.appendChild(img);
    }

    // ---- Source de vérité : navires coulés côté serveur ----
    function applySunkOverride(data) {
        if (data.sunk_cells) {
            Object.entries(data.sunk_cells).forEach(([targetId, cells]) => {
                const newSunkCells = [];
                cells.forEach(([x, y]) => {
                    const c = document.querySelector(`#grid-${targetId} .cell[data-x='${x}'][data-y='${y}']`);
                    if (c && !c.classList.contains('sunk')) {
                        c.classList.remove('hit', 'miss', 'aiming');
                        c.classList.add('sunk');
                        c.style.pointerEvents = 'none';
                        newSunkCells.push(c);
                    }
                });
                if (newSunkCells.length > 0) {
                    triggerSunkExplosion(newSunkCells);
                    const sunkKey = `sunk-enemy-${targetId}-${newSunkCells.length}`;
                    if (!logSeen.has(sunkKey)) {
                        logSeen.add(sunkKey);
                        addLog(`<span class="log-sunk">Coule !</span> Un navire de ${pName(+targetId)} (${newSunkCells.length} cases) a sombre`);
                    }
                }
            });
        }

        if (data.ship_skins) playerShipSkins = data.ship_skins;

        if (data.sunk_ships) {
            Object.entries(data.sunk_ships).forEach(([targetId, ships]) => {
                ships.forEach(ship => {
                    placeSunkShipImage(`#grid-${targetId}`, ship, targetId);
                });
            });
        }

        if (data.sunk_cells_me) {
            const meNewSunk = [];
            data.sunk_cells_me.forEach(([x, y]) => {
                const mini = document.getElementById(`mini-${x}-${y}`);
                if (mini && !mini.classList.contains('sunk')) {
                    mini.classList.remove('ship', 'hit');
                    mini.classList.add('sunk');
                    meNewSunk.push([x, y]);
                }
            });
            if (meNewSunk.length > 0) {
                const sunkKey = `sunk-me-${meNewSunk.length}-${meNewSunk[0][0]}-${meNewSunk[0][1]}`;
                if (!logSeen.has(sunkKey)) {
                    logSeen.add(sunkKey);
                    addLog(`<span class="log-sunk">Navire perdu !</span> Un de vos navires (${meNewSunk.length} cases) a ete coule`);
                }
            }
        }

        if (data.sunk_ships_me) {
            data.sunk_ships_me.forEach(ship => {
                placeSunkShipImage('#minimap-grid', ship, null);
            });
        }
    }

    // ---- Rafraîchissement ----
    function refreshGrids() {
        if (stopAll) return;

        fetch(`get_shots.php?game_id=${gameId}`, { credentials: "include" })
        .then(r => r.json())
        .then(data => {
            if (data.finished) {
                endGame(data.recap || null);
                return;
            }

            if (data.shots_on_me) {
                updateMinimap(data.shots_on_me);
            }

            if (data.my_shots) {
                data.my_shots.forEach(s => {
                    let c = document.querySelector(`#grid-${s.target_id} .cell[data-x='${s.target_x}'][data-y='${s.target_y}']`);
                    if (!c) return;
                    if (s.state === 'pending') {
                        c.classList.add("aiming");
                        c.style.pointerEvents = "none";
                    } else {
                        c.classList.remove("aiming");
                        c.style.pointerEvents = "none";
                        const isNew = !c.classList.contains("hit") && !c.classList.contains("miss") && !c.classList.contains("sunk");
                        if (s.result === "hit") {
                            if (!c.classList.contains("sunk")) c.classList.add("hit");
                            if (isNew) { triggerRipple(c, 'hit'); triggerHitExplosion(c); }
                            logShot(myId, s.target_id, +s.target_x, +s.target_y, 'hit');
                        } else if (s.result === "miss") {
                            if (!c.classList.contains("sunk") && !c.classList.contains("hit")) c.classList.add("miss");
                            if (isNew) triggerRipple(c, 'miss');
                            logShot(myId, s.target_id, +s.target_x, +s.target_y, 'miss');
                        }
                    }
                });
            }

            if (data.all_shots) {
                data.all_shots.forEach(s => {
                    if (s.state === 'resolved' && s.id_player != myId && (s.result === 'hit' || s.result === 'miss')) {
                        logShot(+s.id_player, +s.target_id, +s.target_x, +s.target_y, s.result);
                    }
                    if (s.target_id == myId) return;
                    let c = document.querySelector(`#grid-${s.target_id} .cell[data-x='${s.target_x}'][data-y='${s.target_y}']`);
                    if (!c) return;
                    if (s.state === 'pending') {
                        c.classList.add("aiming");
                    } else {
                        c.classList.remove("aiming");
                        const isNew = !c.classList.contains("hit") && !c.classList.contains("miss") && !c.classList.contains("sunk");
                        if (s.result === "hit" && !c.classList.contains("hit") && !c.classList.contains("sunk")) {
                            c.classList.add("hit");
                            if (isNew) { triggerRipple(c, 'hit'); triggerHitExplosion(c); }
                        }
                        if (s.result === "miss" && !c.classList.contains("miss") && !c.classList.contains("sunk") && !c.classList.contains("hit")) {
                            c.classList.add("miss");
                            if (isNew) triggerRipple(c, 'miss');
                        }
                    }
                });
            }

            applySunkOverride(data);

            if (data.dead_players) {
                data.dead_players.forEach(pid => {
                    logDead(+pid);
                    const container = document.getElementById("container-" + pid);
                    if (container) container.style.opacity = "0.4";
                    document.querySelectorAll(`#grid-${pid} .cell`).forEach(c => {
                        c.style.pointerEvents = "none";
                    });
                });

                if (data.dead_players.map(Number).includes(myId)) {
                    stopAll = true;
                    clearInterval(timer);
                    timerEl.innerText    = "💀";
                    statusEl.innerText   = "Vous êtes éliminé — Mode spectateur";
                    statusEl.style.color = "#f44336";

                    document.querySelectorAll(".cell").forEach(c => {
                        c.style.pointerEvents = "none";
                        c.style.cursor        = "default";
                    });

                    const spectatorPoll = setInterval(() => {
                        fetch(`get_shots.php?game_id=${gameId}`, { credentials: "include" })
                        .then(r => r.json())
                        .then(data => {
                            if (data.finished) {
                                clearInterval(spectatorPoll);
                                endGame(data.recap || null);
                                return;
                            }
                            if (data.all_shots) {
                                data.all_shots.forEach(s => {
                                    if (s.target_id == myId) return;
                                    let c = document.querySelector(`#grid-${s.target_id} .cell[data-x='${s.target_x}'][data-y='${s.target_y}']`);
                                    if (!c) return;
                                    const isNew = !c.classList.contains("hit") && !c.classList.contains("miss") && !c.classList.contains("sunk");
                                    if (s.result === "hit" && !c.classList.contains("hit") && !c.classList.contains("sunk")) {
                                        c.classList.add("hit");
                                        if (isNew) { triggerRipple(c, 'hit'); triggerHitExplosion(c); }
                                    }
                                    if (s.result === "miss" && !c.classList.contains("miss") && !c.classList.contains("sunk") && !c.classList.contains("hit")) {
                                        c.classList.add("miss");
                                    }
                                });
                            }
                            applySunkOverride(data);
                            if (data.dead_players) {
                                data.dead_players.forEach(pid => {
                                    const container = document.getElementById("container-" + pid);
                                    if (container) container.style.opacity = "0.4";
                                });
                            }
                        })
                        .catch(console.error);
                    }, 3000);
                }
            }
        })
        .catch(console.error);
    }

    // ---- Lancement ----
    addLog(`<span class="log-join">Combat engage !</span> ${Object.values(playerNames).map((n,i) => `<span style="color:${palette[i % palette.length]};font-weight:bold">${n}</span>`).join(' vs ')}`, 'start');
    refreshGrids();
    startTimer();
    setInterval(() => refreshGrids(), 3000);
})();

// ---- Modales navales (globales, utilisées par le HTML) ----
function navalAlert(title, text) {
    document.getElementById('nm-title').textContent = title;
    document.getElementById('nm-title').className = 'nv-title info';
    document.getElementById('nm-text').textContent = text;
    document.getElementById('nm-buttons').innerHTML =
        '<button class="naval-btn naval-btn-ok" onclick="document.getElementById(\'naval-modal\').classList.remove(\'visible\')">OK</button>';
    document.getElementById('naval-modal').classList.add('visible');
}
function navalConfirm(title, text, actionLabel, onConfirm, style) {
    document.getElementById('nm-title').textContent = title;
    document.getElementById('nm-title').className = 'nv-title ' + (style === 'danger' ? 'danger' : '');
    document.getElementById('nm-text').textContent = text;
    const btnClass = style === 'danger' ? 'naval-btn-danger' : 'naval-btn-primary';
    document.getElementById('nm-buttons').innerHTML =
        '<button class="naval-btn naval-btn-cancel" id="nm-cancel">Annuler</button>' +
        '<button class="naval-btn ' + btnClass + '" id="nm-ok">' + actionLabel + '</button>';
    document.getElementById('nm-cancel').onclick = () => document.getElementById('naval-modal').classList.remove('visible');
    document.getElementById('nm-ok').onclick = () => { document.getElementById('naval-modal').classList.remove('visible'); onConfirm(); };
    document.getElementById('naval-modal').classList.add('visible');
}
