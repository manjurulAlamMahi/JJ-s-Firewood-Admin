<style>
    .winner-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        animation: fadeIn 0.5s;
    }
    
    .winner-modal.active {
        display: flex;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .winner-content {
        background: white;
        padding: 50px;
        border-radius: 20px;
        text-align: center;
        max-width: 600px;
        position: relative;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        animation: scaleIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    @keyframes scaleIn {
        from {
            transform: scale(0.5);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    .emoji-header {
        font-size: 80px;
        margin-bottom: 20px;
        animation: bounce 1s infinite;
    }
    
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-20px); }
    }
    
    .winner-heading {
        font-size: 36px;
        font-weight: bold;
        color: #333;
        margin-bottom: 30px;
    }
    
    .winner-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 30px;
        border-radius: 15px;
        border: 5px solid #10b981;
        margin: 20px 0;
    }
    
    .winner-name {
        font-size: 48px;
        font-weight: bold;
        color: white;
        margin-bottom: 15px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }
    
    .prize-name {
        font-size: 24px;
        color: rgba(255, 255, 255, 0.9);
        font-weight: 500;
    }
    
    .awesome-btn {
        background: #10b981;
        color: white;
        border: none;
        padding: 15px 50px;
        font-size: 20px;
        font-weight: bold;
        border-radius: 50px;
        cursor: pointer;
        margin-top: 30px;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
    }
    
    .awesome-btn:hover {
        background: #059669;
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6);
    }
    
    .confetti {
        position: fixed;
        width: 10px;
        height: 10px;
        background: #f39c12;
        position: absolute;
        animation: confetti-fall 3s linear infinite;
    }
    
    @keyframes confetti-fall {
        to {
            transform: translateY(100vh) rotate(360deg);
        }
    }
</style>

<div class="winner-modal" id="winnerModal">
    <div class="winner-content">
        <div class="emoji-header">🎉 🏆 🎊</div>
        <h1 class="winner-heading">WINNER DRAWN!</h1>
        
        <div class="winner-box">
            <div class="winner-name" id="winnerNameDisplay"></div>
            <div class="prize-name" id="prizeNameDisplay"></div>
        </div>
        
        <button class="awesome-btn" onclick="closeWinnerModal()">Awesome!</button>
    </div>
</div>

<script>
    function showWinnerReveal() {
        // Update winner info
        document.getElementById('winnerNameDisplay').textContent = WINNER_NAME;
        document.getElementById('prizeNameDisplay').textContent = PRIZE_NAME;
        
        // Show modal
        document.getElementById('winnerModal').classList.add('active');
        
        // Start confetti
        createConfetti();
        
        // Play celebration sound
        if (soundEnabled) {
            playCelebrationSound();
        }
    }
    
    function closeWinnerModal() {
        const modal = document.getElementById('winnerModal');
        modal.style.animation = 'fadeOut 0.5s';
        
        setTimeout(() => {
            modal.classList.remove('active');
            
            // Reload page or redirect if admin
            if (IS_ADMIN) {
                window.location.reload();
            }
        }, 500);
    }
    
    function createConfetti() {
        const colors = ['#f39c12', '#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#1abc9c'];
        const confettiCount = 50;
        
        for (let i = 0; i < confettiCount; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 3 + 's';
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
            
            document.body.appendChild(confetti);
            
            // Remove after animation
            setTimeout(() => {
                confetti.remove();
            }, 5000);
        }
    }
    
    function playCelebrationSound() {
        if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            const audioCtx = new AudioContext();
            
            // Play ascending victory notes
            const notes = [261.63, 329.63, 392.00, 523.25]; // C, E, G, C
            notes.forEach((freq, i) => {
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                oscillator.type = 'sine';
                oscillator.frequency.value = freq;
                gainNode.gain.value = 0.3;
                
                const time = audioCtx.currentTime + (i * 0.15);
                oscillator.start(time);
                oscillator.stop(time + 0.3);
            });
        }
    }
</script>

<style>
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
</style>