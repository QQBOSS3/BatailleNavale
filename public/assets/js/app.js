// app.js — Interactions globales de la page d'accueil (avatar, options, amis, gamemode)

/* === AVATAR === */
function openAvatarMenu() {
  document.getElementById("avatar-menu").classList.remove("hidden");
  document.getElementById("overlay").classList.remove("hidden");
}

function closeAvatarMenu() {
  document.getElementById("avatar-menu").classList.add("hidden");
  document.getElementById("overlay").classList.add("hidden");
}

function changeAvatar(id) {
  var prefix = (typeof avatarSkinPrefix !== 'undefined' && avatarSkinPrefix) ? avatarSkinPrefix : null;
  if (prefix) {
    document.getElementById("current-avatar").src = "assets/img/Avatar/" + id + prefix + ".png";
  } else {
    document.getElementById("current-avatar").src = "get_avatar.php?id=" + id;
  }
  fetch("update_avatar.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "avatar_id=" + encodeURIComponent(id)
    })
    .then(res => res.text())
    .then(msg => console.log(msg));
  closeAvatarMenu();
}

/* === OPTIONS === */
function toggleOptionsMenu() {
  const menu = document.getElementById('options-menu');
  menu.classList.toggle('hidden');
  if (!menu.classList.contains('hidden')) {
    document.addEventListener('click', closeMenuOutside);
  } else {
    document.removeEventListener('click', closeMenuOutside);
  }
}

function closeMenuOutside(e) {
  const menu = document.getElementById('options-menu');
  const btn = document.querySelector('.btn-img-trigger');
  if (!menu.contains(e.target) && !btn.contains(e.target)) {
    menu.classList.add('hidden');
    document.removeEventListener('click', closeMenuOutside);
  }
}

function autoSaveOptions() {
  const form = document.getElementById("options-form");
  const formData = new FormData(form);
  fetch("update_options.php", {
      method: "POST",
      body: formData
    })
    .then(res => res.text())
    .then(msg => console.log(msg))
    .catch(err => console.error("Erreur:", err));
}

document.addEventListener("DOMContentLoaded", () => {
  const optionsForm = document.getElementById("options-form");
  if (optionsForm) {
    optionsForm.querySelectorAll("input, select").forEach(el => {
      el.addEventListener("change", autoSaveOptions);
      el.addEventListener("input", autoSaveOptions);
    });
  }
});

/* === UPDATE POPUP === */
function openUpdatePopup() {
  document.getElementById("update-popup").classList.remove("hidden");
  document.getElementById("update-overlay").classList.remove("hidden");
}

function closeUpdatePopup() {
  document.getElementById("update-popup").classList.add("hidden");
  document.getElementById("update-overlay").classList.add("hidden");
}

/* === FRIENDS MENU === */
function openFriendsMenu() {
  document.getElementById("friends-menu").classList.add("show");
  document.getElementById("friends-overlay").classList.remove("hidden");
}

function closeFriendsMenu() {
  document.getElementById("friends-menu").classList.remove("show");
  document.getElementById("friends-overlay").classList.add("hidden");
}

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("search-friend-form");
  if (form) {
    form.addEventListener("submit", e => {
      e.preventDefault();
      const formData = new FormData(form);
      fetch("search_friend.php", {
          method: "POST",
          body: formData
        })
        .then(res => res.text())
        .then(html => (document.getElementById("search-result").innerHTML = html));
    });
  }
});

function refreshFriendsList() {
  fetch("get_friends.php")
    .then(res => res.text())
    .then(html => {
      const el = document.getElementById("friends-list");
      if (el) el.innerHTML = html;
    })
    .catch(err => console.error("Erreur refresh amis:", err));
}

document.addEventListener("DOMContentLoaded", () => {
  refreshFriendsList();
  setInterval(refreshFriendsList, 5000);
});

/* === RULES POPUP === */
function openRulesPopup() {
  document.getElementById("rules-popup").classList.remove("hidden");
  document.getElementById("rules-overlay").classList.remove("hidden");
}

function closeRulesPopup() {
  document.getElementById("rules-popup").classList.add("hidden");
  document.getElementById("rules-overlay").classList.add("hidden");
}

function showRuleTab(tab) {
  document.querySelectorAll(".tab-btn").forEach(btn => btn.classList.remove("active"));
  document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));
  document.querySelector(`.tab-btn[onclick="showRuleTab('${tab}')"]`).classList.add("active");
  document.getElementById("tab-" + tab).classList.add("active");
}

/* === GAMEMODE MODAL (création/recherche de partie) === */
let selectedMode   = null;  // 'br' | 'solo' | '2vs2' | '3vs3' | '4vs4' | 'private'
let selectedLocale = 'fr';  // 'fr' (espacement obligatoire) | 'be' (collé autorisé)

function openGamemodeModal() {
  document.getElementById("gamemode-modal").classList.remove("hidden");
  document.getElementById("gamemode-overlay").classList.remove("hidden");
  showStep('main');
}

function closeGamemodeModal() {
  document.getElementById("gamemode-modal").classList.add("hidden");
  document.getElementById("gamemode-overlay").classList.add("hidden");
}

function showStep(step) {
  document.querySelectorAll(".gamemode-step").forEach(s => {
    s.classList.add("hidden");
    s.classList.remove("active");
  });
  const el = document.getElementById("step-" + step);
  if (el) { el.classList.remove("hidden"); el.classList.add("active"); }
}

function setLocale(locale) {
  selectedLocale = locale;
  document.querySelectorAll('.gm-locale-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('locale-' + locale).classList.add('active');
}

function selectMode(mode) {
  selectedMode = mode;
  showStep('action');
}

function goToList() {
  window.location.href = 'list_games.php';
}

function doCreate() {
  if (!selectedMode) return;
  let mode = selectedMode === 'solo' ? '1vs1' : selectedMode;
  let type = selectedMode === 'private' ? 'private' : 'public';
  if (selectedMode === 'private') mode = '1vs1';
  window.location.href = `create_game.php?mode=${mode}&locale=${selectedLocale}&type=${type}&size=10`;
}

function doJoin() {
  if (!selectedMode) return;
  const map = {
    br:      [1, 0],
    solo:    [3, 0],
    '2vs2':  [2, 2],
    '3vs3':  [2, 3],
    '4vs4':  [2, 4],
    private: [3, 0]
  };
  const [gameType, teamMode] = map[selectedMode] || [0, 0];
  let url = `list_games.php?game_type=${gameType}`;
  if (teamMode) url += `&team_mode=${teamMode}`;
  window.location.href = url;
}

/* === GAME INVITES === */
function refreshGameInvites() {
  fetch("get_game_invites.php")
    .then(res => res.text())
    .then(html => {
      const container = document.getElementById("invites-container");
      if (container) container.innerHTML = html;
    });
}

document.addEventListener("DOMContentLoaded", () => {
  refreshGameInvites();
  setInterval(refreshGameInvites, 5000);
});

function inviteFriend(gameId, friendId) {
  fetch("invite_to_game.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "game_id=" + encodeURIComponent(gameId) + "&friend_id=" + encodeURIComponent(friendId)
    })
    .then(res => res.text())
    .then(msg => {
      console.log("Invitation:", msg);
      if (typeof navalAlert === 'function') navalAlert("📨 Invitation", msg);
      else console.log(msg);
      refreshFriendsList();
    })
    .catch(err => console.error("Erreur invitation:", err));
}