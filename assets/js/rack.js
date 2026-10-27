(function(){
  const rack=document.getElementById('dnctRack'); if(!rack) return;
  // ---- EDYTUJ TUTAJ: jednostki w szafie, od góry ----
  const SERVERS=[
    {type:'switch', name:'CORE', sub:'SWITCH 10G'},
    {type:'blank'},
    {type:'server', u:2, name:'PL-01', sub:'64 GB · NVMe', ram:'64 GB DDR4', disk:'4× NVMe', games:'Minecraft'},
    {type:'server', u:2, name:'PL-02', sub:'64 GB · NVMe', ram:'64 GB DDR4', disk:'4× NVMe', games:'Minecraft, Zomboid'},
    {type:'server', u:1, name:'PL-03', sub:'32 GB · SSD', ram:'32 GB DDR4', disk:'2× SSD', games:'Vintage Story'},
    {type:'server', u:1, name:'PL-04', sub:'32 GB · SSD', ram:'32 GB DDR4', disk:'2× SSD', games:'Minecraft'},
    {type:'blank'},
    {type:'backup', name:'BACKUP', sub:'kopie co noc'},
  ];
  // ----------------------------------------------------
  const bay=rack.querySelector('[data-units]');
  const pw='<svg viewBox="0 0 24 24" fill="none" stroke-width="3" stroke-linecap="round"><path d="M12 3v8"/><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/></svg>';
  const led=(c='')=>`<i class="dnct-led ${c}"></i>`;
  let idx=0;
  SERVERS.forEach(s=>{
    const u=document.createElement('div'); u.style.setProperty('--i',idx++);
    if(s.type==='blank'){u.className='dnct-u dnct-u--blank';bay.appendChild(u);return;}
    const id=`<div class="dnct-u__id"><div class="dnct-u__name"><em>DNCT</em> ${s.name}</div><div class="dnct-u__sub">${s.sub||''}</div></div>`;
    if(s.type==='switch'){
      u.className='dnct-u dnct-u--1 dnct-u--sw';
      u.innerHTML=`<div class="dnct-u__handle"></div><button type="button" class="dnct-u__power" aria-label="Wyłącz serwer">${pw}</button>${id}<div class="dnct-ports">${Array.from({length:24},()=>`<span class="dnct-port">${led()}${led()}</span>`).join('')}</div><div class="dnct-u__sfp"><i></i><i></i><i></i><i></i></div><div class="dnct-u__handle dnct-u__handle--r"></div>`;
    } else if(s.type==='backup'){
      u.className='dnct-u dnct-u--2 dnct-u--bk';
      u.innerHTML=`<div class="dnct-u__handle"></div><button type="button" class="dnct-u__power" aria-label="Wyłącz serwer">${pw}</button>${id}<div class="dnct-u__slots">${[62,48,80,55,70,40,66,58].map((h,k)=>`<i style="--h:${h}%;--sd:${-k*.35}s"></i>`).join('')}</div><div class="dnct-u__leds">${led()}${led()}${led()}${led()}</div><div class="dnct-u__handle dnct-u__handle--r"></div>`;
      s.info={Typ:'Kopie zapasowe',Harmonogram:'co noc',Przywracanie:'1 kliknięcie'};
    } else {
      const n=s.u===2?8:4;
      u.className='dnct-u '+(s.u===2?'dnct-u--2':'dnct-u--1');
      u.innerHTML=`<div class="dnct-u__handle"></div><button type="button" class="dnct-u__power" aria-label="Wyłącz serwer">${pw}</button>${id}<div class="dnct-u__leds">${led()}${led()}${led()}${led()}</div><div class="dnct-u__drives">${Array.from({length:n},()=>`<span class="dnct-drive">${led()}${led()}</span>`).join('')}</div><div class="dnct-u__vent" style="--d:${-Math.random()*4}s"></div><div class="dnct-u__load"><span>CPU</span><div class="dnct-bar"><i></i></div><span>RAM</span><div class="dnct-bar"><i></i></div></div><div class="dnct-u__handle dnct-u__handle--r"></div>`;
      s.info={RAM:s.ram,Dyski:s.disk,Gry:s.games};
    }
    u.insertAdjacentHTML('beforeend','<i class="dnct-u__slide dnct-u__slide--l"></i><i class="dnct-u__slide dnct-u__slide--r"></i>');
    u.tabIndex=0; u._s=s; u.dataset.kind=s.type; bay.appendChild(u);
  });
  const units=[...bay.querySelectorAll('.dnct-u:not(.dnct-u--blank)')];

  // szyny: numeracja U
  const rails=rack.querySelectorAll('.dnct-rack__rail');
  function railNums(){ rails.forEach(r=>{r.innerHTML='';const h=r.offsetHeight,step=15.33;let k=Math.floor(h/step);for(let i=0;i<k;i+=3){const s=document.createElement('span');s.style.top=(4+i*step+7)+'px';s.textContent=String(k-i).padStart(2,'0');r.appendChild(s);}});}

  const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
  const rnd=(a,b)=>a+Math.random()*(b-a);
  function setLed(el,cls){el.className='dnct-led'+(cls?' '+cls:'');}

  // boot: szara -> bursztyn -> szara -> zielona
  function boot(u,delay){
    u._booting=true;
    const dr=[...u.querySelectorAll('.dnct-drive .dnct-led, .dnct-port .dnct-led')];
    dr.forEach(l=>setLed(l,''));
    setTimeout(()=>dr.forEach((l,k)=>setTimeout(()=>setLed(l,k%2?'':'on-g'),k*(u.classList.contains('dnct-u--sw')?12:45))),delay+820);
    setTimeout(()=>{u._booting=false;},delay+820+dr.length*45+200);
    const sl=u.querySelectorAll('.dnct-u__leds .dnct-led, .dnct-u__sfp');
    const status=u.querySelectorAll('.dnct-u__leds .dnct-led');
    const seq=[['on-a',0],['',260],['on-a',420],['',600],['on-g',780]];
    seq.forEach(([c,t])=>setTimeout(()=>status.forEach((l,i)=>setLed(l,i===0?c:(c==='on-g'?(i===1?'on-b':''):c))),delay+t));
    setTimeout(()=>{u.classList.add('is-up'); if(u.classList.contains('dnct-u--bk')) u.classList.add('is-sync');},delay+800);
  }
  function start(){
    rack.classList.add('is-in'); railNums();
    setTimeout(()=>units.concat([...bay.querySelectorAll('.dnct-u--blank')]).forEach(u=>u.classList.add('is-placed')), reduce?0:2600);
    units.forEach((u,i)=>{u._booting=true; u.querySelectorAll('.dnct-led').forEach(l=>setLed(l,'')); boot(u,reduce?0:(+u.style.getPropertyValue('--i'))*170+950);});
    if(!rack._loop){rack._loop=1; setTimeout(loop, reduce?0:1700);}
  }

  // aktywność: dyski, porty, obciążenie
  let t0=Date.now();
  function loop(){
    units.forEach(u=>{
      if(u._booting||!u.classList.contains('is-up')) return;
      u.querySelectorAll('.dnct-drive').forEach(d=>{const [a,b]=d.children; setLed(a,'on-g'); setLed(b,Math.random()<.35?'on-b':'');});
      u.querySelectorAll('.dnct-port').forEach((p,i)=>{const [a,b]=p.children; const link=i<18; setLed(a,link?'on-g':''); setLed(b,link&&Math.random()<.45?'on-a':'');});
      const st=u.querySelectorAll('.dnct-u__leds .dnct-led'); if(st.length>=4){ setLed(st[2],Math.random()<.5?'on-b':''); setLed(st[3],Math.random()<.2?'on-p':''); }
    });
    if(!reduce) setTimeout(loop, rnd(110,240));
  }
  function loads(){
    units.forEach(u=>{const b=u.querySelectorAll('.dnct-bar i'), l=u.querySelectorAll('.dnct-u__load b'); if(!b.length) return;
      if(!u.classList.contains('is-up')){b[0].style.width='0%';b[1].style.width='0%';return;} b[0].style.width=rnd(18,74)+'%'; b[1].style.width=rnd(35,82)+'%';});
  }
  setInterval(()=>{ if(rack.classList.contains('is-in')) loads(); }, 2600);

  // uptime szafy (licznik od wczytania strony)


  // dymek
  const tip=rack.querySelector('.dnct-rack__tip');
  function show(u){const s=u._s; if(!s) return; const info=Object.assign({},s.info||{SWITCH:'10 Gbit/s',Porty:'24 + 4 SFP+','Ochrona':'DDoS'});
    tip.innerHTML=`<h4><i class="dnct-led on-g"></i>DNCT ${s.name}</h4><dl>${Object.entries(info).map(([k,v])=>`<dt>${k.toUpperCase()}</dt><dd>${v}</dd>`).join('')}</dl>`;
    const r=rack.getBoundingClientRect(), b=u.getBoundingClientRect();
    let x=b.right-r.left-tip.offsetWidth-20, y=b.top-r.top-tip.offsetHeight-10; if(y<6) y=b.bottom-r.top+10;
    tip.style.left=Math.max(10,x)+'px'; tip.style.top=y+'px'; tip.classList.add('show');}
  units.forEach(u=>{u.addEventListener('mouseenter',()=>show(u));u.addEventListener('focus',()=>show(u));u.addEventListener('mouseleave',()=>tip.classList.remove('show'));u.addEventListener('blur',()=>tip.classList.remove('show'));});


  // easter egg: wyłączenie serwera przyciskiem zasilania
  const MSG=['No i przez ciebie ktoś ma zepsuty wieczór.','Gdzieś właśnie wywaliło 12 graczy z serwera. Gratulacje.','Kasia budowała ten zamek trzy tygodnie. Mam nadzieję, że jesteś z siebie dumny.','Admin właśnie spadł z krzesła.','Ktoś na Discordzie już pisze „czy serwer leży?”.','Spokojnie, odpalamy go z powrotem. Tym razem ci się upiekło.'];
  let mi=0, toastT=null; const toast=rack.querySelector('.dnct-rack__toast');
  units.forEach(u=>{const btn=u.querySelector('.dnct-u__power'); if(!btn) return;
    btn.addEventListener('click',e=>{
      e.stopPropagation(); if(u._booting||u.classList.contains('is-down')||!u.classList.contains('is-up')) return;
      u.classList.remove('is-up'); u.classList.add('is-down'); u._booting=true; tip.classList.remove('show');
      u.querySelectorAll('.dnct-led').forEach(l=>setLed(l,'')); u.querySelectorAll('.dnct-bar i').forEach(b=>b.style.width='0%');
      if(u.classList.contains('dnct-u--bk')) u.classList.remove('is-sync');
      toast.querySelector('b').textContent=`DNCT ${u._s.name} wyłączony`; toast.querySelector('span').textContent=MSG[mi++%MSG.length];
      toast.classList.add('show'); clearTimeout(toastT); toastT=setTimeout(()=>toast.classList.remove('show'),3800);
      setTimeout(()=>{u.classList.remove('is-down'); boot(u,0); setTimeout(loads,1300);},3200);
    });
  });
  // start, gdy szafa wejdzie w kadr
  // pętla: co ~6 s kolejna jednostka robi restart (wysuwa się, boot, wraca)
  let cyc=0, cycT=null;
  function cycle(){
    const list=units.filter(u=>!u.classList.contains('dnct-u--sw'));
    const u=list[cyc++%list.length]; const st=u.querySelectorAll('.dnct-u__leds .dnct-led');
    u.classList.add('is-cycle'); u.classList.remove('is-up'); u._booting=true; u.querySelectorAll('.dnct-led').forEach(l=>setLed(l,''));
    u.querySelectorAll('.dnct-bar i').forEach(b=>b.style.width='0%');
    setTimeout(()=>{u.classList.remove('is-cycle');},1100);
    setTimeout(()=>{boot(u,0);},1500);
    setTimeout(loads,2700);
    cycT=setTimeout(cycle,6200);
  }
  let running=false;
  function stop(){running=false;rack.classList.remove('is-in');clearTimeout(cycT);units.concat([...bay.querySelectorAll('.dnct-u--blank')]).forEach(u=>u.classList.remove('is-up','is-cycle','is-sync','is-placed'));}
  if('IntersectionObserver' in window){
    const io=new IntersectionObserver(e=>{
      if(e[0].isIntersecting&&!running){running=true;start();loads();}
      else if(!e[0].isIntersecting&&running){stop();}
    },{threshold:.2}); io.observe(rack);
  } else {start();loads();}
  addEventListener('resize',railNums);
})();

/* ===== Efekty szafy na stronie głównej: przechył 3D + światło pod kursorem + podświetlanie z liczb ===== */
(function(){
  const rack=document.getElementById('dnctRack'); if(!rack) return;
  const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fine=matchMedia('(hover:hover) and (pointer:fine)').matches;
  const MAX=4; // maks. kąt przechyłu w stopniach
  let lx=null,ly=null,raf=0;
  function apply(){
    raf=0; if(lx===null) return;
    const r=rack.getBoundingClientRect(); if(!r.width) return;
    const px=(lx-r.left)/r.width, py=(ly-r.top)/r.height;
    if(px<0||px>1||py<0||py>1){ reset(); return; }
    rack.style.setProperty('--ry',((px-.5)*2*MAX).toFixed(2)+'deg');
    rack.style.setProperty('--rx',((.5-py)*2*MAX).toFixed(2)+'deg');
    rack.style.setProperty('--mx',(px*100).toFixed(1)+'%');
    rack.style.setProperty('--my',(py*100).toFixed(1)+'%');
    rack.classList.add('is-lit');
  }
  function reset(){ lx=ly=null; rack.style.setProperty('--rx','0deg'); rack.style.setProperty('--ry','0deg'); rack.classList.remove('is-lit'); }
  const sched=()=>{ if(!raf) raf=requestAnimationFrame(apply); };
  if(fine&&!reduce){
    addEventListener('mousemove',e=>{ lx=e.clientX; ly=e.clientY; sched(); },{passive:true});
    // smooth-scroll (Lenis) przesuwa treść pod nieruchomym kursorem - przelicz z ostatniej pozycji
    addEventListener('scroll',()=>{ if(lx!==null) sched(); },{passive:true});
    document.addEventListener('mouseleave',reset);
  }
  // liczby obok podświetlają pasujące jednostki szafy
  const pick={
    switch:u=>u.dataset.kind==='switch',
    server:u=>u.dataset.kind==='server',
    small:u=>u.dataset.kind==='server'&&u._s&&u._s.u===1,
    all:u=>u.dataset.kind!=='blank'
  };
  function hl(key){
    const f=pick[key]; if(!f) return;
    rack.classList.add('has-hl');
    rack.querySelectorAll('.dnct-u').forEach(u=>u.classList.toggle('is-hl',!!f(u)));
  }
  function unhl(){ rack.classList.remove('has-hl'); rack.querySelectorAll('.is-hl').forEach(u=>u.classList.remove('is-hl')); }
  document.querySelectorAll('[data-hl]').forEach(el=>{
    const k=el.getAttribute('data-hl');
    el.addEventListener('mouseenter',()=>hl(k)); el.addEventListener('mouseleave',unhl);
    el.addEventListener('focus',()=>hl(k)); el.addEventListener('blur',unhl);
  });
})();
