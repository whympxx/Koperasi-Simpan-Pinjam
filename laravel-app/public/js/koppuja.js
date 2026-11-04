/* KOPPUJA — interaksi kecil: format uang, tilt 3D, kartu anggota, animasi masuk */
(function () {
    'use strict';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Format input uang: 1500000 -> 1.500.000
    document.querySelectorAll('input[data-money]').forEach(function (el) {
        const fmt = function () {
            const digits = el.value.replace(/[^0-9]/g, '');
            el.value = digits ? Number(digits).toLocaleString('id-ID') : '';
        };
        el.addEventListener('input', fmt);
        fmt();
    });

    // Animasi masuk bertahap untuk kartu konten
    document.querySelectorAll('.content > .row > [class*="col"], .content > .card, .content > .hero, .content > .alert, .content > form.card')
        .forEach(function (el, i) {
            el.classList.add('reveal');
            el.style.animationDelay = Math.min(i * 45, 500) + 'ms';
        });

    // Tilt 3D mengikuti kursor
    if (!reduceMotion) {
        document.querySelectorAll('[data-tilt]').forEach(function (el) {
            const max = parseFloat(el.dataset.tilt) || 8;
            el.addEventListener('mousemove', function (e) {
                const r = el.getBoundingClientRect();
                const x = (e.clientX - r.left) / r.width - 0.5;
                const y = (e.clientY - r.top) / r.height - 0.5;
                el.style.transform = 'perspective(900px) rotateX(' + (-y * max) + 'deg) rotateY(' + (x * max) + 'deg) translateZ(4px)';
                el.style.setProperty('--shine', (x * 120) + '%');
            });
            el.addEventListener('mouseleave', function () {
                el.style.transform = '';
                el.style.setProperty('--shine', '-60%');
            });
        });
    }

    // Kartu anggota: klik untuk membalik
    document.querySelectorAll('.member-card-scene').forEach(function (scene) {
        scene.addEventListener('click', function () { scene.classList.toggle('flipped'); });
        scene.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); scene.classList.toggle('flipped'); }
        });
    });

    // Sidebar mobile
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () { sidebar.classList.toggle('show'); });
        document.addEventListener('click', function (e) {
            if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && !toggle.contains(e.target)) sidebar.classList.remove('show');
        });
    }

    // Angka statistik bergerak naik
    if (!reduceMotion) {
        document.querySelectorAll('[data-count]').forEach(function (el) {
            const target = parseFloat(el.dataset.count);
            const prefix = el.dataset.prefix || '';
            if (!isFinite(target) || target <= 0) return;
            const start = performance.now(), dur = 900;
            (function step(now) {
                const p = Math.min(1, (now - start) / dur), eased = 1 - Math.pow(1 - p, 3);
                el.textContent = prefix + Math.round(target * eased).toLocaleString('id-ID');
                if (p < 1) requestAnimationFrame(step);
            })(start);
        });
    }
})();
