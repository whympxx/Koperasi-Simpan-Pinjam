/* Scene 3D halaman login: koin emas melayang, globe jaringan anggota, dan partikel. */
(function () {
    'use strict';
    const host = document.getElementById('hero3d');
    if (!host || !window.THREE) return;

    const THREE = window.THREE;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    host.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
    camera.position.set(0, 0, 11);

    // Cahaya
    scene.add(new THREE.AmbientLight(0x8fa8d6, 0.55));
    const key = new THREE.DirectionalLight(0xfff1c8, 1.25);
    key.position.set(5, 6, 8);
    scene.add(key);
    const rim = new THREE.PointLight(0x4d8fe0, 1.2, 30);
    rim.position.set(-6, -3, 4);
    scene.add(rim);

    const world = new THREE.Group();
    world.position.set(2.2, 0.3, 0);
    scene.add(world);

    // Globe jaringan (anggota saling terhubung)
    const globe = new THREE.Mesh(
        new THREE.IcosahedronGeometry(2.4, 1),
        new THREE.MeshBasicMaterial({ color: 0x5b8fd6, wireframe: true, transparent: true, opacity: 0.28 })
    );
    world.add(globe);
    const nodes = new THREE.Points(
        new THREE.IcosahedronGeometry(2.4, 1),
        new THREE.PointsMaterial({ color: 0xdcbc52, size: 0.09 })
    );
    world.add(nodes);
    const core = new THREE.Mesh(
        new THREE.SphereGeometry(1.15, 48, 48),
        new THREE.MeshStandardMaterial({ color: 0x12305a, metalness: 0.6, roughness: 0.35, emissive: 0x0b1f3a, emissiveIntensity: 0.6 })
    );
    world.add(core);

    // Koin emas
    const coinMat = new THREE.MeshStandardMaterial({ color: 0xd4af37, metalness: 0.95, roughness: 0.28, emissive: 0x3a2a00, emissiveIntensity: 0.25 });
    const rimMat = new THREE.MeshStandardMaterial({ color: 0xb8901e, metalness: 1, roughness: 0.35 });
    const coinGeo = new THREE.CylinderGeometry(0.62, 0.62, 0.12, 64);
    const ringGeo = new THREE.TorusGeometry(0.5, 0.035, 12, 64);
    const coins = [];
    const placements = [
        // Diletakkan di sisi kanan agar tidak menutupi teks di kiri panel.
        [-0.6, 2.9, 1.0], [3.4, 2.4, -0.6], [0.2, -3.0, 1.6], [3.3, -2.3, 1.0], [1.6, 3.6, -1.4], [4.3, 0.1, -1.0], [2.0, -3.7, 0.6]
    ];
    placements.forEach(function (p, i) {
        const coin = new THREE.Group();
        const body = new THREE.Mesh(coinGeo, [rimMat, coinMat, coinMat]);
        const ringTop = new THREE.Mesh(ringGeo, rimMat);
        ringTop.rotation.x = Math.PI / 2;
        ringTop.position.y = 0.065;
        const ringBottom = ringTop.clone();
        ringBottom.position.y = -0.065;
        coin.add(body, ringTop, ringBottom);
        coin.position.set(p[0], p[1], p[2]);
        coin.rotation.set(Math.PI / 2 + (i % 2 ? 0.4 : -0.3), i * 0.7, 0.2 * i);
        coin.userData = { base: p[1], speed: 0.5 + (i % 3) * 0.18, phase: i * 1.3, spin: 0.006 + (i % 4) * 0.003 };
        const s = 0.75 + (i % 3) * 0.18;
        coin.scale.set(s, s, s);
        world.add(coin);
        coins.push(coin);
    });

    // Partikel latar
    const count = 380;
    const pos = new Float32Array(count * 3);
    for (let i = 0; i < count; i++) {
        pos[i * 3] = (Math.random() - 0.5) * 26;
        pos[i * 3 + 1] = (Math.random() - 0.5) * 16;
        pos[i * 3 + 2] = (Math.random() - 0.5) * 12 - 3;
    }
    const dustGeo = new THREE.BufferGeometry();
    dustGeo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const dust = new THREE.Points(dustGeo, new THREE.PointsMaterial({ color: 0x9fb6dd, size: 0.035, transparent: true, opacity: 0.7 }));
    scene.add(dust);

    // Ukuran & parallax
    function resize() {
        const w = host.clientWidth, h = host.clientHeight;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        world.position.x = w < 700 ? 0 : 2.2;
        world.scale.setScalar(w < 700 ? 0.75 : 1);
        camera.updateProjectionMatrix();
    }
    window.addEventListener('resize', resize);
    resize();

    const mouse = { x: 0, y: 0 };
    host.parentElement.addEventListener('mousemove', function (e) {
        const r = host.getBoundingClientRect();
        mouse.x = ((e.clientX - r.left) / r.width - 0.5) * 2;
        mouse.y = ((e.clientY - r.top) / r.height - 0.5) * 2;
    });

    let running = true;
    document.addEventListener('visibilitychange', function () { running = !document.hidden; if (running) loop(); });

    const clock = new THREE.Clock();
    function frame() {
        const t = clock.getElapsedTime();
        globe.rotation.y = t * 0.12;
        globe.rotation.x = Math.sin(t * 0.2) * 0.15;
        nodes.rotation.copy(globe.rotation);
        core.rotation.y = -t * 0.2;
        coins.forEach(function (c) {
            c.rotation.z += c.userData.spin;
            c.position.y = c.userData.base + Math.sin(t * c.userData.speed + c.userData.phase) * 0.28;
        });
        dust.rotation.y = t * 0.012;
        world.rotation.y += (mouse.x * 0.35 - world.rotation.y) * 0.04;
        world.rotation.x += (mouse.y * 0.2 - world.rotation.x) * 0.04;
        renderer.render(scene, camera);
    }
    function loop() {
        if (!running) return;
        frame();
        requestAnimationFrame(loop);
    }

    if (reduceMotion) {
        frame();
    } else {
        loop();
    }
})();
