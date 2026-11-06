<style>
    .raffle-container {
        width: 100%;
        height: 100%;
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: visible;
    }
    
    .wheel-wrapper {
        position: relative;
        width: 450px;
        height: 450px;
    }
    
    .wheel {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        position: relative;
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.6),
                    inset 0 0 30px rgba(0, 0, 0, 0.3);
        border: 8px solid rgba(255, 255, 255, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .wheel.spinning {
        animation: spinFast 0.3s linear infinite;
    }
    
    .wheel.stop {
        animation: none !important;
    }
    
    @keyframes spinFast {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .wheel-segment {
        position: absolute;
        width: 100%;
        height: 100%;
        clip-path: polygon(50% 50%, 50% 0%, 100% 0%);
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }
    
    .segment-text {
        position: absolute;
        transform: rotate(90deg) translateY(-160px);
        font-weight: 700;
        font-size: 12px;
        color: white;
        text-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);
        width: 130px;
        text-align: center;
        white-space: normal;
        word-break: break-word;
        line-height: 1.1;
        max-height: 60px;
        overflow: hidden;
    }
    
    .wheel-pointer {
        position: absolute;
        top: -35px;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 0;
        border-left: 20px solid transparent;
        border-right: 20px solid transparent;
        border-top: 35px solid rgba(255, 107, 53, 0.95);
        z-index: 20;
        filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.4));
    }
    
    .stats-display {
        position: absolute;
        bottom: -180px;
        left: 50%;
        transform: translateX(-50%);
        text-align: center;
        color: white;
        background: rgba(0, 0, 0, 0.3);
        padding: 15px 30px;
        border-radius: 10px;
        backdrop-filter: blur(10px);
        min-width: 300px;
        z-index: 10;
    }
    
    .stat-item {
        display: inline-block;
        margin: 0 15px;
        font-size: 14px;
    }
    
    .stat-value {
        font-weight: bold;
        font-size: 24px;
        display: block;
        margin-top: 5px;
    }
    
    /* Winner Modal */
    .modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        animation: fadeIn 0.3s ease-out;
    }
    
    .modal-backdrop.show {
        display: flex;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .winner-modal {
        background: linear-gradient(135deg, rgba(255,107,53,0.98) 0%, rgba(247,147,30,0.98) 100%);
        border-radius: 30px;
        padding: 60px 50px;
        text-align: center;
        max-width: 600px;
        width: 90%;
        box-shadow: 0 20px 80px rgba(0, 0, 0, 0.5),
                    0 0 60px rgba(255, 107, 53, 0.6);
        border: 3px solid rgba(255, 255, 255, 0.3);
        animation: popIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        overflow: hidden;
    }
    
    .winner-modal::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: shine 3s ease-in-out infinite;
    }
    
    @keyframes shine {
        0%, 100% { transform: translate(-30%, -30%); }
        50% { transform: translate(30%, 30%); }
    }
    
    @keyframes popIn {
        0% {
            transform: scale(0.3) rotateX(90deg);
            opacity: 0;
        }
        50% {
            transform: scale(1.05) rotateX(0deg);
        }
        100% {
            transform: scale(1) rotateX(0deg);
            opacity: 1;
        }
    }
    
    .modal-content {
        position: relative;
        z-index: 2;
    }
    
    .confetti {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        pointer-events: none;
    }
    
    .confetti-piece {
        position: absolute;
        width: 10px;
        height: 10px;
        animation: confettiFall 2.5s ease-out forwards;
    }
    
    @keyframes confettiFall {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(500px) rotate(720deg);
            opacity: 0;
        }
    }
    
    .modal-trophy {
        font-size: 80px;
        margin-bottom: 20px;
        animation: bounce 0.6s ease-out;
    }
    
    @keyframes bounce {
        0% { transform: scale(0); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .modal-title {
        font-size: 52px;
        font-weight: 900;
        color: white;
        text-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        margin-bottom: 30px;
        letter-spacing: 2px;
    }
    
    .modal-initials {
        font-size: 100px;
        font-weight: 900;
        color: rgba(255, 255, 255, 0.95);
        text-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        margin: 15px 0;
        line-height: 1;
        min-height: 120px;
    }
    
    .modal-name {
        font-size: 38px;
        font-weight: 700;
        color: white;
        text-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        margin: 25px 0;
        word-break: break-word;
    }
    
    .modal-subtitle {
        font-size: 16px;
        color: rgba(255, 255, 255, 0.95);
        margin-top: 15px;
        font-style: italic;
    }
    
    .modal-close {
        margin-top: 40px;
        padding: 15px 45px;
        background: rgba(255, 255, 255, 0.25);
        border: 2px solid rgba(255, 255, 255, 0.6);
        color: white;
        font-size: 16px;
        font-weight: 700;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .modal-close:hover {
        background: rgba(255, 255, 255, 0.35);
        border-color: rgba(255, 255, 255, 0.9);
        transform: scale(1.05);
    }
</style>

<div class="raffle-container">
    <div class="wheel-wrapper">
        <div class="wheel-pointer"></div>
        <div class="wheel spinning" id="spinningWheel"></div>
        <div class="stats-display">
            <div class="stat-item">
                <div>Total Entries</div>
                <div class="stat-value" id="totalEntries">0</div>
            </div>
            <div class="stat-item">
                <div>Participants</div>
                <div class="stat-value" id="totalParticipants">0</div>
            </div>
        </div>
    </div>
</div>

<!-- Winner Modal -->
<div class="modal-backdrop" id="winnerModal">
    <div class="winner-modal">
        <div class="confetti" id="confetti"></div>
        <div class="modal-content">
            <div class="modal-trophy">🏆</div>
            <div class="modal-title">CONGRATULATIONS!</div>
            <div class="modal-initials" id="modalInitials">--</div>
            <div class="modal-name" id="modalName">Winner Name</div>
            <div class="modal-subtitle" id="modalSubtitle">Prize WINNER!</div>
            <button class="modal-close" onclick="location.reload()">Close</button>
        </div>
    </div>
</div>

<script>
    (function() {
        'use strict';
        
        const animationData = window.ANIMATION_DATA || {};
        const winner = window.WINNER_DATA || {};
        const entries = window.ENTRIES_DATA || [];
        
        const wheel = document.getElementById('spinningWheel');
        const totalEntries = document.getElementById('totalEntries');
        const totalParticipants = document.getElementById('totalParticipants');
        const winnerModal = document.getElementById('winnerModal');
        const modalInitials = document.getElementById('modalInitials');
        const modalName = document.getElementById('modalName');
        const modalSubtitle = document.getElementById('modalSubtitle');
        const confettiContainer = document.getElementById('confetti');
        
        // Set stats
        totalEntries.textContent = animationData.stats?.total_entries || animationData.total_entries || 0;
        totalParticipants.textContent = animationData.stats?.total_participants || animationData.total_participants || 0;
        
        if (modalSubtitle && animationData.prize_name) {
            modalSubtitle.textContent = animationData.prize_name + ' WINNER!';
        }
        
        // Build wheel segments with names
        const colors = [
            '#ff6b35', '#f7931e', '#fdb913', '#c1272d',
            '#662e91', '#003da5', '#009fdf', '#00a651',
            '#88c540', '#ffb81c', '#f47b20', '#e4405f'
        ];
        
        const entryList = Array.isArray(entries) ? entries : (entries.data || []);
        const segmentCount = Math.max(entryList.length, 8);
        
        for (let i = 0; i < segmentCount; i++) {
            const segment = document.createElement('div');
            segment.className = 'wheel-segment';
            segment.style.background = colors[i % colors.length];
            segment.style.transform = `rotate(${(360 / segmentCount) * i}deg)`;
            
            const text = document.createElement('div');
            text.className = 'segment-text';
            
            let displayName = 'Entry ' + (i + 1);
            if (entryList[i] && entryList[i].first_name) {
                const first = entryList[i].first_name || '';
                const last = entryList[i].last_name || '';
                displayName = (first + ' ' + last).trim().substring(0, 25);
            }
            
            text.textContent = displayName;
            segment.appendChild(text);
            wheel.appendChild(segment);
        }
        
        console.log('🎡 Starting spinning wheel animation');
        
        // Spin for 3 seconds, then reveal winner
        setTimeout(() => {
            wheel.classList.remove('spinning');
            wheel.classList.add('stop');
            
            const randomRotation = Math.floor(Math.random() * 360);
            wheel.style.transform = `rotate(${randomRotation}deg)`;
            
            const firstName = (winner && winner.first_name) ? String(winner.first_name).trim() : 'Winner';
            const lastName = (winner && winner.last_name) ? String(winner.last_name).trim() : '';
            const firstInitial = firstName.charAt(0).toUpperCase();
            const lastInitial = lastName ? lastName.charAt(0).toUpperCase() : '';
            const initials = firstInitial + lastInitial;
            const fullName = (firstName + ' ' + lastName).trim();
            
            setTimeout(() => {
                modalInitials.textContent = initials;
                modalName.textContent = fullName;
                
                winnerModal.classList.add('show');
                
                createConfetti();
                
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvPXijkIHGm98OKbTA==');
                    audio.volume = 0.7;
                    audio.play().catch(() => {});
                } catch(e) {}
                
            }, 1000);
            
        }, 3000);
        
        function createConfetti() {
            for (let i = 0; i < 60; i++) {
                const piece = document.createElement('div');
                piece.className = 'confetti-piece';
                piece.style.left = Math.random() * 100 + '%';
                piece.style.background = colors[Math.floor(Math.random() * colors.length)];
                piece.style.animation = `confettiFall ${2 + Math.random() * 1}s ease-out forwards`;
                piece.style.animationDelay = Math.random() * 0.4 + 's';
                confettiContainer.appendChild(piece);
            }
        }
        
    })();
</script>