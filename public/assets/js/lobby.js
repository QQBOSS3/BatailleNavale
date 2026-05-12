// lobby.js — Logique du lobby extraite de game.php
(function() {
    const gameId = GAME_CONFIG.gameId;

    // Rafraîchissement Auto
    setInterval(() => {
        fetch("check_game_status.php?id=" + gameId)
            .then(r => r.json())
            .then(data => {
                if (!data) return;

                if (data.status === 'placement') {
                    window.location.href = "place_ships_view.php?id=" + gameId;
                    return;
                }
                if (data.status === 'in_progress') {
                    window.location.href = "play.php?id=" + gameId;
                    return;
                }

                if (data.players) {
                    const container = document.getElementById('players-container');
                    function getAvatarSrc(p) {
                        return p.avatar_prefix
                            ? 'assets/img/Avatar/' + p.Avatar + p.avatar_prefix + '.png'
                            : 'get_avatar.php?id=' + p.Avatar;
                    }

                    let captain = null;
                    let crew = [];
                    data.players.forEach(p => {
                        if (parseInt(p.id_player) === data.creator_id) captain = p;
                        else crew.push(p);
                    });

                    let html = '';

                    html += '<div class="captain-section"><div class="captain-label">Capitaine</div>';
                    if (captain) {
                        html += `<div class="captain-card">
                            <span class="captain-crown">👑</span>
                            <img src="${getAvatarSrc(captain)}" class="captain-avatar" alt="Avatar">
                            <span class="captain-pseudo">${captain.Pseudo}</span>
                            <span class="captain-rank">Chef de flotte</span>
                        </div>`;
                    }
                    html += '</div>';

                    if (crew.length > 0 || data.players.length < 2) {
                        html += '<div class="crew-divider"><span>Equipage</span></div>';
                        html += '<div class="crew-count">' + crew.length + ' matelot' + (crew.length > 1 ? 's' : '') + ' a bord</div>';
                        html += '<div class="crew-grid">';
                        crew.forEach(c => {
                            html += `<div class="crew-card">
                                <img src="${getAvatarSrc(c)}" class="crew-avatar" alt="Avatar">
                                <span class="crew-pseudo">${c.Pseudo}</span>
                                <span class="crew-rank">Matelot</span>
                            </div>`;
                        });
                        if (data.players.length < 2) {
                            html += `<div class="crew-card ghost">
                                <div class="ghost-circle">?</div>
                                <span class="ghost-text">En attente...</span>
                            </div>`;
                        }
                        html += '</div>';
                    }

                    container.innerHTML = html;
                }
            })
            .catch(() => {});
    }, 2000);
})();

// Fonctions globales appelées depuis le HTML
function launchGame() {
    navalConfirm(
        "⚓ Lancer la mission ?",
        "Tous les capitaines seront envoyés au placement des bateaux.",
        "Lancer", () => {
            fetch("start_game.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "game_id=" + GAME_CONFIG.gameId
            });
        }
    );
}

function openSidebar() { document.getElementById('sidebar').classList.add('open'); loadAllFriends(); }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); }

function loadAllFriends() {
    fetch("get_friends.php?invite_mode=1&game_id=" + GAME_CONFIG.gameId)
        .then(r => r.text()).then(h => document.getElementById('friends-list-content').innerHTML = h);
}

function searchAndInvite() {
    const pseudo = document.getElementById('friendPseudo').value;
    const res = document.getElementById('search-result');
    if(!pseudo) return;
    res.innerHTML = "Recherche...";
    const fd = new FormData(); fd.append('pseudo', pseudo);
    fetch("search_friend.php", { method: "POST", body: fd }).then(r=>r.text()).then(h=>res.innerHTML=h);
}

function inviteFriend(fid) {
    fetch("invite_to_game.php", { method: "POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:"game_id="+GAME_CONFIG.gameId+"&friend_id="+fid })
    .then(r=>r.text()).then(m => navalAlert("📨 Invitation", m));
}

function quitGame() {
    navalConfirm(
        "⚓ Quitter le lobby ?",
        "Vous quitterez cette partie.",
        "Quitter", () => {
            fetch("leave_game.php", { method: "POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:"game_id="+GAME_CONFIG.gameId })
            .then(()=>window.location.href="index.php").catch(()=>window.location.href="index.php");
        }, "danger"
    );
}

// Modales navales
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
