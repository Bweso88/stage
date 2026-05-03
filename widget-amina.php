<!-- Widget Amina — Assistant IA StagIA -->
<style>
#amina-btn {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1b2a6b, #e8001c);
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(0,0,0,.25);
    font-size: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9000;
    transition: transform .2s;
}
#amina-btn:hover { transform: scale(1.1); }
#amina-panel {
    position: fixed;
    bottom: 90px;
    right: 24px;
    width: 320px;
    height: 440px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,.18);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 9000;
    border: 1px solid #e2e4ea;
}
#amina-panel.open { display: flex; }
.amina-header {
    background: linear-gradient(135deg, #1b2a6b, #2d3c8a);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #fff;
}
.amina-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.amina-name { font-weight: 600; font-size: 14px; }
.amina-status { font-size: 11px; opacity: .7; }
.amina-close { margin-left: auto; background: none; border: none; color: #fff; cursor: pointer; font-size: 18px; }
.amina-messages {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f9fafb;
}
.amina-msg {
    max-width: 85%;
    padding: 9px 12px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.5;
}
.amina-msg.bot {
    background: #fff;
    border: 1px solid #e2e4ea;
    align-self: flex-start;
    border-bottom-left-radius: 4px;
}
.amina-msg.user {
    background: #1b2a6b;
    color: #fff;
    align-self: flex-end;
    border-bottom-right-radius: 4px;
}
.amina-input-wrap {
    padding: 12px;
    border-top: 1px solid #e2e4ea;
    display: flex;
    gap: 8px;
}
.amina-input {
    flex: 1;
    padding: 8px 12px;
    border: 1.5px solid #e2e4ea;
    border-radius: 20px;
    font-family: inherit;
    font-size: 13px;
    outline: none;
}
.amina-input:focus { border-color: #1b2a6b; }
.amina-send {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #e8001c;
    border: none;
    cursor: pointer;
    color: #fff;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background .15s;
}
.amina-send:hover { background: #c40018; }
</style>

<button id="amina-btn" onclick="toggleAmina()" title="Chat avec Amina">🌸</button>

<div id="amina-panel">
    <div class="amina-header">
        <div class="amina-avatar">🌸</div>
        <div>
            <div class="amina-name">Amina</div>
            <div class="amina-status">Assistante StagIA</div>
        </div>
        <button class="amina-close" onclick="toggleAmina()">×</button>
    </div>
    <div class="amina-messages" id="amina-msgs">
        <div class="amina-msg bot">Bonjour ! Je suis Amina, votre assistante pour les stages. Comment puis-je vous aider ?</div>
    </div>
    <div class="amina-input-wrap">
        <input class="amina-input" id="amina-input" type="text" placeholder="Votre question..." onkeydown="if(event.key==='Enter')aminaSend()">
        <button class="amina-send" onclick="aminaSend()">➤</button>
    </div>
</div>

<script>
function toggleAmina() {
    const panel = document.getElementById('amina-panel');
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        document.getElementById('amina-input').focus();
    }
}

const aminaReplies = [
    { q: ['candidature','postuler','soumettre'], r: 'Pour soumettre une candidature, rendez-vous sur /inscription.php et créez votre compte. Vous pourrez ensuite postuler à une offre ou envoyer une candidature spontanée.' },
    { q: ['stage','durée','combien'], r: 'La durée initiale d\'un stage est de 31 jours maximum. Des renouvellements sont possibles (jusqu\'à 3), pour une durée totale de 5 mois.' },
    { q: ['statut','état','suivi'], r: 'Vous pouvez suivre l\'état de votre candidature en vous connectant à votre espace stagiaire sur la page d\'accueil.' },
    { q: ['renouvellement'], r: 'Pour demander un renouvellement, connectez-vous à votre espace stagiaire et faites la demande depuis votre fiche de stage. Un habilité la traitera.' },
    { q: ['lettre','document'], r: 'Votre lettre de stage est disponible dans votre espace stagiaire dès que votre stage est créé. Vous pouvez la télécharger en PDF ou DOCX.' },
    { q: ['bonjour','salut','hello'], r: 'Bonjour ! Je suis là pour répondre à vos questions sur les stages. Que puis-je faire pour vous ?' },
];

function aminaSend() {
    const input = document.getElementById('amina-input');
    const text  = input.value.trim();
    if (!text) return;

    const msgs = document.getElementById('amina-msgs');
    msgs.innerHTML += `<div class="amina-msg user">${text}</div>`;
    input.value = '';

    setTimeout(() => {
        const lower = text.toLowerCase();
        let reply = 'Je ne suis pas sûre de comprendre. Vous pouvez me poser des questions sur les candidatures, la durée des stages, les renouvellements ou les documents.';
        for (const r of aminaReplies) {
            if (r.q.some(k => lower.includes(k))) { reply = r.r; break; }
        }
        msgs.innerHTML += `<div class="amina-msg bot">${reply}</div>`;
        msgs.scrollTop = msgs.scrollHeight;
    }, 400);
    msgs.scrollTop = msgs.scrollHeight;
}
</script>
