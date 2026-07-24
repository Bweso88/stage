<?php /* widget-amina.php — Widget chat flottant Amina (style Studio Ghibli) */ ?>
<!-- Widget Amina -->
<style>
#amina-btn{
  position:fixed;bottom:28px;right:28px;z-index:9999;
  width:60px;height:60px;border-radius:50%;
  background:linear-gradient(135deg,#a8d8ea,#f7d794);
  border:none;cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,.2);
  font-size:28px;transition:transform .2s;
  display:flex;align-items:center;justify-content:center;
}
#amina-btn:hover{transform:scale(1.1)}
#amina-bubble{
  position:fixed;bottom:100px;right:28px;z-index:9999;
  width:340px;background:#fffef7;border-radius:20px;
  box-shadow:0 8px 32px rgba(0,0,0,.15);
  overflow:hidden;display:none;flex-direction:column;
  border:2px solid #f7d794;max-height:480px;
}
#amina-bubble.open{display:flex}
.amina-head{
  background:linear-gradient(135deg,#a8d8ea,#f7d794);
  padding:14px 18px;display:flex;align-items:center;gap:10px;
}
.amina-head .avatar{
  width:38px;height:38px;border-radius:50%;
  background:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;
  box-shadow:0 2px 6px rgba(0,0,0,.1);
}
.amina-head .info h4{margin:0;font-size:.9rem;font-weight:700;color:#1b2a6b}
.amina-head .info p{margin:0;font-size:.72rem;color:#5a6a8a}
.amina-head .close-btn{margin-left:auto;background:none;border:none;font-size:18px;cursor:pointer;color:#5a6a8a;line-height:1}
.amina-msgs{
  flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px;
  background:#fffef7;
}
.amina-msg{max-width:85%;padding:10px 14px;border-radius:14px;font-size:.82rem;line-height:1.5}
.amina-msg.bot{background:#e8f4fd;color:#2c3e50;border-bottom-left-radius:4px;align-self:flex-start}
.amina-msg.user{background:#1b2a6b;color:#fff;border-bottom-right-radius:4px;align-self:flex-end}
.amina-typing{display:flex;gap:4px;align-items:center;padding:10px 14px}
.amina-typing span{
  width:8px;height:8px;background:#a8d8ea;border-radius:50%;
  animation:aminaBounce .8s infinite;
}
.amina-typing span:nth-child(2){animation-delay:.15s}
.amina-typing span:nth-child(3){animation-delay:.3s}
@keyframes aminaBounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-6px)}}
.amina-input-wrap{
  padding:10px 12px;border-top:1px solid #f0e8c8;background:#fffef7;
  display:flex;gap:8px;align-items:center;
}
.amina-input-wrap input{
  flex:1;border:1.5px solid #e8dfc0;border-radius:20px;
  padding:8px 14px;font-size:.82rem;font-family:inherit;
  background:#fff8ee;outline:none;color:#2c3e50;
}
.amina-input-wrap input:focus{border-color:#a8d8ea}
.amina-input-wrap button{
  background:linear-gradient(135deg,#a8d8ea,#f7d794);
  border:none;border-radius:50%;width:34px;height:34px;cursor:pointer;
  font-size:16px;display:flex;align-items:center;justify-content:center;
}
.amina-chips{display:flex;flex-wrap:wrap;gap:6px;padding:0 14px 10px}
.amina-chip{
  background:#f0f8ff;border:1.5px solid #a8d8ea;color:#1b2a6b;
  border-radius:20px;padding:4px 12px;font-size:.76rem;cursor:pointer;
  font-family:inherit;transition:background .15s;
}
.amina-chip:hover{background:#a8d8ea;color:#fff}
</style>

<button id="amina-btn" onclick="aminaToggle()" title="Parler à Amina">🌿</button>

<div id="amina-bubble">
  <div class="amina-head">
    <div class="avatar">🌸</div>
    <div class="info">
      <h4>Amina</h4>
      <p>Assistante Stage • En ligne</p>
    </div>
    <button class="close-btn" onclick="aminaToggle()">✕</button>
  </div>
  <div class="amina-msgs" id="amina-msgs"></div>
  <div class="amina-chips" id="amina-chips"></div>
  <div class="amina-input-wrap">
    <input type="text" id="amina-input" placeholder="Posez votre question…" onkeydown="if(event.key==='Enter')aminaSend()">
    <button onclick="aminaSend()">➤</button>
  </div>
</div>

<script>
(function(){
const KB = {
  'bonjour': ['Bonjour ! 🌸 Je suis Amina, votre assistante Stage. Comment puis-je vous aider aujourd\'hui ?'],
  'stage': [
    'Pour programmer un stage, la candidature doit être validée par le superviseur. L\'habilité peut ensuite cliquer sur "🎓 Programmer" dans le détail de la candidature.',
    'La durée initiale d\'un stage est de 31 jours maximum. Des renouvellements sont possibles jusqu\'à 4 fois.',
  ],
  'candidature': [
    'Le workflow de candidature passe par deux niveaux : l\'habilité (niv1) puis le superviseur (niv2).',
    'Une candidature peut être soumise sur offre ouverte ou en candidature spontanée.',
  ],
  'renouvellement': [
    'Un stage peut être renouvelé jusqu\'à 4 fois. La durée totale ne peut dépasser 5 mois.',
    'Pour demander un renouvellement, accédez au détail du stage et cliquez sur "Demander un renouvellement".',
  ],
  'connexion': ['Connectez-vous via admin.php pour le backoffice, ou index.php pour l\'espace stagiaire.'],
  'offre': ['Les offres ouvertes sont visibles sur la page d\'accueil. Les administrateurs et habilités peuvent créer des offres dans le backoffice.'],
  'default': [
    'Je ne suis pas sûre de comprendre. Pouvez-vous reformuler ?',
    'Cette question dépasse mes connaissances actuelles. Consultez votre administrateur.',
    'Je suis encore en apprentissage ! 🌱 Pour cette question, je vous recommande de contacter un administrateur.',
  ],
};

const chips = ['Programmer un stage', 'Renouvellement', 'Candidature', 'Se connecter'];
let open = false;

function aminaToggle() {
  open = !open;
  document.getElementById('amina-bubble').classList.toggle('open', open);
  if (open && !document.getElementById('amina-msgs').children.length) {
    aminaBot('Bonjour ! 🌸 Je suis Amina, votre assistante. Posez-moi vos questions sur Stage !');
    renderChips();
  }
}

function renderChips() {
  const c = document.getElementById('amina-chips');
  c.innerHTML = chips.map(t => `<button class="amina-chip" onclick="aminaAsk('${t}')">${t}</button>`).join('');
}

function aminaAsk(text) {
  document.getElementById('amina-input').value = text;
  aminaSend();
}

function aminaSend() {
  const inp = document.getElementById('amina-input');
  const msg = inp.value.trim();
  if (!msg) return;
  inp.value = '';
  addMsg(msg, 'user');
  document.getElementById('amina-chips').innerHTML = '';
  setTimeout(() => {
    const reply = findReply(msg.toLowerCase());
    aminaBot(reply);
    if (Math.random() > .6) renderChips();
  }, 600 + Math.random() * 400);
}

function findReply(text) {
  for (const [key, replies] of Object.entries(KB)) {
    if (key === 'default') continue;
    if (text.includes(key)) return replies[Math.floor(Math.random() * replies.length)];
  }
  const def = KB.default;
  return def[Math.floor(Math.random() * def.length)];
}

function aminaBot(text) {
  addMsg(text, 'bot');
}

function addMsg(text, role) {
  const msgs = document.getElementById('amina-msgs');
  const div = document.createElement('div');
  div.className = 'amina-msg ' + role;
  div.textContent = text;
  msgs.appendChild(div);
  msgs.scrollTop = msgs.scrollHeight;
}

window.aminaToggle = aminaToggle;
window.aminaSend   = aminaSend;
window.aminaAsk    = aminaAsk;
})();
</script>
