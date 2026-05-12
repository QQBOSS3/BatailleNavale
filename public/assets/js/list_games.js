// list_games.js — Animation sonar radar extraite de list_games.php
(function() {
    const SWEEP_DURATION = 6000;
    const sweepStart = performance.now();
    const GOLDEN_ANGLE = 137.508;

    function positionBlips() {
        const blips = [...document.querySelectorAll('.game-blip')];
        if (!blips.length) return;

        blips.sort((a, b) => parseInt(a.dataset.id) - parseInt(b.dataset.id));

        blips.forEach((blip, i) => {
            const id = parseInt(blip.dataset.id);
            const angle = (i * GOLDEN_ANGLE + (id % 30)) % 360;
            const radius = 16 + (i % 4) * 7 + (id % 7) * 2;
            const rad = angle * Math.PI / 180;
            const x = 50 + radius * Math.cos(rad);
            const y = 50 + radius * Math.sin(rad);

            blip.style.left = x + '%';
            blip.style.top  = y + '%';
            blip.dataset.angle = angle.toFixed(1);

            const card = blip.querySelector('.blip-card');
            if (card) {
                card.classList.toggle('below', y < 30);
            }
        });

        const counter = document.getElementById('radar-count');
        const n = blips.length;
        if (counter) counter.textContent = n + ' signal' + (n > 1 ? 's' : '');
    }

    function animateBlips(now) {
        const elapsed = (now - sweepStart) % SWEEP_DURATION;
        const sweepAngle = (elapsed / SWEEP_DURATION) * 360;

        document.querySelectorAll('.game-blip').forEach(blip => {
            const blipAngle = parseFloat(blip.dataset.angle || 0);
            let diff = (sweepAngle - blipAngle + 360) % 360;

            const dot = blip.querySelector('.blip-dot');
            const tag = blip.querySelector('.blip-tag');

            if (diff < 6) {
                if (!blip.classList.contains('pinged')) {
                    blip.classList.add('pinged');
                    const ripple = document.createElement('div');
                    ripple.className = 'blip-ping';
                    blip.appendChild(ripple);
                    setTimeout(() => ripple.remove(), 1500);
                }
            } else if (diff > 12) {
                blip.classList.remove('pinged');
            }

            let opacity;
            if (diff < 12) {
                opacity = 1;
            } else if (diff < 300) {
                opacity = 0.85 - (diff - 12) / 300 * 0.7;
            } else {
                opacity = 0.15;
            }

            if (dot) dot.style.opacity = opacity;
            if (tag) tag.style.opacity = Math.max(0.1, opacity - 0.15);
        });

        requestAnimationFrame(animateBlips);
    }

    function bindBlipClicks() {
        document.querySelectorAll('.game-blip').forEach(blip => {
            blip.addEventListener('click', (e) => {
                e.stopPropagation();
                const wasActive = blip.classList.contains('active');
                document.querySelectorAll('.game-blip.active').forEach(b => b.classList.remove('active'));
                if (!wasActive) blip.classList.add('active');
            });
        });
    }

    document.addEventListener('click', () => {
        document.querySelectorAll('.game-blip.active').forEach(b => b.classList.remove('active'));
    });

    positionBlips();
    bindBlipClicks();
    requestAnimationFrame(animateBlips);

    // Auto-refresh
    setInterval(() => {
        const url = new URL(window.location.href);
        url.searchParams.set('ajax', '1');
        fetch(url)
            .then(r => r.text())
            .then(html => {
                const c = document.getElementById('blips-container');
                if (c) {
                    c.innerHTML = html;
                    positionBlips();
                    bindBlipClicks();
                }
            })
            .catch(() => {});
    }, 5000);
})();
