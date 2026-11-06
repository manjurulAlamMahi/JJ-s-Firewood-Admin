<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $replay->giveaway->prize_name ?? 'Draw Replay' }} – JJ’s Firewood Perth</title>

<style>
  *{margin:0;padding:0;box-sizing:border-box}
  :root{
    --orange:#ff6b35;
    --amber:#f7931e;
    --panel:rgba(0,0,0,.36);
    --panelBorder:rgba(255,107,53,.22);
    --text:rgba(255,255,255,.92);
    --muted:rgba(255,255,255,.72);
  }

  body{
    font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    background:#0d0b08;
    color:#fff;
    height:100vh;
    overflow:hidden;
    display:flex;
    flex-direction:column;
  }

  .bg{
    position:fixed; inset:0;
    background:
      radial-gradient(1200px 700px at 50% 25%, rgba(255,107,53,.18), transparent 55%),
      linear-gradient(180deg,#050403,#120b07,#24150c);
    z-index:-3;
  }
  .embers{
    position:fixed; inset:0; z-index:-2;
    pointer-events:none;
    opacity:.55;
    background-image:
      radial-gradient(circle at 12% 80%, rgba(255,107,53,.18) 0 2px, transparent 3px),
      radial-gradient(circle at 35% 72%, rgba(247,147,30,.12) 0 2px, transparent 3px),
      radial-gradient(circle at 60% 82%, rgba(255,184,28,.10) 0 1px, transparent 2px),
      radial-gradient(circle at 84% 74%, rgba(255,107,53,.10) 0 2px, transparent 3px);
    animation:float 6s ease-in-out infinite;
  }
  @keyframes float{50%{transform:translateY(-10px);filter:blur(.4px)}}

  .container{
    flex:1;
    display:flex;
    flex-direction:column;
    padding:20px;
    max-width:1200px;
    margin:0 auto;
    width:100%;
  }

  .header{
    text-align:center;
    margin:10px 0 18px;
  }
  .header h1{
    font-size:clamp(24px,4vw,42px);
    color:var(--orange);
    font-weight:950;
    text-shadow:0 8px 28px rgba(255,107,53,.4);
    margin-bottom:8px;
  }
  .header p{
    opacity:.82;
    margin:0;
  }

  .stage{
    flex:1;
    border-radius:22px;
    background:var(--panel);
    border:2px solid var(--panelBorder);
    box-shadow:0 0 44px rgba(255,107,53,.14), inset 0 0 70px rgba(0,0,0,.62);
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    overflow:hidden;
    margin-top:16px;
  }

  /* ---------------- Wheel ---------------- */
  .wheel-wrap{
    position:relative;
    width:min(560px,92vw);
    aspect-ratio:1;
    display:grid;
    place-items:center;
  }

  .pointer{
    position:absolute;
    top:-12px;
    left:50%;
    transform:translateX(-50%);
    width:58px;
    height:58px;
    z-index:20;
    filter:drop-shadow(0 8px 16px rgba(0,0,0,.6));
  }
  .pointer:before{
    content:"";
    position:absolute;
    left:50%;
    top:6px;
    transform:translateX(-50%);
    width:0;height:0;
    border-left:16px solid transparent;
    border-right:16px solid transparent;
    border-top:30px solid rgba(255,107,53,.98);
  }
  .pointer:after{
    content:"";
    position:absolute;
    left:50%;
    top:36px;
    transform:translateX(-50%);
    width:14px;height:14px;
    border-radius:50%;
    background:rgba(255,255,255,.85);
    box-shadow:0 0 0 6px rgba(255,107,53,.35));
  }
  .pointer.tick{ transform:translateX(-50%) rotate(-10deg) }

  .wheel-shell{
    position:relative;
    width:100%;
    height:100%;
    border-radius:50%;
    padding:18px;
    background:
      radial-gradient(circle at 30% 25%, rgba(255,255,255,.12), transparent 45%),
      radial-gradient(circle at 50% 65%, rgba(0,0,0,.50), rgba(0,0,0,.85));
    box-shadow:0 30px 80px rgba(0,0,0,.7), 0 0 60px rgba(255,107,53,.12);
    border:2px solid rgba(255,255,255,.12);
  }

  .rim{
    position:absolute;
    inset:10px;
    border-radius:50%;
    border:12px solid rgba(255,255,255,.14);
    box-shadow:inset 0 0 22px rgba(0,0,0,.55), 0 0 0 2px rgba(255,107,53,.15);
    pointer-events:none;
  }

  .wheel{
    width:100%;
    height:100%;
    border-radius:50%;
    overflow:hidden;
    transform:rotate(0deg);
    will-change:transform;
  }
  .wheel.spinning{
    transition:transform 14.5s cubic-bezier(.08,.78,.12,1);
  }
  .wheel svg{width:100%;height:100%;display:block}

  .gloss{
    position:absolute;
    inset:0;
    border-radius:50%;
    background:
      radial-gradient(circle at 30% 25%, rgba(255,255,255,.18), transparent 50%),
      radial-gradient(circle at 55% 75%, rgba(0,0,0,.35), transparent 60%);
    pointer-events:none;
    mix-blend-mode:screen;
    opacity:.9;
  }

  .spotlight{
    position:absolute;
    inset:0;
    border-radius:50%;
    pointer-events:none;
    opacity:0;
    transition:opacity .35s ease;
    background:
      conic-gradient(from 270deg,
        rgba(255,255,255,0) 0deg,
        rgba(255,255,255,0) 345deg,
        rgba(255,255,255,.22) 350deg,
        rgba(255,255,255,.32) 355deg,
        rgba(255,255,255,.22) 360deg);
    filter:blur(.2px);
  }
  .spotlight.on{opacity:1}

  .hub{
    position:absolute;
    width:122px;height:122px;
    border-radius:50%;
    background:
      radial-gradient(circle at 35% 30%, rgba(255,255,255,.16), transparent 50%),
      linear-gradient(180deg, rgba(255,107,53,.95), rgba(247,147,30,.95));
    box-shadow:0 18px 40px rgba(0,0,0,.6), inset 0 0 22px rgba(0,0,0,.28);
    border:2px solid rgba(255,255,255,.22);
    display:grid;
    place-items:center;
    inset:50%;
    transform:translate(-50%,-50%);
    font-weight:950;
    text-align:center;
    letter-spacing:1px;
    z-index:5;
  }
  .hub small{display:block;font-weight:800;opacity:.9;letter-spacing:1.2px}

  .bottom-bar{
    position:absolute;
    bottom:18px;
    left:50%;
    transform:translateX(-50%);
    width:min(760px,92%);
    padding:14px 16px;
    border-radius:16px;
    background:rgba(0,0,0,.35);
    border:1px solid rgba(255,255,255,.14);
    backdrop-filter:blur(8px);
    box-shadow:0 18px 50px rgba(0,0,0,.45);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
  }
  .bottom-bar .label{
    color:rgba(255,255,255,.72);
    font-size:13px;
    letter-spacing:.6px;
    text-transform:uppercase;
    white-space:nowrap;
  }
  .bottom-bar .value{
    flex:1;
    color:rgba(255,255,255,.92);
    font-size:15px;
    font-weight:800;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
  .bottom-bar .pill{
    white-space:nowrap;
    padding:10px 14px;
    border-radius:999px;
    background:linear-gradient(135deg, rgba(255,107,53,.95), rgba(247,147,30,.95));
    border:1px solid rgba(255,255,255,.22);
    color:#fff;
    font-weight:950;
    cursor:pointer;
    user-select:none;
    transition:transform .18s ease, filter .18s ease;
  }
  .bottom-bar .pill:hover{transform:translateY(-1px) scale(1.02);filter:brightness(1.05)}
  .bottom-bar .pill:active{transform:translateY(0) scale(.99)}

  /* ---------------- Modal ---------------- */
  .modal{
    position:fixed; inset:0;
    background:rgba(0,0,0,.75);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:1000;
  }
  .modal.show{display:flex}
  .modal-box{
    width:min(640px,92vw);
    background:linear-gradient(135deg, rgba(255,107,53,.985), rgba(247,147,30,.985));
    border-radius:28px;
    padding:48px 34px 30px;
    text-align:center;
    box-shadow:0 30px 90px rgba(0,0,0,.7);
    border:2px solid rgba(255,255,255,.28);
    position:relative;
    overflow:hidden;
    animation:pop .5s ease;
  }
  @keyframes pop{from{transform:scale(.86);opacity:0}to{transform:scale(1);opacity:1}}

  .confetti{
    position:absolute; inset:0;
    width:100%; height:100%;
    pointer-events:none;
  }
  .modal h2{
    font-size:44px;
    font-weight:1000;
    text-shadow:0 12px 28px rgba(0,0,0,.22);
  }
  .modal .sub{
    margin-top:8px;
    font-weight:900;
    letter-spacing:.8px;
    opacity:.95;
  }
  .winner{
    font-size:30px;
    font-weight:1000;
    margin-top:14px;
    text-shadow:0 12px 26px rgba(0,0,0,.18);
  }
  .details{
    margin-top:18px;
    background:rgba(0,0,0,.18);
    border:1px solid rgba(255,255,255,.22);
    border-radius:18px;
    padding:16px 14px;
    text-align:left;
    line-height:1.3;
  }
  .details .row{
    display:flex;
    justify-content:space-between;
    gap:12px;
    padding:9px 8px;
    border-bottom:1px solid rgba(255,255,255,.18);
  }
  .details .row:last-child{border-bottom:none}
  .details .k{opacity:.9;font-weight:900}
  .details .v{opacity:.95;font-weight:950;text-align:right}

  .close{
    margin-top:22px;
    padding:12px 40px;
    border-radius:999px;
    border:2px solid rgba(255,255,255,.8);
    background:rgba(255,255,255,.22);
    color:#fff;
    font-weight:950;
    cursor:pointer;
    transition:transform .18s ease, background .18s ease;
  }
  .close:hover{background:rgba(255,255,255,.32);transform:translateY(-1px) scale(1.02)}
  .close:active{transform:translateY(0) scale(.99)}

  @media (max-width:520px){
    .modal h2{font-size:38px}
    .winner{font-size:26px}
    .hub{width:108px;height:108px}
  }
</style>
</head>

<body>
<div class="bg"></div>
<div class="embers"></div>

<div class="container">
  <div class="header">
    <h1>🔥 {{ $replay->giveaway->prize_name ?? 'GIVEAWAY DRAW' }}</h1>
    <p>Every order puts you in the draw — drawn weekly</p>
  </div>

  <div class="stage">
    <div class="wheel-wrap">
      <div class="pointer" id="pointer"></div>

      <div class="wheel-shell">
        <div class="rim"></div>
        <div class="wheel" id="wheel"></div>
        <div class="spotlight" id="spotlight"></div>
        <div class="gloss"></div>

        <div class="hub">
          JJ’S<br>DRAW
          <small>GIVEAWAY</small>
        </div>
      </div>

      <div class="bottom-bar">
        <div class="label">Status</div>
        <div class="value" id="statusText">Ready to spin</div>
        <div class="pill" id="spinBtn">SPIN</div>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="modal">
  <div class="modal-box">
    <canvas class="confetti" id="confetti"></canvas>
    <h2>🏆 WINNER</h2>
    <div class="sub">JJ’s Firewood Perth Giveaway Draw</div>
    <div class="winner" id="winnerName">—</div>

    <div class="details">
      <div class="row">
        <div class="k">Prize</div>
        <div class="v" id="prizeName">{{ $replay->giveaway->prize_name ?? 'Prize' }}</div>
      </div>
      <div class="row">
        <div class="k">Draw reference</div>
        <div class="v" id="drawRef">#{{ $replay->id ?? '—' }}</div>
      </div>
      <div class="row">
        <div class="k">Draw time</div>
        <div class="v" id="drawTime">—</div>
      </div>
      <div class="row">
        <div class="k">Verification</div>
        <div class="v">Recorded replay</div>
      </div>
    </div>

    <button class="close" onclick="location.reload()">Close</button>
  </div>
</div>

<script>
(() => {
  const replay = @json($replay);
  const animationData = replay.animation_data || {};
  const winner = replay.winner || {};

  const wheelEl = document.getElementById('wheel');
  const pointerEl = document.getElementById('pointer');
  const spotlightEl = document.getElementById('spotlight');
  const statusText = document.getElementById('statusText');
  const spinBtn = document.getElementById('spinBtn');

  const modal = document.getElementById('modal');
  const winnerNameEl = document.getElementById('winnerName');
  const drawTimeEl = document.getElementById('drawTime');
  const confettiCanvas = document.getElementById('confetti');

  const winnerName = (() => {
    if (winner.name) return String(winner.name);
    const fn = winner.first_name ? String(winner.first_name) : '';
    const ln = winner.last_name ? String(winner.last_name) : '';
    const full = (fn + ' ' + ln).trim();
    return full || 'Winner';
  })();

  let entries = animationData.entries || [];
  if (!entries.length && Array.isArray(animationData.data)) entries = animationData.data;

  const SEGMENTS = 48;
  const anglePer = 360 / SEGMENTS;

  const seedStr = String(replay.id || replay.replay_id || 'seed');
  let seed = 0;
  for (let i=0;i<seedStr.length;i++) seed = (seed*31 + seedStr.charCodeAt(i)) >>> 0;
  function rnd(){
    seed ^= seed << 13; seed >>>= 0;
    seed ^= seed >>> 17; seed >>>= 0;
    seed ^= seed << 5; seed >>>= 0;
    return (seed >>> 0) / 4294967296;
  }

  const colors = [
    '#ff6b35', '#f7931e', '#fdb913', '#ffb81c',
    '#c1272d', '#7a1f2b', '#003da5', '#009fdf',
    '#00a651', '#88c540'
  ];

  function degToRad(d){ return d * Math.PI / 180; }

  function norm(s){ return String(s||'').toLowerCase().replace(/\s+/g,' ').trim(); }
  function entryDisplayName(e, fallbackIndex){
    if (!e) return `Entry ${fallbackIndex + 1}`;
    const fn = e.first_name ? String(e.first_name) : '';
    const ln = e.last_name ? String(e.last_name) : '';
    const name = (fn + ' ' + ln).trim();
    if (e.name && String(e.name).trim()) return String(e.name).trim();
    if (name) return name;
    if (e.email && String(e.email).trim()) return String(e.email).trim();
    if (e.phone && String(e.phone).trim()) return String(e.phone).trim();
    return `Entry ${fallbackIndex + 1}`;
  }

  function findWinnerOriginalIndex(){
    const winnerId = winner.id ?? winner.user_id ?? winner.customer_id ?? null;
    if (winnerId != null && entries.length){
      const idx = entries.findIndex(e => e && (e.id == winnerId || e.user_id == winnerId || e.customer_id == winnerId));
      if (idx >= 0) return idx;
    }
    const wn = norm(winnerName);
    if (wn && entries.length){
      const idx = entries.findIndex(e => norm(entryDisplayName(e,0)) === wn);
      if (idx >= 0) return idx;
    }
    return 0;
  }

  const winnerOriginalIndex = findWinnerOriginalIndex();
  const winnerSegment = (entries.length ? (winnerOriginalIndex % SEGMENTS) : Math.floor(rnd()*SEGMENTS));

  function buildWheel(){
    const size = 560;
    const cx = size/2, cy = size/2;
    const r = (size/2) - 6;

    let svg = `<svg viewBox="0 0 ${size} ${size}" aria-label="Prize wheel">`;

    svg += `<defs>
      <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
        <feDropShadow dx="0" dy="6" stdDeviation="8" flood-color="rgba(0,0,0,0.35)"/>
      </filter>
      <pattern id="hatch" patternUnits="userSpaceOnUse" width="10" height="10" patternTransform="rotate(25)">
        <line x1="0" y1="0" x2="0" y2="10" stroke="rgba(255,255,255,0.10)" stroke-width="2"/>
      </pattern>
    </defs>`;

    for (let i=0;i<SEGMENTS;i++){
      const startDeg = (i*anglePer) - 90;
      const endDeg   = ((i+1)*anglePer) - 90;

      const a1 = degToRad(startDeg);
      const a2 = degToRad(endDeg);

      const x1 = cx + r * Math.cos(a1);
      const y1 = cy + r * Math.sin(a1);
      const x2 = cx + r * Math.cos(a2);
      const y2 = cy + r * Math.sin(a2);

      const d = `M ${cx} ${cy} L ${x1} ${y1} A ${r} ${r} 0 0 1 ${x2} ${y2} Z`;
      const base = colors[i % colors.length];

      svg += `<path d="${d}" fill="${base}" stroke="rgba(255,255,255,0.16)" stroke-width="2" filter="url(#shadow)"></path>`;
      svg += `<path d="${d}" fill="url(#hatch)" opacity="0.9"></path>`;

      const midDeg = startDeg + anglePer/2;
      const t = degToRad(midDeg);

      const dotR1 = r*0.72;
      const dotR2 = r*0.58;

      const dx1 = cx + dotR1 * Math.cos(t);
      const dy1 = cy + dotR1 * Math.sin(t);
      const dx2 = cx + dotR2 * Math.cos(t + (rnd()*0.18 - 0.09));
      const dy2 = cy + dotR2 * Math.sin(t + (rnd()*0.18 - 0.09));

      svg += `<circle cx="${dx1.toFixed(2)}" cy="${dy1.toFixed(2)}" r="${(2.0 + rnd()*1.8).toFixed(2)}" fill="rgba(255,255,255,0.25)"></circle>`;
      svg += `<circle cx="${dx2.toFixed(2)}" cy="${dy2.toFixed(2)}" r="${(1.6 + rnd()*1.6).toFixed(2)}" fill="rgba(0,0,0,0.18)"></circle>`;

      if (i % 4 === 0) {
        const label = entries.length ? `ENTRY #${(i+1 + (winnerOriginalIndex||0))}` : `ENTRY #${i+1}`;
        const tx = cx + (r*0.66) * Math.cos(t);
        const ty = cy + (r*0.66) * Math.sin(t);
        const rotate = midDeg + 90;

        svg += `
          <text x="${tx.toFixed(2)}" y="${ty.toFixed(2)}"
            text-anchor="middle" dominant-baseline="middle"
            font-size="12" font-weight="950"
            fill="rgba(255,255,255,0.92)"
            style="paint-order:stroke;stroke:rgba(0,0,0,0.40);stroke-width:4px;"
            transform="rotate(${rotate.toFixed(2)} ${tx.toFixed(2)} ${ty.toFixed(2)})"
          >${label}</text>`;
      }
    }

    svg += `<circle cx="${cx}" cy="${cy}" r="${r*0.36}" fill="rgba(0,0,0,0.26)" stroke="rgba(255,255,255,0.18)" stroke-width="2"></circle>`;
    svg += `</svg>`;

    wheelEl.innerHTML = svg;
  }

  const tickAudio = new Audio("data:audio/wav;base64,UklGRhYAAABXQVZFZm10IBAAAAABAAEAESsAACJWAAACABAAZGF0YQcAAA==");
  tickAudio.volume = 0.35;

  const winAudio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvPXijkIHGm98OKbTA==');
  winAudio.volume = 0.7;

  function playTick(){
    try{ tickAudio.currentTime = 0; tickAudio.play().catch(()=>{}); }catch(e){}
    pointerEl.classList.add('tick');
    setTimeout(()=>pointerEl.classList.remove('tick'), 70);
  }

  function startConfetti(durationMs=2800){
    const ctx = confettiCanvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;

    function resize(){
      confettiCanvas.width = confettiCanvas.clientWidth * dpr;
      confettiCanvas.height = confettiCanvas.clientHeight * dpr;
      ctx.setTransform(dpr,0,0,dpr,0,0);
    }
    resize();
    window.addEventListener('resize', resize, {passive:true});

    const W = confettiCanvas.clientWidth;
    const H = confettiCanvas.clientHeight;

    const pieces = [];
    const count = 200;

    for(let i=0;i<count;i++){
      pieces.push({
        x: Math.random()*W,
        y: -20 - Math.random()*H*0.4,
        w: 6 + Math.random()*8,
        h: 10 + Math.random()*12,
        vx: -2 + Math.random()*4,
        vy: 2.6 + Math.random()*5.8,
        r: Math.random()*Math.PI,
        vr: -0.12 + Math.random()*0.24,
        color: colors[Math.floor(Math.random()*colors.length)],
        alpha: 0.85 + Math.random()*0.15
      });
    }

    let start = performance.now();
    function frame(now){
      const t = now - start;
      ctx.clearRect(0,0,W,H);

      for(const p of pieces){
        p.x += p.vx;
        p.y += p.vy;
        p.r += p.vr;
        p.vy += 0.02;

        ctx.save();
        ctx.globalAlpha = p.alpha;
        ctx.translate(p.x,p.y);
        ctx.rotate(p.r);
        ctx.fillStyle = p.color;
        ctx.fillRect(-p.w/2, -p.h/2, p.w, p.h);
        ctx.restore();

        if(p.x < -60) p.x = W + 60;
        if(p.x > W + 60) p.x = -60;
      }

      if(t < durationMs) requestAnimationFrame(frame);
      else{
        ctx.clearRect(0,0,W,H);
        window.removeEventListener('resize', resize);
      }
    }
    requestAnimationFrame(frame);
  }

  const TOTAL_MS = 14500;
  const REVEAL_PAUSE = 650;

  function computeTargetRotation(){
    const centerAngle = (winnerSegment * anglePer) + (anglePer / 2);
    const extraSpins = 9 + Math.floor(rnd()*4);
    const jitter = (rnd() * (anglePer * 0.16)) - (anglePer * 0.08);
    return (extraSpins * 360) - centerAngle + jitter;
  }

  let hasSpun = false;

  function spin(){
    if(hasSpun) return;
    hasSpun = true;

    statusText.textContent = "Spinning… selecting winner";
    spinBtn.textContent = "SPINNING…";
    spinBtn.style.pointerEvents = "none";
    spinBtn.style.opacity = "0.85";

    const targetRotation = computeTargetRotation();

    wheelEl.classList.add('spinning');
    wheelEl.style.transform = `rotate(0deg)`;
    void wheelEl.offsetWidth;
    wheelEl.style.transform = `rotate(${targetRotation}deg)`;

    const boundaries = anglePer;
    let lastBoundary = null;

    const start = performance.now();
    const end = start + TOTAL_MS;

    function getRotationDegrees(el){
      const st = getComputedStyle(el);
      const tr = st.transform;
      if(!tr || tr === 'none') return 0;
      const m = tr.match(/matrix\(([^)]+)\)/);
      if(!m) return 0;
      const parts = m[1].split(',').map(v => parseFloat(v.trim()));
      const a = parts[0], b = parts[1];
      let angle = Math.atan2(b, a) * (180/Math.PI);
      if(angle < 0) angle += 360;
      return angle;
    }

    function tickLoop(now){
      const rot = getRotationDegrees(wheelEl);
      const boundaryIndex = Math.floor(rot / boundaries);

      if(lastBoundary === null) lastBoundary = boundaryIndex;

      if(boundaryIndex !== lastBoundary){
        playTick();
        lastBoundary = boundaryIndex;
      }

      if(now < end) requestAnimationFrame(tickLoop);
    }
    requestAnimationFrame(tickLoop);

    setTimeout(() => {
      spotlightEl.classList.add('on');
      statusText.textContent = "Finalising result…";
      setTimeout(() => {
        winnerNameEl.textContent = winnerName;
        drawTimeEl.textContent = new Date().toLocaleString('en-AU', { dateStyle:'medium', timeStyle:'short' });

        modal.classList.add('show');
        try{ winAudio.play().catch(()=>{}); }catch(e){}
        startConfetti(3000);

        statusText.textContent = "Winner selected";
        spinBtn.textContent = "DONE";
      }, REVEAL_PAUSE);
    }, TOTAL_MS);
  }

  buildWheel();
  spinBtn.addEventListener('click', spin);
  setTimeout(() => { statusText.textContent = "Ready…"; spin(); }, 900);
})();
</script>

</body>
</html>  
