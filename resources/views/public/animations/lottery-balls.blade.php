<style>
    .lottery-container {
        width: 100%;
        height: 100%;
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    .machine {
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border: 5px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        position: relative;
        backdrop-filter: blur(10px);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                    inset 0 0 50px rgba(255, 255, 255, 0.1);
        overflow: hidden;
    }
    
    .ball {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        position: absolute;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3),
                    inset -5px -5px 10px rgba(0, 0, 0, 0.2),
                    inset 5px 5px 10px rgba(255, 255, 255, 0.3);
        transition: transform 0.1s;
    }
    
    .ball.winner {
        animation: winnerGlow 0.5s infinite alternate;
    }
    
    @keyframes winnerGlow {
        from {
            box-shadow: 0 0 20px #ffd700,
                        0 0 40px #ffd700,
                        0 0 60px #ffd700;
            transform: scale(1);
        }
        to {
            box-shadow: 0 0 30px #ffd700,
                        0 0 50px #ffd700,
                        0 0 70px #ffd700;
            transform: scale(1.1);
        }
    }
    
    .tube {
        width: 80px;
        height: 150px;
        background: rgba(255, 255, 255, 0.2);
        border: 3px solid rgba(255, 255, 255, 0.4);
        border-radius: 10px;
        position: absolute;
        bottom: -100px;
        left: 50%;
        transform: translateX(-50%);
        backdrop-filter: blur(5px);
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
</style>

<script>
    function loadLotteryBallsAnimation() {
        const stage = document.getElementById('animationStage');
        
        const container = document.createElement('div');
        container.className = 'lottery-container';
        container.innerHTML = `
            <div class="machine" id="machine">
                <div class="tube"></div>
            </div>
            <div class="stats-display">
                <div class="stat-item">
                    <div>Total Entries</div>
                    <div class="stat-value">${ANIMATION_DATA.stats.total_entries}</div>
                </div>
                <div class="stat-item">
                    <div>Participants</div>
                    <div class="stat-value">${ANIMATION_DATA.stats.total_participants}</div>
                </div>
                <div class="stat-item">
                    <div>Draw Time</div>
                    <div class="stat-value">${ANIMATION_DATA.stats.draw_time}</div>
                </div>
            </div>
        `;
        
        stage.appendChild(container);
        
        // Start animation
        startLotteryAnimation();
    }
    
    function startLotteryAnimation() {
        const machine = document.getElementById('machine');
        const balls = [];
        const ballCount = 30;
        const colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
        
        // Create balls
        for (let i = 0; i < ballCount; i++) {
            const ball = document.createElement('div');
            ball.className = 'ball';
            ball.style.background = colors[i % colors.length];
            ball.style.left = Math.random() * 350 + 'px';
            ball.style.top = Math.random() * 350 + 'px';
            
            machine.appendChild(ball);
            
            balls.push({
                element: ball,
                x: parseFloat(ball.style.left),
                y: parseFloat(ball.style.top),
                vx: (Math.random() - 0.5) * 4,
                vy: (Math.random() - 0.5) * 4
            });
        }
        
        // Physics animation
        let frame = 0;
        const maxFrames = 200; // ~5 seconds at 40fps
        
        function animate() {
            frame++;
            
            balls.forEach((ball, index) => {
                // Update position
                ball.x += ball.vx;
                ball.y += ball.vy;
                
                // Bounce off walls
                if (ball.x < 0 || ball.x > 350) ball.vx *= -0.9;
                if (ball.y < 0 || ball.y > 350) ball.vy *= -0.9;
                
                // Keep in bounds
                ball.x = Math.max(0, Math.min(350, ball.x));
                ball.y = Math.max(0, Math.min(350, ball.y));
                
                // Apply gravity and friction
                ball.vy += 0.2;
                ball.vx *= 0.99;
                ball.vy *= 0.99;
                
                // Update DOM
                ball.element.style.left = ball.x + 'px';
                ball.element.style.top = ball.y + 'px';
            });
            
            // Slow down animation over time
            if (frame > maxFrames * 0.6) {
                balls.forEach(ball => {
                    ball.vx *= 0.95;
                    ball.vy *= 0.95;
                });
            }
            
            if (frame < maxFrames) {
                requestAnimationFrame(animate);
            } else {
                // Animation complete - select winner ball
                selectWinnerBall(balls);
            }
        }
        
        animate();
        
        // Play sound if enabled
        if (soundEnabled) {
            playBallSound();
        }
    }
    
    function selectWinnerBall(balls) {
        // Pick random ball as "winner"
        const winnerBall = balls[Math.floor(Math.random() * balls.length)];
        winnerBall.element.classList.add('winner');
        
        // Animate winner ball to center and out through tube
        setTimeout(() => {
            winnerBall.element.style.transition = 'all 1s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
            winnerBall.element.style.left = '175px';
            winnerBall.element.style.top = '175px';
            winnerBall.element.style.transform = 'scale(1.5)';
            
            if (soundEnabled) {
                playWhooshSound();
            }
            
            setTimeout(() => {
                winnerBall.element.style.top = '500px';
                
                setTimeout(() => {
                    animationComplete = true;
                    showWinnerReveal();
                }, 500);
            }, 1000);
        }, 500);
    }
    
    function playBallSound() {
        // Simple audio context for ball bouncing sounds
        if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            const audioCtx = new AudioContext();
            
            // Play periodic tapping sounds
            let time = 0;
            for (let i = 0; i < 50; i++) {
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                oscillator.frequency.value = 200 + Math.random() * 100;
                gainNode.gain.value = 0.1;
                
                oscillator.start(time);
                oscillator.stop(time + 0.05);
                
                time += 0.1;
            }
        }
    }
    
    function playWhooshSound() {
        if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            const audioCtx = new AudioContext();
            
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            
            oscillator.frequency.setValueAtTime(800, audioCtx.currentTime);
            oscillator.frequency.exponentialRampToValueAtTime(200, audioCtx.currentTime + 0.5);
            
            gainNode.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
            
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.5);
        }
    }
</script>