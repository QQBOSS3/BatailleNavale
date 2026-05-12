// place_ships.js — Logique de placement des bateaux extraite de place_ships_view.php
(function() {
    const gameId = GAME_CONFIG.gameId;
    const GRID_SIZE = GAME_CONFIG.gridSize;
    const isAlreadyValidated = GAME_CONFIG.alreadyValidated;
    const IS_FRENCH_RULES = GAME_CONFIG.isFrenchRules;

    const uiPlacement = document.getElementById('placement-ui');
    const uiWaiting = document.getElementById('waiting-ui');
    const waitStatus = document.getElementById('wait-status');

    if (isAlreadyValidated) {
        switchToWaitingMode();
    } else {
        initPlacementLogic();
    }

    function switchToWaitingMode() {
        uiPlacement.style.display = 'none';
        uiWaiting.style.display = 'block';
        setInterval(checkIfGameStarted, 2000);
        checkIfGameStarted();
    }

    function checkIfGameStarted() {
        fetch("check_ready.php?id=" + gameId)
            .then(r => r.json())
            .then(data => {
                if (data.readyCount !== undefined) {
                    waitStatus.innerText = data.readyCount + " / " + data.total + " joueurs prêts";
                }
                if (data.ready === true) {
                    waitStatus.innerText = "🚀 Lancement du combat !";
                    waitStatus.style.color = "#00ff00";
                    setTimeout(() => {
                        window.location.href = "play.php?id=" + gameId;
                    }, 1000);
                }
            })
            .catch(e => console.error(e));
    }

    function initPlacementLogic() {
        // Flotte selon les règles
        const initialFleet = IS_FRENCH_RULES
            ? { 5: 1, 4: 1, 3: 2, 2: 1 }          // FR : 1×5, 1×4, 2×3, 1×2
            : { 4: 1, 3: 2, 2: 3, 1: 4 };          // BE : 1×4, 2×3, 3×2, 4×1
        const fleet = JSON.parse(JSON.stringify(initialFleet));
        const board = Array.from({ length: GRID_SIZE }, () => Array(GRID_SIZE).fill(0));
        const shipIdMap = Array.from({ length: GRID_SIZE }, () => Array(GRID_SIZE).fill(null));

        let placedShips = [], nextShipId = 1, currentSize = 0, orientation = "H";
        let ship3Count = 0;
        let previewImg = null;

        const shipFolder = GAME_CONFIG.activeShipFolder;
        const shipPrefix = GAME_CONFIG.activeShipPrefix;

        function getShipImageName(size, ori) {
            let sz = size;
            if (size === 3) {
                const placed3 = placedShips.filter(s => s.size === 3).length;
                sz = placed3 === 0 ? '3.1' : '3.2';
            }
            const oriStr = ori === 'H' ? 'horizontal' : 'vertical';
            if (shipFolder) {
                return `assets/img/ship/${shipFolder}/${sz}_${oriStr}_${shipPrefix}.png`;
            }
            return `assets/img/ship/defaut/${sz}_${oriStr}.png`;
        }

        function getShipImageNameForPlaced(size, ori, variant) {
            let sz = size;
            if (size === 3) sz = variant;
            const oriStr = ori === 'H' ? 'horizontal' : 'vertical';
            if (shipFolder) {
                return `assets/img/ship/${shipFolder}/${sz}_${oriStr}_${shipPrefix}.png`;
            }
            return `assets/img/ship/defaut/${sz}_${oriStr}.png`;
        }

        const grid = document.getElementById('grid');
        const fleetBox = document.getElementById('fleetBox');
        const validateBtn = document.getElementById('validateBtn');
        const statusLbl = document.getElementById('statusLbl');

        // Création Grille Dynamique
        for (let y = 0; y < GRID_SIZE; y++) {
            for (let x = 0; x < GRID_SIZE; x++) {
                const cell = document.createElement('div');
                cell.className = 'cell';
                cell.dataset.x = x; cell.dataset.y = y;
                cell.addEventListener('mouseenter', () => handlePreview(x, y, true));
                cell.addEventListener('mouseleave', () => handlePreview(x, y, false));
                cell.addEventListener('click', () => onCellClick(x, y));
                grid.appendChild(cell);
            }
        }

        const shipNames = IS_FRENCH_RULES
            ? { 5: 'Porte-avions', 4: 'Croiseur', 3: 'Sous-marin', 2: 'Torpilleur' }
            : { 4: 'Cuirassé', 3: 'Croiseur', 2: 'Torpilleur', 1: 'Vedette' };
        const fleetSizes = Object.keys(initialFleet).map(Number).sort((a,b) => b - a);

        function renderFleet() {
            fleetBox.innerHTML = '';
            fleetSizes.forEach(size => {
                const count = fleet[size] || 0;
                const btn = document.createElement('button');
                btn.className = 'ship-btn' + (count === 0 ? ' disabled' : '') + (currentSize === size ? ' selected' : '');
                btn.innerHTML = `
                    <span>
                        <span class="ship-name">${shipNames[size]}</span>
                        <span class="ship-blocks">${'■'.repeat(size)}</span>
                    </span>
                    <span class="ship-count">x${count}</span>`;
                btn.onclick = () => { if(count>0) { currentSize=size; renderFleet(); } };
                fleetBox.appendChild(btn);
            });
            validateBtn.disabled = !isFleetComplete();
        }
        renderFleet();

        document.getElementById('rotateBtn').onclick = () => { orientation = orientation === 'H' ? 'V' : 'H'; };
        document.getElementById('resetBtn').onclick = () => location.reload();
        document.getElementById('autoPlaceBtn').onclick = () => { randomPlacement(); };
        document.getElementById('quitBtn').onclick = () => {
            navalConfirm("⚓ Quitter ?", "Vous abandonnerez le placement.", "Quitter", () => { window.location.href = "index.php"; }, "danger");
        };
        document.addEventListener('keydown', e => { if(e.key === 'r' || e.key === 'R') orientation = orientation === 'H' ? 'V' : 'H'; });

        validateBtn.onclick = () => {
            validateBtn.disabled = true;
            statusLbl.textContent = "Envoi en cours...";
            fetch("place_ships.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ game_id: gameId, ships: board })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    switchToWaitingMode();
                } else {
                    navalAlert("⚠ Erreur", res.error);
                    validateBtn.disabled = false;
                }
            })
            .catch(err => {
                navalAlert("⚠ Erreur", "Erreur réseau");
                validateBtn.disabled = false;
            });
        };

        function getCell(x,y) { return document.querySelector(`.cell[data-x='${x}'][data-y='${y}']`); }
        function isFleetComplete() { return Object.values(fleet).every(v => v === 0); }

        function handlePreview(x, y, show) {
            document.querySelectorAll('.preview, .invalid').forEach(c => c.classList.remove('preview', 'invalid'));
            if (previewImg) { previewImg.remove(); previewImg = null; }

            if(!show || !currentSize) return;

            const cells = getShipCells(x, y, currentSize, orientation);
            const valid = checkValid(cells);
            cells.forEach(p => {
                const c = getCell(p.x, p.y);
                if(c) {
                    c.classList.add('preview');
                    if(!valid) c.classList.add('invalid');
                }
            });

            const firstCell = getCell(cells[0].x, cells[0].y);
            if (firstCell) {
                previewImg = document.createElement('img');
                previewImg.className = 'ship-img-preview' + (valid ? '' : ' invalid-preview');
                previewImg.src = getShipImageName(currentSize, orientation);
                const cellSize = 40;
                if (orientation === 'H') {
                    previewImg.style.width = (cellSize * currentSize + (currentSize - 1) * 2) + 'px';
                    previewImg.style.height = cellSize + 'px';
                } else {
                    previewImg.style.width = cellSize + 'px';
                    previewImg.style.height = (cellSize * currentSize + (currentSize - 1) * 2) + 'px';
                }
                previewImg.style.left = '0'; previewImg.style.top = '0';
                firstCell.appendChild(previewImg);
            }
        }

        function onCellClick(x,y) {
            if(shipIdMap[y][x]) { removeShip(shipIdMap[y][x]); return; }
            if(!currentSize) return;
            const cells = getShipCells(x,y,currentSize,orientation);
            if(checkValid(cells)) { placeShip(cells, currentSize, orientation); } else {
                document.body.style.filter = "sepia(1) hue-rotate(-50deg) saturate(3)";
                setTimeout(() => document.body.style.filter = "none", 200);
            }
        }

        function getShipCells(x,y,size,ori) {
            let res = [];
            for(let i=0; i<size; i++) res.push({ x: x+(ori==='H'?i:0), y: y+(ori==='V'?i:0) });
            return res;
        }

        function checkValid(cells) {
            const basicCheck = cells.every(p =>
                p.x >= 0 && p.x < GRID_SIZE &&
                p.y >= 0 && p.y < GRID_SIZE &&
                board[p.y][p.x] === 0
            );
            if (!basicCheck) return false;

            if (IS_FRENCH_RULES) {
                const hasNeighbor = cells.some(p => {
                    for (let dx = -1; dx <= 1; dx++) {
                        for (let dy = -1; dy <= 1; dy++) {
                            if (dx === 0 && dy === 0) continue;
                            const nx = p.x + dx;
                            const ny = p.y + dy;
                            if (nx >= 0 && nx < GRID_SIZE && ny >= 0 && ny < GRID_SIZE) {
                                if (board[ny][nx] !== 0) return true;
                            }
                        }
                    }
                    return false;
                });
                if (hasNeighbor) return false;
            }
            return true;
        }

        function placeShip(cells, size, ori) {
            ori = ori || orientation;
            const id = nextShipId++;
            const variant = (size === 3) ? (placedShips.filter(s => s.size === 3).length === 0 ? '3.1' : '3.2') : String(size);
            cells.forEach(p => { board[p.y][p.x]=id; shipIdMap[p.y][p.x]=id; getCell(p.x,p.y).classList.add('ship'); });

            const firstCell = getCell(cells[0].x, cells[0].y);
            const img = document.createElement('img');
            img.className = 'ship-img';
            img.src = getShipImageNameForPlaced(size, ori, variant);
            img.dataset.shipId = id;
            const cellSize = 40;
            if (ori === 'H') {
                img.style.width = (cellSize * size + (size - 1) * 2) + 'px';
                img.style.height = cellSize + 'px';
            } else {
                img.style.width = cellSize + 'px';
                img.style.height = (cellSize * size + (size - 1) * 2) + 'px';
            }
            img.style.left = '0'; img.style.top = '0';
            firstCell.appendChild(img);

            placedShips.push({id, size, cells, ori, variant});
            fleet[size]--; currentSize=0; renderFleet();
        }

        function removeShip(id) {
            const idx = placedShips.findIndex(s=>s.id===id);
            if(idx<0) return;
            const s = placedShips[idx];
            s.cells.forEach(p => { board[p.y][p.x]=0; shipIdMap[p.y][p.x]=null; getCell(p.x,p.y).classList.remove('ship'); });
            const img = document.querySelector(`.ship-img[data-ship-id='${id}']`);
            if (img) img.remove();
            fleet[s.size]++; placedShips.splice(idx,1); renderFleet();
        }

        function randomPlacement() {
            placedShips.forEach(s => {
                s.cells.forEach(p => { board[p.y][p.x]=0; shipIdMap[p.y][p.x]=null; getCell(p.x,p.y).classList.remove('ship'); });
                const img = document.querySelector(`.ship-img[data-ship-id='${s.id}']`);
                if (img) img.remove();
            });
            placedShips = []; nextShipId = 1;
            Object.assign(fleet, JSON.parse(JSON.stringify(initialFleet)));

            const toPlace = [];
            fleetSizes.forEach(size => {
                for (let i = 0; i < initialFleet[size]; i++) toPlace.push(size);
            });

            toPlace.forEach(size => {
                let placed=false;
                for(let i=0; i<500 && !placed; i++) {
                    const x = Math.floor(Math.random()*GRID_SIZE), y = Math.floor(Math.random()*GRID_SIZE), ori = Math.random()>.5?'H':'V';
                    const cells = getShipCells(x,y,size,ori);
                    if(checkValid(cells)) { placeShip(cells, size, ori); placed=true; }
                }
            });
            Object.keys(fleet).forEach(k=>fleet[k]=0); renderFleet();
        }

        function startTimer() {
            let timerVal = 60;
            const timerLbl = document.getElementById('timer');
            const timerInterval = setInterval(() => {
                timerVal--;
                timerLbl.textContent = timerVal;
                if (timerVal > 30) timerLbl.style.color = "#00ff00";
                else if (timerVal > 10) timerLbl.style.color = "#ffaa00";
                else timerLbl.style.color = "#ff0000";
                if (timerVal <= 0) {
                    clearInterval(timerInterval);
                    if (!isFleetComplete()) randomPlacement();
                    validateBtn.click();
                }
            }, 1000);
        }
        startTimer();
    }
})();

// Modales navales (globales)
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
