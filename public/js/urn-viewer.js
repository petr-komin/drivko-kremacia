/**
 * 3D náhled urny (Three.js, bez buildu – načítá se přes importmap z partials/urn-3d).
 * Vychází z konfigurátoru krabičky na e-shopu, upravené pro urnu:
 *   tělo = dno + 4 stěny, víko = horní deska + zanořená zátka (do objemu se nepočítá).
 * Rozměry v mm, osa Y nahoru, předek urny míří ke kameře (+Z).
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const WOOD_COLORS = { olse: 0xc98a5a, dub: 0x8b6340, buk: 0xc69b6d, jasan: 0xd6b88a, orech: 0x6b4226, smrk: 0xe8d5b0, tresen: 0xa0522d };
const BURN = '#3a1e08';

export class UrnViewer {
    /**
     * @param {HTMLCanvasElement} canvas
     * @param {{wall:number, lidPlug:number, lidTop:number, textRatios:Object}} sizes  v mm; textRatios = velikost řádku → poměr písma
     */
    constructor(canvas, sizes) {
        this.canvas = canvas;
        this.sizes = sizes;
        this.state = {};
        this.lidOpen = false;
        this.textures = new Map();

        this.renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;

        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0xf5ede0);

        this.camera = new THREE.PerspectiveCamera(40, 1, 1, 20000);
        this.controls = new OrbitControls(this.camera, canvas);
        this.controls.enableDamping = true;
        this.controls.enablePan = false;

        this.scene.add(new THREE.AmbientLight(0xffffff, 1.6));
        const key = new THREE.DirectionalLight(0xfff8f0, 2.2);
        key.position.set(500, 900, 700);
        this.scene.add(key);
        const fill = new THREE.DirectionalLight(0xf0f8ff, 0.6);
        fill.position.set(-600, 300, -400);
        this.scene.add(fill);

        this.grid = new THREE.GridHelper(1000, 20, 0xccbbaa, 0xe2d6c4);
        this.scene.add(this.grid);

        this.body = new THREE.Group();
        this.lid = new THREE.Group();
        this.scene.add(this.body, this.lid);

        new ResizeObserver(() => this.resize()).observe(canvas);
        this.resize();

        const loop = () => {
            requestAnimationFrame(loop);
            this.controls.update();
            this.renderer.render(this.scene, this.camera);
        };
        loop();
    }

    resize() {
        const w = this.canvas.clientWidth, h = this.canvas.clientHeight;
        if (!w || !h) return;
        this.renderer.setSize(w, h, false);
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
    }

    toggleLid() {
        this.lidOpen = !this.lidOpen;
        this.placeLid();
        return this.lidOpen;
    }

    /**
     * @param {{innerW:number, innerH:number, woodName?:string, woodPhoto?:string,
     *          withPaw?:boolean, lines?:{text:string,size:string}[], layout?:string}} state  rozměry v mm
     */
    update(state) {
        const prev = this.state;
        this.state = { ...state };
        const resized = prev.innerW !== state.innerW || prev.innerH !== state.innerH;
        this.build();
        if (resized) this.frame();
    }

    // ── Stavba ─────────────────────────────────────────────────────────────

    build() {
        for (const g of [this.body, this.lid]) {
            g.traverse(o => { o.geometry?.dispose(); if (o.material?.map?.userData?.decal) o.material.map.dispose(); });
            g.clear();
        }

        const { innerW: iw, innerH: ih } = this.state;
        if (!(iw > 0 && ih > 0)) return;

        const { wall: t, lidPlug, lidTop } = this.sizes;
        const W = iw + 2 * t;              // vnější šířka = hloubka (čtverec)
        const wallH = t + ih + lidPlug;    // stěny: dno + vnitřek + zátka víka
        const wood = this.woodMaterial();

        // Dno mezi stěnami, stěny kolem (přední/zadní přes celou šířku)
        this.block(this.body, wood, iw, t, iw, 0, t / 2, 0);
        this.block(this.body, this.frontMaterial(W, wallH), W, wallH, t, 0, wallH / 2, W / 2 - t / 2);
        this.block(this.body, wood, W, wallH, t, 0, wallH / 2, -(W / 2 - t / 2));
        this.block(this.body, wood, t, wallH, iw, -(W / 2 - t / 2), wallH / 2, 0);
        this.block(this.body, wood, t, wallH, iw, W / 2 - t / 2, wallH / 2, 0);

        // Víko: zátka zapadá mezi stěny, deska leží na nich
        this.block(this.lid, wood, iw - 0.5, lidPlug, iw - 0.5, 0, -lidPlug / 2, 0);
        this.block(this.lid, wood, W, lidTop, W, 0, lidTop / 2, 0);

        this.wallH = wallH;
        this.placeLid();
    }

    placeLid() {
        if (!this.wallH) return;
        const lift = this.lidOpen ? this.sizes.lidPlug + this.wallH * 0.25 : 0;
        this.lid.position.set(0, this.wallH + lift, 0);
    }

    frame() {
        const { innerW: iw, innerH: ih } = this.state;
        if (!(iw > 0 && ih > 0)) return;
        const W = iw + 2 * this.sizes.wall;
        const H = this.sizes.wall + ih + this.sizes.lidPlug + this.sizes.lidTop;
        // Rámováno i na zvednuté víko (+30 % výšky), aby nevyjelo z plátna
        const d = Math.max(W, H * 1.3) * 2.1;
        this.controls.target.set(0, H * 0.62, 0);
        this.camera.position.set(d * 0.55, H * 0.62 + d * 0.4, d);
        this.controls.minDistance = Math.max(W, H);
        this.controls.maxDistance = d * 3;
        this.grid.scale.setScalar(Math.max(W, H) / 250);
    }

    block(group, material, sx, sy, sz, x, y, z) {
        const geo = new THREE.BoxGeometry(sx, sy, sz);
        const mesh = new THREE.Mesh(geo, material);
        mesh.position.set(x, y, z);
        group.add(mesh);
        const edges = new THREE.LineSegments(new THREE.EdgesGeometry(geo),
            new THREE.LineBasicMaterial({ color: 0x000000, transparent: true, opacity: 0.25 }));
        edges.position.copy(mesh.position);
        group.add(edges);
    }

    // ── Materiály ──────────────────────────────────────────────────────────

    woodColor() {
        const name = (this.state.woodName || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        for (const [k, c] of Object.entries(WOOD_COLORS)) if (name.includes(k)) return c;
        return 0xd4a96a;
    }

    /** Fotka dřeva jako textura (když je), jinak barva podle názvu dřeviny. */
    woodMaterial() {
        const url = this.state.woodPhoto;
        if (!url) return new THREE.MeshLambertMaterial({ color: this.woodColor() });

        if (!this.textures.has(url)) {
            const tex = new THREE.TextureLoader().load(url, () => this.build());
            tex.colorSpace = THREE.SRGBColorSpace;
            this.textures.set(url, tex);
        }
        const tex = this.textures.get(url);
        return new THREE.MeshLambertMaterial(tex.image ? { map: tex } : { color: this.woodColor() });
    }

    /** Přední stěna: dřevo + vypálená tlapka a text podle rozložení. */
    frontMaterial(W, H) {
        if (!this.state.withPaw && !this.lines().length) return this.woodMaterial();

        const scale = 1024 / Math.max(W, H);
        const cw = Math.round(W * scale), ch = Math.round(H * scale);
        const c = Object.assign(document.createElement('canvas'), { width: cw, height: ch });
        const ctx = c.getContext('2d');

        const img = this.textures.get(this.state.woodPhoto)?.image;
        if (img) ctx.drawImage(img, 0, 0, cw, ch);
        else { ctx.fillStyle = '#' + this.woodColor().toString(16).padStart(6, '0'); ctx.fillRect(0, 0, cw, ch); }

        this.drawDecal(ctx, cw, ch);

        const tex = new THREE.CanvasTexture(c);
        tex.colorSpace = THREE.SRGBColorSpace;
        tex.userData.decal = true;
        const plain = this.woodMaterial();
        // BoxGeometry: pořadí stran +x, -x, +y, -y, +z (předek), -z
        return [plain, plain, plain, plain, new THREE.MeshLambertMaterial({ map: tex }), plain];
    }

    drawDecal(ctx, w, h) {
        const { withPaw, layout } = this.state;
        const text = this.lines().length > 0;
        const m = Math.min(w, h) * 0.12; // okraj
        const box = { x: m, y: m, w: w - 2 * m, h: h - 2 * m };

        let pawBox = null, textBox = null;
        if (withPaw && text) {
            const split = (a, b, vertical) => vertical
                ? [{ ...box, h: box.h * a }, { ...box, y: box.y + box.h * a, h: box.h * b }]
                : [{ ...box, w: box.w * a }, { ...box, x: box.x + box.w * a, w: box.w * b }];
            ({
                paw_top:   () => [pawBox, textBox] = split(0.6, 0.4, true),
                text_top:  () => [textBox, pawBox] = split(0.4, 0.6, true),
                paw_left:  () => [pawBox, textBox] = split(0.5, 0.5, false),
                text_left: () => [textBox, pawBox] = split(0.5, 0.5, false),
            }[layout] || (() => [pawBox, textBox] = split(0.6, 0.4, true)))();
        } else if (withPaw) pawBox = box;
        else textBox = box;

        ctx.fillStyle = BURN;
        if (pawBox) this.drawPaw(ctx, pawBox);
        if (textBox) this.drawText(ctx, textBox, this.lines());
    }

    /** Stylizovaná tlapka: polštářek + 4 prsty. */
    drawPaw(ctx, b) {
        const s = Math.min(b.w, b.h) * 0.9, cx = b.x + b.w / 2, cy = b.y + b.h / 2;
        const ellipse = (x, y, rx, ry, rot = 0) => { ctx.beginPath(); ctx.ellipse(cx + x * s, cy + y * s, rx * s, ry * s, rot, 0, Math.PI * 2); ctx.fill(); };
        ellipse(0, 0.17, 0.25, 0.2);
        ellipse(-0.3, -0.1, 0.09, 0.12, -0.35);
        ellipse(-0.11, -0.28, 0.09, 0.12, -0.1);
        ellipse(0.11, -0.28, 0.09, 0.12, 0.1);
        ellipse(0.3, -0.1, 0.09, 0.12, 0.35);
    }

    lines() {
        return (this.state.lines || []).filter(l => (l.text || '').trim()).slice(0, 6);
    }

    /**
     * Řádky na střed oblasti, každý ve své velikosti (poměr vůči střední).
     * Střední písmo má pevnou výšku vůči stěně (aby šlo velikost porovnat
     * i u jediného řádku); zmenší se jen, když by se text nevešel.
     */
    drawText(ctx, b, lines) {
        if (!lines.length) return;
        const ratio = l => this.sizes.textRatios?.[l.size] ?? 1;
        const font = px => `600 ${px}px Georgia, 'Times New Roman', serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        const totalH = lines.reduce((sum, l) => sum + ratio(l) * 1.25, 0);
        let base = Math.min(Math.min(ctx.canvas.width, ctx.canvas.height) * 0.08, b.h / totalH);
        for (const l of lines) {
            ctx.font = font(base * ratio(l));
            const w = ctx.measureText(l.text).width;
            if (w > b.w) base *= b.w / w;
        }

        let y = b.y + b.h / 2 - (base * totalH) / 2;
        for (const l of lines) {
            const lh = base * ratio(l) * 1.25;
            ctx.font = font(base * ratio(l));
            ctx.fillText(l.text, b.x + b.w / 2, y + lh / 2);
            y += lh;
        }
    }
}
